<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * Teste de concorrência REAL (sem mocks) para o saque imediato.
 *
 * Valida que o `SELECT ... FOR UPDATE` em `ContaRepositorioMySQL::buscarPorIdComLock`,
 * combinado com a transação no `ProcessarSaque`, impede que requisições
 * concorrentes deixem o saldo negativo — o requisito de "compatibilidade com
 * escalabilidade horizontal" do case técnico.
 *
 * Cenário:
 *   - Conta com saldo R$ 100,00
 *   - 20 processos disparam saque de R$ 80,00 simultaneamente via HTTP
 *   - Esperado: 1 sucesso (HTTP 201), 19 recusas (HTTP 422), saldo final R$ 20,00
 *
 * Como rodar:
 *   docker compose up -d
 *   docker compose exec app vendor/bin/phpunit tests/Integration/ConcorrenciaSaqueImediatoTest.php
 *
 * Ou, se as variáveis APP_BASE_URL e DB_* estiverem setadas no host:
 *   vendor/bin/phpunit tests/Integration/ConcorrenciaSaqueImediatoTest.php
 *
 * Requisitos:
 *   - Extensão PCNTL (presente em PHP CLI Linux/macOS por padrão; presente
 *     na imagem hyperf/hyperf:8.2-alpine usada pelo Dockerfile do projeto)
 *   - Servidor da aplicação rodando (acessível via APP_BASE_URL ou
 *     http://localhost:9501 por padrão)
 *
 * Não estende IntegracaoTestCase porque pcntl_fork() não é seguro dentro de
 * coroutines Swoole — este teste roda em contexto CLI puro, fazendo requests
 * HTTP externos contra a aplicação rodando no Docker.
 */
class ConcorrenciaSaqueImediatoTest extends TestCase
{
    private const N_PROCESSOS    = 20;
    private const SALDO_INICIAL  = '100.00';
    private const VALOR_SAQUE    = '80.00';
    private const TIMEOUT_CURL   = 30;

    private PDO $pdo;
    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('Extensão PCNTL não disponível neste ambiente.');
        }
        if (!function_exists('curl_init')) {
            $this->markTestSkipped('Extensão cURL não disponível neste ambiente.');
        }

        $this->baseUrl = rtrim(
            $_ENV['APP_BASE_URL'] ?? getenv('APP_BASE_URL') ?: 'http://localhost:9501',
            '/'
        );

        $dbHost = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $dbPort = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
        $dbName = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'saque_pix';
        $dbUser = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'app';
        $dbPass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'secret';

        $this->pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->pdo->exec('DELETE FROM account_withdraw_pix');
        $this->pdo->exec('DELETE FROM account_withdraw');
        $this->pdo->exec('DELETE FROM account');

        // Verifica que a aplicação está acessível antes de seguir
        $ch = curl_init($this->baseUrl . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $httpCode !== 200) {
            $this->markTestSkipped(
                "Aplicação não acessível em {$this->baseUrl}. " .
                "Suba com 'docker compose up -d' antes de rodar."
            );
        }
    }

    public function testSaqueConcorrenteNaoFicaNegativo(): void
    {
        // Setup: conta com saldo limitado
        $contaId = Uuid::uuid4()->toString();
        $this->pdo->prepare('INSERT INTO account (id, name, balance) VALUES (?, ?, ?)')
                  ->execute([$contaId, 'ConcorrênciaTest', self::SALDO_INICIAL]);

        // Arquivo onde os filhos gravam seus resultados (LOCK_EX evita corrupção)
        $arquivoResultados = tempnam(sys_get_temp_dir(), 'concorrencia-');
        file_put_contents($arquivoResultados, '');

        $url = $this->baseUrl . '/account/' . $contaId . '/balance/withdraw';
        $body = json_encode([
            'method'   => 'PIX',
            'pix'      => ['type' => 'email', 'key' => 'concorrencia@test.com'],
            'amount'   => (float) self::VALOR_SAQUE,
            'schedule' => null,
        ]);

        // Dispara N processos paralelos via fork
        $pids = [];
        for ($i = 1; $i <= self::N_PROCESSOS; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Falha em pcntl_fork()');
            }
            if ($pid === 0) {
                // Filho: faz o request e grava o resultado
                $this->dispararSaqueComoFilho($i, $url, $body, $arquivoResultados);
                exit(0);
            }
            $pids[] = $pid;
        }

        // Pai: espera todos os filhos
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        // Reconectar ao MySQL: fork() compartilha o descritor da conexão e os
        // processos filho a fecham ao sair, invalidando a conexão do pai.
        $dbHost = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $dbPort = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
        $dbName = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'saque_pix';
        $dbUser = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'app';
        $dbPass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'secret';
        $this->pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Coleta e classifica os resultados
        $linhas = file($arquivoResultados, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        @unlink($arquivoResultados);

        $this->assertCount(
            self::N_PROCESSOS,
            $linhas,
            'Esperava resposta de cada um dos ' . self::N_PROCESSOS . ' processos'
        );

        $sucessos = $recusasSaldo = 0;
        $statusInesperados = [];
        foreach ($linhas as $linha) {
            [$workerId, $statusHttp, $body] = explode('|', $linha, 3);
            $statusHttp = (int) $statusHttp;

            if ($statusHttp === 201) {
                $sucessos++;
            } elseif ($statusHttp === 422 && str_contains($body, 'Saldo insuficiente')) {
                $recusasSaldo++;
            } else {
                $statusInesperados[] = "worker $workerId: HTTP $statusHttp — $body";
            }
        }

        // Assertivas principais
        $this->assertEmpty(
            $statusInesperados,
            "Status HTTP inesperados:\n" . implode("\n", $statusInesperados)
        );
        $this->assertSame(
            1,
            $sucessos,
            "Esperava exatamente 1 saque bem-sucedido sob concorrência, obteve $sucessos. " .
            "Isto indica que o lock de linha (SELECT FOR UPDATE) não está funcionando."
        );
        $this->assertSame(
            self::N_PROCESSOS - 1,
            $recusasSaldo,
            'Esperava ' . (self::N_PROCESSOS - 1) . ' recusas por saldo insuficiente, ' .
            "obteve $recusasSaldo"
        );

        // Validação no banco — fonte da verdade
        $saldoFinal = (string) $this->pdo
            ->query("SELECT balance FROM account WHERE id = '$contaId'")
            ->fetchColumn();

        $saquesGravados = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM account_withdraw WHERE done = 1")
            ->fetchColumn();

        $this->assertGreaterThanOrEqual(
            0,
            (float) $saldoFinal,
            "SALDO NEGATIVO detectado ($saldoFinal). Bug crítico de concorrência."
        );

        $saldoEsperado = bcsub(self::SALDO_INICIAL, self::VALOR_SAQUE, 2);
        $this->assertSame(
            $saldoEsperado,
            number_format((float) $saldoFinal, 2, '.', ''),
            "Saldo final inconsistente: esperado R\$ $saldoEsperado, obteve R\$ $saldoFinal"
        );

        $this->assertSame(
            1,
            $saquesGravados,
            "Esperava 1 saque registrado no banco, há $saquesGravados " .
            '(múltiplos saques registrados sob concorrência indicam falha de atomicidade)'
        );
    }

    /**
     * Executado em cada processo filho. Faz a requisição e grava o resultado
     * em arquivo compartilhado (com lock para evitar corrupção).
     */
    private function dispararSaqueComoFilho(
        int $workerId,
        string $url,
        string $body,
        string $arquivoResultados
    ): void {
        // Pequeno offset para aumentar a chance dos requests baterem juntos
        usleep(100_000);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => self::TIMEOUT_CURL,
        ]);
        $resposta = curl_exec($ch);
        $statusHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            $resposta = "ERRO_CURL: $erroCurl";
            $statusHttp = 0;
        }

        // Comprime o body em uma linha (sem quebras) para o log
        $bodyLog = str_replace(["\n", "\r", '|'], [' ', ' ', ';'], (string) $resposta);

        file_put_contents(
            $arquivoResultados,
            "$workerId|$statusHttp|$bodyLog\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
