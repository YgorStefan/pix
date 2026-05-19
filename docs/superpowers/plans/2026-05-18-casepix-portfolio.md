# CasePix — Portfolio com CRUD e Demo Interativo

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transformar o case técnico em um portfólio com dashboard interativo acessível em `casepix.ygorstefan.com`, onde qualquer pessoa pode criar contas, gerenciar saldos e executar saques PIX via interface web sem precisar de Postman.

**Architecture:** nginx na porta 80 serve `public/index.html` e faz proxy de `/account/*` para o Hyperf (porta 9501). O frontend (HTML/CSS/JS vanilla, estilo terminal hacker) se comunica com a API via `fetch()`. Novos endpoints CRUD de contas e histórico de saques são adicionados ao Hyperf seguindo os padrões existentes (anotações, repositórios, application services, DI).

**Tech Stack:** PHP 8.2 + Hyperf 3.1, HTML/CSS/JS vanilla, nginx:alpine, MySQL 8.0, Docker Compose, PHPUnit 10 + Mockery.

---

## Mapa de arquivos

| Status | Arquivo | O que muda |
|--------|---------|-----------|
| `~` | `app/Domain/Saque/SaquePix.php` | Adiciona cpf, cnpj, telefone, aleatoria a TIPOS_VALIDOS |
| `~` | `app/Domain/Conta/Conta.php` | Adiciona método `alterarSaldo()` |
| `~` | `app/Domain/Conta/ContaRepositorio.php` | Adiciona `criar()`, `listar()`, `excluir()` |
| `~` | `app/Domain/Saque/SaqueRepositorio.php` | Adiciona `listarPorConta()`, `contarPorConta()` |
| `+` | `app/Domain/Conta/Exception/ContaPossuiSaquesException.php` | Nova exceção para 409 |
| `~` | `app/Infrastructure/Persistencia/ContaRepositorioMySQL.php` | Implementa os novos métodos |
| `~` | `app/Infrastructure/Persistencia/SaqueRepositorioMySQL.php` | Implementa os novos métodos |
| `+` | `app/Application/Conta/CriarConta.php` | Novo use case |
| `+` | `app/Application/Conta/AtualizarSaldo.php` | Novo use case |
| `+` | `app/Application/Conta/ExcluirConta.php` | Novo use case com verificação de saques |
| `+` | `app/Application/Saque/ListarSaquesDaConta.php` | Novo use case |
| `~` | `app/Infrastructure/Http/ContaController.php` | Adiciona 6 novos endpoints CRUD |
| `~` | `config/routes.php` | Remove entrada duplicada do withdraw; adiciona rotas CRUD |
| `+` | `docker/nginx/Dockerfile` | Imagem nginx:alpine |
| `+` | `docker/nginx/nginx.conf` | Proxy `/account/*` → Hyperf; `/` → index.html |
| `~` | `docker-compose.yml` | Adiciona serviço nginx na porta 80 |
| `+` | `public/index.html` | Dashboard frontend completo |
| `~` | `README.md` | Seção de deploy no servidor |
| `~` | `tests/Unit/Domain/SaquePixTest.php` | Atualiza tipos inválidos |
| `~` | `tests/Unit/Domain/ContaTest.php` | Adiciona teste para alterarSaldo |
| `~` | `tests/Unit/Application/ProcessarSaqueTest.php` | Corrige tipo inválido (telefone → bitcoin) |
| `~` | `tests/Unit/Application/AgendarSaqueTest.php` | Corrige tipo inválido (telefone → bitcoin) |
| `+` | `tests/Unit/Application/CriarContaTest.php` | Testes do novo use case |
| `+` | `tests/Unit/Application/AtualizarSaldoTest.php` | Testes do novo use case |
| `+` | `tests/Unit/Application/ExcluirContaTest.php` | Testes do novo use case |
| `+` | `tests/Unit/Application/ListarSaquesDaContaTest.php` | Testes do novo use case |

---

## Task 1: Expandir tipos PIX suportados

**Files:**
- Modify: `app/Domain/Saque/SaquePix.php`
- Modify: `app/Infrastructure/Http/ContaController.php` (método `validar`)
- Modify: `tests/Unit/Domain/SaquePixTest.php`
- Modify: `tests/Unit/Application/ProcessarSaqueTest.php`
- Modify: `tests/Unit/Application/AgendarSaqueTest.php`

- [ ] **Step 1: Atualizar SaquePixTest — corrigir testes que ficarão errados**

Os testes `testTipoInvalidoLancaExcecao`, `testOutrosTiposInvalidosLancamExcecao` e `testMensagemDeErroContemTipoInvalido` usam tipos que passarão a ser válidos. Substituir com tipos nunca suportados (`bitcoin`, `ted`, `transferencia`). Adicionar testes para os novos tipos válidos.

Substituir **todo** `tests/Unit/Domain/SaquePixTest.php`:

```php
<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Saque\SaquePix;
use App\Domain\Saque\Exception\TipoPixInvalidoException;
use PHPUnit\Framework\TestCase;

class SaquePixTest extends TestCase
{
    public function testCriaComEmailValido(): void
    {
        $pix = new SaquePix('email', 'fulano@email.com');
        $this->assertSame('PIX', $pix->obterTipo());
        $this->assertSame('email', $pix->tipo());
        $this->assertSame('fulano@email.com', $pix->chave());
    }

    public function testCriaComTodosOsTiposValidos(): void
    {
        $casos = [
            ['cpf',       '12345678900'],
            ['cnpj',      '12345678000190'],
            ['telefone',  '+5511999999999'],
            ['aleatoria', '550e8400-e29b-41d4-a716-446655440000'],
        ];
        foreach ($casos as [$tipo, $chave]) {
            $pix = new SaquePix($tipo, $chave);
            $this->assertSame('PIX', $pix->obterTipo());
            $this->assertSame($tipo, $pix->tipo());
        }
    }

    public function testTipoInvalidoLancaExcecao(): void
    {
        $this->expectException(TipoPixInvalidoException::class);
        new SaquePix('bitcoin', 'qualquer');
    }

    public function testOutrosTiposInvalidosLancamExcecao(): void
    {
        foreach (['ted', 'transferencia', 'debito', ''] as $tipo) {
            try {
                new SaquePix($tipo, 'qualquer');
                $this->fail("Esperava TipoPixInvalidoException para tipo '{$tipo}'");
            } catch (TipoPixInvalidoException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testMensagemDeErroContemTipoInvalido(): void
    {
        $this->expectException(TipoPixInvalidoException::class);
        $this->expectExceptionMessageMatches('/bitcoin/');
        new SaquePix('bitcoin', 'qualquer');
    }
}
```

- [ ] **Step 2: Rodar os testes (devem falhar — SaquePix ainda não aceita novos tipos)**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit --filter SaquePixTest
```

Esperado: falha em `testCriaComTodosOsTiposValidos`.

- [ ] **Step 3: Atualizar SaquePix — adicionar os novos tipos**

Substituir `app/Domain/Saque/SaquePix.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Saque;

use App\Domain\Saque\Exception\TipoPixInvalidoException;

class SaquePix implements MetodoDeSaque
{
    private const TIPOS_VALIDOS = ['email', 'cpf', 'cnpj', 'telefone', 'aleatoria'];

    public function __construct(
        private readonly string $tipo,
        private readonly string $chave
    ) {
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new TipoPixInvalidoException("Tipo PIX '{$tipo}' não é suportado. Tipos aceitos: " . implode(', ', self::TIPOS_VALIDOS));
        }
    }

    public function obterTipo(): string { return 'PIX'; }
    public function tipo(): string { return $this->tipo; }
    public function chave(): string { return $this->chave; }
}
```

- [ ] **Step 4: Corrigir ProcessarSaqueTest e AgendarSaqueTest — trocar `telefone` por `bitcoin`**

Em `tests/Unit/Application/ProcessarSaqueTest.php`, método `testTipoPixInvalidoLancaExcecaoAntesDeTransacionar`:
```php
// linha: $this->useCase->executar('conta-1', 'telefone', '11999999999', '100.00');
// substituir por:
$this->useCase->executar('conta-1', 'bitcoin', '1A2B3C', '100.00');
```

Em `tests/Unit/Application/AgendarSaqueTest.php`, método `testTipoPixInvalidoLancaExcecao`:
```php
// linha: $this->useCase->executar('conta-1', 'telefone', '11999999999', '100.00', $futuro);
// substituir por:
$this->useCase->executar('conta-1', 'bitcoin', '1A2B3C', '100.00', $futuro);
```

- [ ] **Step 5: Atualizar validação de chave PIX no ContaController**

No método `validar()` de `app/Infrastructure/Http/ContaController.php`, substituir o bloco de validação de PIX:

```php
// REMOVER:
if (!isset($body['pix']['type']) || $body['pix']['type'] !== 'email') {
    $erros[] = 'pix.type deve ser "email"';
}
if (!isset($body['pix']['key']) || !filter_var($body['pix']['key'], FILTER_VALIDATE_EMAIL)) {
    $erros[] = 'pix.key deve ser um email válido';
}

// INSERIR:
$tiposValidos = ['email', 'cpf', 'cnpj', 'telefone', 'aleatoria'];
$pixTipo = $body['pix']['type'] ?? null;
if (!in_array($pixTipo, $tiposValidos, true)) {
    $erros[] = 'pix.type deve ser um de: ' . implode(', ', $tiposValidos);
}
if (!isset($body['pix']['key'])) {
    $erros[] = 'pix.key é obrigatório';
} elseif ($pixTipo !== null && in_array($pixTipo, $tiposValidos, true)) {
    $erroChave = $this->validarChavePix($pixTipo, (string) $body['pix']['key']);
    if ($erroChave !== null) {
        $erros[] = $erroChave;
    }
}
```

Adicionar o método privado no final da classe (antes do `}`):

```php
private function validarChavePix(string $tipo, string $chave): ?string
{
    return match ($tipo) {
        'email'    => filter_var($chave, FILTER_VALIDATE_EMAIL) ? null
                      : 'pix.key deve ser um email válido (ex: usuario@email.com)',
        'cpf'      => preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$|^\d{11}$/', $chave) ? null
                      : 'pix.key CPF inválido. Use 000.000.000-00 ou 00000000000',
        'cnpj'     => preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$|^\d{14}$/', $chave) ? null
                      : 'pix.key CNPJ inválido. Use 00.000.000/0001-00 ou 00000000000000',
        'telefone' => preg_match('/^\+55\d{10,11}$/', $chave) ? null
                      : 'pix.key telefone inválido. Use +55XXXXXXXXXXX',
        'aleatoria'=> preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $chave) ? null
                      : 'pix.key chave aleatória deve ser UUID (ex: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)',
        default    => null,
    };
}
```

- [ ] **Step 6: Rodar suite de testes unitários completa**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit
```

Esperado: todos passam.

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Saque/SaquePix.php app/Infrastructure/Http/ContaController.php tests/Unit/Domain/SaquePixTest.php tests/Unit/Application/ProcessarSaqueTest.php tests/Unit/Application/AgendarSaqueTest.php
git commit -m "expande tipos PIX para cpf, cnpj, telefone e aleatoria"
```

---

## Task 2: Domínio — `Conta.alterarSaldo()` + exceção + ContaRepositorio

**Files:**
- Modify: `app/Domain/Conta/Conta.php`
- Modify: `app/Domain/Conta/ContaRepositorio.php`
- Create: `app/Domain/Conta/Exception/ContaPossuiSaquesException.php`
- Modify: `tests/Unit/Domain/ContaTest.php`

- [ ] **Step 1: Escrever teste para `alterarSaldo()`**

Adicionar ao final de `tests/Unit/Domain/ContaTest.php` (antes do `}`):

```php
public function testAlteraSaldoDiretamente(): void
{
    $this->conta->alterarSaldo(Dinheiro::deDecimal('999.99'));
    $this->assertSame('999.99', $this->conta->obterSaldo()->toDecimal());
}

public function testAlteraSaldoParaZero(): void
{
    $this->conta->alterarSaldo(Dinheiro::deDecimal('0.00'));
    $this->assertSame('0.00', $this->conta->obterSaldo()->toDecimal());
}
```

- [ ] **Step 2: Rodar (deve falhar)**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit --filter ContaTest
```

Esperado: FAIL — `alterarSaldo` não existe.

- [ ] **Step 3: Adicionar `alterarSaldo()` em `Conta`**

Adicionar após o método `deduzirSaldo()` em `app/Domain/Conta/Conta.php`:

```php
public function alterarSaldo(Dinheiro $novoSaldo): void
{
    $this->saldo = $novoSaldo;
}
```

- [ ] **Step 4: Criar `ContaPossuiSaquesException`**

Criar `app/Domain/Conta/Exception/ContaPossuiSaquesException.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Conta\Exception;

class ContaPossuiSaquesException extends \RuntimeException {}
```

- [ ] **Step 5: Expandir interface `ContaRepositorio`**

Substituir `app/Domain/Conta/ContaRepositorio.php`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Conta;

interface ContaRepositorio
{
    public function buscarPorId(string $id): Conta;
    public function buscarPorIdComLock(string $id): Conta;
    public function criar(Conta $conta): void;
    /** @return Conta[] */
    public function listar(): array;
    public function salvar(Conta $conta): void;
    public function excluir(string $id): void;
}
```

- [ ] **Step 6: Rodar testes unitários**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit
```

Esperado: todos passam (a interface só adiciona métodos, a implementação MySQL ainda não foi atualizada mas não é testada unitariamente).

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Conta/Conta.php app/Domain/Conta/ContaRepositorio.php app/Domain/Conta/Exception/ContaPossuiSaquesException.php tests/Unit/Domain/ContaTest.php
git commit -m "adiciona alterarSaldo no domínio e expande ContaRepositorio"
```

---

## Task 3: ContaRepositorioMySQL — implementar novos métodos

**Files:**
- Modify: `app/Infrastructure/Persistencia/ContaRepositorioMySQL.php`

- [ ] **Step 1: Implementar `criar()`, `listar()`, `excluir()` no repositório MySQL**

Substituir `app/Infrastructure/Persistencia/ContaRepositorioMySQL.php`:

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistencia;

use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Saque\Dinheiro;

class ContaRepositorioMySQL implements ContaRepositorio
{
    public function buscarPorId(string $id): Conta
    {
        $model = ContaModel::find($id);
        if ($model === null) {
            throw new ContaNaoEncontradaException("Conta '{$id}' não encontrada.");
        }
        return $this->paraEntidade($model);
    }

    public function buscarPorIdComLock(string $id): Conta
    {
        $model = ContaModel::query()->where('id', $id)->lockForUpdate()->first();
        if ($model === null) {
            throw new ContaNaoEncontradaException("Conta '{$id}' não encontrada.");
        }
        return $this->paraEntidade($model);
    }

    public function criar(Conta $conta): void
    {
        ContaModel::create([
            'id'      => $conta->id(),
            'name'    => $conta->nome(),
            'balance' => $conta->obterSaldo()->toDecimal(),
        ]);
    }

    public function listar(): array
    {
        return ContaModel::query()
            ->orderBy('name')
            ->get()
            ->map(fn(ContaModel $m) => $this->paraEntidade($m))
            ->all();
    }

    public function salvar(Conta $conta): void
    {
        ContaModel::query()->where('id', $conta->id())->update([
            'balance' => $conta->obterSaldo()->toDecimal(),
        ]);
    }

    public function excluir(string $id): void
    {
        ContaModel::query()->where('id', $id)->delete();
    }

    private function paraEntidade(ContaModel $model): Conta
    {
        return new Conta(
            $model->id,
            $model->name,
            Dinheiro::deDecimal((string) $model->balance)
        );
    }
}
```

- [ ] **Step 2: Rodar testes unitários**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit
```

Esperado: todos passam.

- [ ] **Step 3: Commit**

```bash
git add app/Infrastructure/Persistencia/ContaRepositorioMySQL.php
git commit -m "implementa criar, listar e excluir no ContaRepositorioMySQL"
```

---

## Task 4: SaqueRepositorio — `listarPorConta()` e `contarPorConta()`

**Files:**
- Modify: `app/Domain/Saque/SaqueRepositorio.php`
- Modify: `app/Infrastructure/Persistencia/SaqueRepositorioMySQL.php`

- [ ] **Step 1: Expandir interface `SaqueRepositorio`**

Adicionar ao final da interface em `app/Domain/Saque/SaqueRepositorio.php` (antes do `}`):

```php
/** @return array[] array de arrays com dados do saque e PIX */
public function listarPorConta(string $contaId): array;

public function contarPorConta(string $contaId): int;
```

- [ ] **Step 2: Implementar no repositório MySQL**

Adicionar ao final de `app/Infrastructure/Persistencia/SaqueRepositorioMySQL.php` (antes do `}`). Garantir que os imports de `DateTimeImmutable` e `DateTimeZone` já existem no topo (existem):

```php
public function listarPorConta(string $contaId): array
{
    return SaqueModel::query()
        ->where('account_id', $contaId)
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function (SaqueModel $m) {
            $pix = SaquePixModel::find($m->id);
            $scheduledFor = $m->scheduled_for !== null
                ? (new DateTimeImmutable($m->scheduled_for, new DateTimeZone('UTC')))
                    ->setTimezone(new DateTimeZone('America/Sao_Paulo'))
                    ->format('Y-m-d H:i:s')
                : null;
            $createdAt = (new DateTimeImmutable($m->created_at, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('America/Sao_Paulo'))
                ->format('Y-m-d H:i:s');
            return [
                'id'           => $m->id,
                'amount'       => number_format((float) $m->amount, 2, '.', ''),
                'method'       => $m->method,
                'pix'          => $pix ? ['type' => $pix->type, 'key' => $pix->key] : null,
                'scheduled'    => (bool) $m->scheduled,
                'scheduled_for'=> $scheduledFor,
                'done'         => (bool) $m->done,
                'error'        => (bool) $m->error,
                'error_reason' => $m->error_reason,
                'created_at'   => $createdAt,
            ];
        })
        ->all();
}

public function contarPorConta(string $contaId): int
{
    return SaqueModel::query()->where('account_id', $contaId)->count();
}
```

- [ ] **Step 3: Rodar testes unitários**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit
```

Esperado: todos passam.

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Saque/SaqueRepositorio.php app/Infrastructure/Persistencia/SaqueRepositorioMySQL.php
git commit -m "adiciona listarPorConta e contarPorConta ao SaqueRepositorio"
```

---

## Task 5: Application services — CRUD de contas

**Files:**
- Create: `app/Application/Conta/CriarConta.php`
- Create: `app/Application/Conta/AtualizarSaldo.php`
- Create: `app/Application/Conta/ExcluirConta.php`
- Create: `tests/Unit/Application/CriarContaTest.php`
- Create: `tests/Unit/Application/AtualizarSaldoTest.php`
- Create: `tests/Unit/Application/ExcluirContaTest.php`

- [ ] **Step 1: Escrever CriarContaTest**

Criar `tests/Unit/Application/CriarContaTest.php`:

```php
<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Conta\CriarConta;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Saque\Exception\ValorInvalidoException;
use Mockery;
use PHPUnit\Framework\TestCase;

class CriarContaTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private CriarConta $useCase;

    protected function setUp(): void
    {
        $this->contaRepo = Mockery::mock(ContaRepositorio::class);
        $this->useCase   = new CriarConta($this->contaRepo);
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testCriaContaComUUIDEPersiste(): void
    {
        $this->contaRepo->expects('criar')->with(Mockery::type(Conta::class));
        $conta = $this->useCase->executar('Ygor', '500.00');
        $this->assertSame('Ygor', $conta->nome());
        $this->assertSame('500.00', $conta->obterSaldo()->toDecimal());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $conta->id());
    }

    public function testCriaComSaldoZero(): void
    {
        $this->contaRepo->expects('criar');
        $conta = $this->useCase->executar('Teste', '0.00');
        $this->assertSame('0.00', $conta->obterSaldo()->toDecimal());
    }

    public function testSaldoNegativoLancaExcecao(): void
    {
        $this->contaRepo->shouldNotReceive('criar');
        $this->expectException(ValorInvalidoException::class);
        $this->useCase->executar('Teste', '-10.00');
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit --filter CriarContaTest
```

Esperado: FAIL — classe não existe.

- [ ] **Step 3: Implementar CriarConta**

Criar `app/Application/Conta/CriarConta.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Conta;

use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Saque\Dinheiro;
use Ramsey\Uuid\Uuid;

class CriarConta
{
    public function __construct(private readonly ContaRepositorio $contaRepositorio) {}

    public function executar(string $nome, string $saldo): Conta
    {
        $conta = new Conta(Uuid::uuid4()->toString(), $nome, Dinheiro::deDecimal($saldo));
        $this->contaRepositorio->criar($conta);
        return $conta;
    }
}
```

- [ ] **Step 4: Rodar CriarContaTest (deve passar)**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit --filter CriarContaTest
```

Esperado: PASS.

- [ ] **Step 5: Escrever AtualizarSaldoTest**

Criar `tests/Unit/Application/AtualizarSaldoTest.php`:

```php
<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Conta\AtualizarSaldo;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Saque\Dinheiro;
use App\Domain\Saque\Exception\ValorInvalidoException;
use Mockery;
use PHPUnit\Framework\TestCase;

class AtualizarSaldoTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private AtualizarSaldo $useCase;

    protected function setUp(): void
    {
        $this->contaRepo = Mockery::mock(ContaRepositorio::class);
        $this->useCase   = new AtualizarSaldo($this->contaRepo);
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testAtualizaSaldoComSucesso(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('100.00'));
        $this->contaRepo->expects('buscarPorId')->with('id-1')->andReturn($conta);
        $this->contaRepo->expects('salvar')->with(Mockery::type(Conta::class));
        $resultado = $this->useCase->executar('id-1', '300.00');
        $this->assertSame('300.00', $resultado->obterSaldo()->toDecimal());
    }

    public function testSaldoNegativoLancaExcecao(): void
    {
        $this->contaRepo->shouldNotReceive('buscarPorId');
        $this->expectException(ValorInvalidoException::class);
        $this->useCase->executar('id-1', '-1.00');
    }

    public function testContaNaoEncontradaLancaExcecao(): void
    {
        $this->contaRepo->expects('buscarPorId')->andThrow(new ContaNaoEncontradaException('não encontrada'));
        $this->expectException(ContaNaoEncontradaException::class);
        $this->useCase->executar('id-inexistente', '100.00');
    }
}
```

- [ ] **Step 6: Implementar AtualizarSaldo**

Criar `app/Application/Conta/AtualizarSaldo.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Conta;

use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Saque\Dinheiro;

class AtualizarSaldo
{
    public function __construct(private readonly ContaRepositorio $contaRepositorio) {}

    public function executar(string $id, string $novoSaldo): Conta
    {
        $dinheiro = Dinheiro::deDecimal($novoSaldo);
        $conta = $this->contaRepositorio->buscarPorId($id);
        $conta->alterarSaldo($dinheiro);
        $this->contaRepositorio->salvar($conta);
        return $conta;
    }
}
```

- [ ] **Step 7: Escrever ExcluirContaTest**

Criar `tests/Unit/Application/ExcluirContaTest.php`:

```php
<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Conta\ExcluirConta;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Conta\Exception\ContaPossuiSaquesException;
use App\Domain\Saque\Dinheiro;
use App\Domain\Saque\SaqueRepositorio;
use Mockery;
use PHPUnit\Framework\TestCase;

class ExcluirContaTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private SaqueRepositorio $saqueRepo;
    private ExcluirConta $useCase;

    protected function setUp(): void
    {
        $this->contaRepo = Mockery::mock(ContaRepositorio::class);
        $this->saqueRepo = Mockery::mock(SaqueRepositorio::class);
        $this->useCase   = new ExcluirConta($this->contaRepo, $this->saqueRepo);
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testExcluiContaSemSaques(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('0.00'));
        $this->contaRepo->expects('buscarPorId')->with('id-1')->andReturn($conta);
        $this->saqueRepo->expects('contarPorConta')->with('id-1')->andReturn(0);
        $this->contaRepo->expects('excluir')->with('id-1');
        $this->useCase->executar('id-1');
    }

    public function testLancaExcecaoSeContaPossuiSaques(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('0.00'));
        $this->contaRepo->expects('buscarPorId')->andReturn($conta);
        $this->saqueRepo->expects('contarPorConta')->andReturn(3);
        $this->contaRepo->shouldNotReceive('excluir');
        $this->expectException(ContaPossuiSaquesException::class);
        $this->useCase->executar('id-1');
    }

    public function testContaNaoEncontradaLancaExcecao(): void
    {
        $this->contaRepo->expects('buscarPorId')->andThrow(new ContaNaoEncontradaException('não encontrada'));
        $this->expectException(ContaNaoEncontradaException::class);
        $this->useCase->executar('id-inexistente');
    }
}
```

- [ ] **Step 8: Implementar ExcluirConta**

Criar `app/Application/Conta/ExcluirConta.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Conta;

use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaPossuiSaquesException;
use App\Domain\Saque\SaqueRepositorio;

class ExcluirConta
{
    public function __construct(
        private readonly ContaRepositorio $contaRepositorio,
        private readonly SaqueRepositorio $saqueRepositorio
    ) {}

    public function executar(string $id): void
    {
        $this->contaRepositorio->buscarPorId($id);

        if ($this->saqueRepositorio->contarPorConta($id) > 0) {
            throw new ContaPossuiSaquesException("Conta '{$id}' possui saques e não pode ser excluída.");
        }

        $this->contaRepositorio->excluir($id);
    }
}
```

- [ ] **Step 9: Rodar todos os novos testes**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit --filter "CriarContaTest|AtualizarSaldoTest|ExcluirContaTest"
```

Esperado: todos passam.

- [ ] **Step 10: Rodar suite unitária completa**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit
```

Esperado: todos passam.

- [ ] **Step 11: Commit**

```bash
git add app/Application/Conta/ tests/Unit/Application/CriarContaTest.php tests/Unit/Application/AtualizarSaldoTest.php tests/Unit/Application/ExcluirContaTest.php
git commit -m "adiciona application services CriarConta, AtualizarSaldo e ExcluirConta"
```

---

## Task 6: Application service `ListarSaquesDaConta`

**Files:**
- Create: `app/Application/Saque/ListarSaquesDaConta.php`
- Create: `tests/Unit/Application/ListarSaquesDaContaTest.php`

- [ ] **Step 1: Escrever ListarSaquesDaContaTest**

Criar `tests/Unit/Application/ListarSaquesDaContaTest.php`:

```php
<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Saque\ListarSaquesDaConta;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Saque\Dinheiro;
use App\Domain\Saque\SaqueRepositorio;
use Mockery;
use PHPUnit\Framework\TestCase;

class ListarSaquesDaContaTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private SaqueRepositorio $saqueRepo;
    private ListarSaquesDaConta $useCase;

    protected function setUp(): void
    {
        $this->contaRepo = Mockery::mock(ContaRepositorio::class);
        $this->saqueRepo = Mockery::mock(SaqueRepositorio::class);
        $this->useCase   = new ListarSaquesDaConta($this->contaRepo, $this->saqueRepo);
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testRetornaSaquesFormatados(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('500.00'));
        $this->contaRepo->expects('buscarPorId')->with('id-1')->andReturn($conta);
        $saques = [
            ['id' => 'saque-1', 'amount' => '150.00', 'done' => true, 'error' => false],
            ['id' => 'saque-2', 'amount' => '50.00',  'done' => false, 'error' => false],
        ];
        $this->saqueRepo->expects('listarPorConta')->with('id-1')->andReturn($saques);
        $resultado = $this->useCase->executar('id-1');
        $this->assertCount(2, $resultado);
        $this->assertSame('150.00', $resultado[0]['amount']);
    }

    public function testRetornaListaVaziaSeNaoHaSaques(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('500.00'));
        $this->contaRepo->expects('buscarPorId')->andReturn($conta);
        $this->saqueRepo->expects('listarPorConta')->andReturn([]);
        $this->assertSame([], $this->useCase->executar('id-1'));
    }

    public function testContaNaoEncontradaLancaExcecao(): void
    {
        $this->contaRepo->expects('buscarPorId')->andThrow(new ContaNaoEncontradaException('não encontrada'));
        $this->saqueRepo->shouldNotReceive('listarPorConta');
        $this->expectException(ContaNaoEncontradaException::class);
        $this->useCase->executar('id-inexistente');
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit --filter ListarSaquesDaContaTest
```

Esperado: FAIL — classe não existe.

- [ ] **Step 3: Implementar ListarSaquesDaConta**

Criar `app/Application/Saque/ListarSaquesDaConta.php`:

```php
<?php
declare(strict_types=1);

namespace App\Application\Saque;

use App\Domain\Conta\ContaRepositorio;
use App\Domain\Saque\SaqueRepositorio;

class ListarSaquesDaConta
{
    public function __construct(
        private readonly ContaRepositorio $contaRepositorio,
        private readonly SaqueRepositorio $saqueRepositorio
    ) {}

    public function executar(string $contaId): array
    {
        $this->contaRepositorio->buscarPorId($contaId);
        return $this->saqueRepositorio->listarPorConta($contaId);
    }
}
```

- [ ] **Step 4: Rodar suite unitária completa**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit
```

Esperado: todos passam.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Saque/ListarSaquesDaConta.php tests/Unit/Application/ListarSaquesDaContaTest.php
git commit -m "adiciona ListarSaquesDaConta"
```

---

## Task 7: Expandir `ContaController` com rotas CRUD

**Files:**
- Modify: `app/Infrastructure/Http/ContaController.php`
- Modify: `config/routes.php`

- [ ] **Step 1: Atualizar `config/routes.php`**

Substituir `config/routes.php`:

```php
<?php
declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');
```

A rota de saque agora é registrada via annotation no controller.

- [ ] **Step 2: Substituir `ContaController` completo**

Substituir `app/Infrastructure/Http/ContaController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Conta\{AtualizarSaldo, CriarConta, ExcluirConta};
use App\Application\Saque\{AgendarSaque, ListarSaquesDaConta, ProcessarSaque};
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\{ContaNaoEncontradaException, ContaPossuiSaquesException};
use App\Domain\Conta\Exception\SaldoInsuficienteException;
use App\Domain\Saque\Exception\{AgendamentoNoPassadoException, TipoPixInvalidoException};
use Hyperf\HttpServer\Annotation\{Controller, DeleteMapping, GetMapping, PatchMapping, PostMapping};
use Hyperf\HttpServer\Contract\{RequestInterface, ResponseInterface};

#[Controller(prefix: '/account')]
class ContaController
{
    public function __construct(
        private readonly ProcessarSaque $processarSaque,
        private readonly AgendarSaque $agendarSaque,
        private readonly CriarConta $criarConta,
        private readonly AtualizarSaldo $atualizarSaldo,
        private readonly ExcluirConta $excluirConta,
        private readonly ListarSaquesDaConta $listarSaquesDaConta,
        private readonly ContaRepositorio $contaRepositorio
    ) {}

    #[PostMapping(path: '')]
    public function criar(RequestInterface $request, ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $body = $request->getParsedBody();
        $erros = $this->validarCriacao($body);
        if (!empty($erros)) {
            return $response->json(['erros' => $erros])->withStatus(422);
        }

        try {
            $conta = $this->criarConta->executar(trim($body['name']), (string) $body['balance']);
            return $response->json([
                'id'      => $conta->id(),
                'name'    => $conta->nome(),
                'balance' => $conta->obterSaldo()->toDecimal(),
            ])->withStatus(201);
        } catch (\Throwable) {
            return $response->json(['message' => 'Erro interno. Tente novamente.'])->withStatus(500);
        }
    }

    #[GetMapping(path: '')]
    public function listar(ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $contas = $this->contaRepositorio->listar();
        return $response->json(array_map(fn($c) => [
            'id'      => $c->id(),
            'name'    => $c->nome(),
            'balance' => $c->obterSaldo()->toDecimal(),
        ], $contas));
    }

    #[GetMapping(path: '/{id}')]
    public function obter(string $id, ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        try {
            $conta = $this->contaRepositorio->buscarPorId($id);
            return $response->json([
                'id'      => $conta->id(),
                'name'    => $conta->nome(),
                'balance' => $conta->obterSaldo()->toDecimal(),
            ]);
        } catch (ContaNaoEncontradaException) {
            return $response->json(['message' => 'Conta não encontrada.'])->withStatus(404);
        }
    }

    #[PatchMapping(path: '/{id}/balance')]
    public function atualizarSaldo(string $id, RequestInterface $request, ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $body = $request->getParsedBody();
        $erros = $this->validarSaldo($body);
        if (!empty($erros)) {
            return $response->json(['erros' => $erros])->withStatus(422);
        }

        try {
            $conta = $this->atualizarSaldo->executar($id, (string) $body['balance']);
            return $response->json([
                'id'      => $conta->id(),
                'name'    => $conta->nome(),
                'balance' => $conta->obterSaldo()->toDecimal(),
            ]);
        } catch (ContaNaoEncontradaException) {
            return $response->json(['message' => 'Conta não encontrada.'])->withStatus(404);
        } catch (\Throwable) {
            return $response->json(['message' => 'Erro interno. Tente novamente.'])->withStatus(500);
        }
    }

    #[DeleteMapping(path: '/{id}')]
    public function excluir(string $id, ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        try {
            $this->excluirConta->executar($id);
            return $response->withStatus(204);
        } catch (ContaNaoEncontradaException) {
            return $response->json(['message' => 'Conta não encontrada.'])->withStatus(404);
        } catch (ContaPossuiSaquesException) {
            return $response->json(['message' => 'Conta possui saques e não pode ser excluída.'])->withStatus(409);
        } catch (\Throwable) {
            return $response->json(['message' => 'Erro interno. Tente novamente.'])->withStatus(500);
        }
    }

    #[GetMapping(path: '/{id}/withdrawals')]
    public function listarSaques(string $id, ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        try {
            return $response->json($this->listarSaquesDaConta->executar($id));
        } catch (ContaNaoEncontradaException) {
            return $response->json(['message' => 'Conta não encontrada.'])->withStatus(404);
        }
    }

    #[PostMapping(path: '/{id}/balance/withdraw')]
    public function sacar(string $id, RequestInterface $request, ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $body = $request->getParsedBody();
        $erros = $this->validarSaque($body, $id);
        if (!empty($erros)) {
            return $response->json(['erros' => $erros])->withStatus(422);
        }

        try {
            $pixTipo  = $body['pix']['type'];
            $pixChave = $body['pix']['key'];
            $valor    = (string) $body['amount'];
            $schedule = $body['schedule'] ?? null;

            if ($schedule === null) {
                $saque = $this->processarSaque->executar($id, $pixTipo, $pixChave, $valor);
            } else {
                $saque = $this->agendarSaque->executar($id, $pixTipo, $pixChave, $valor, $schedule);
            }

            return $response->json([
                'id'            => $saque->id(),
                'account_id'    => $saque->contaId(),
                'method'        => 'PIX',
                'amount'        => $saque->valor()->toDecimal(),
                'scheduled'     => $saque->estaAgendado(),
                'scheduled_for' => $saque->agendadoPara()?->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i:s'),
                'done'          => $saque->concluido(),
                'error'         => $saque->erro(),
                'error_reason'  => $saque->motivoErro(),
                'pix'           => ['type' => $pixTipo, 'key' => $pixChave],
            ])->withStatus(201);

        } catch (ContaNaoEncontradaException) {
            return $response->json(['message' => 'Conta não encontrada.'])->withStatus(404);
        } catch (SaldoInsuficienteException) {
            return $response->json(['message' => 'Saldo insuficiente.'])->withStatus(422);
        } catch (AgendamentoNoPassadoException) {
            return $response->json(['message' => 'Data de agendamento não pode ser no passado.'])->withStatus(422);
        } catch (TipoPixInvalidoException) {
            return $response->json(['message' => 'Tipo de chave PIX inválido.'])->withStatus(422);
        } catch (\Throwable) {
            return $response->json(['message' => 'Erro interno. Tente novamente.'])->withStatus(500);
        }
    }

    private function validarCriacao(mixed $body): array
    {
        $erros = [];
        if (!is_array($body)) {
            return ['body deve ser um JSON válido'];
        }
        if (empty(trim($body['name'] ?? ''))) {
            $erros[] = 'name é obrigatório e não pode ser vazio';
        }
        $erros = array_merge($erros, $this->validarSaldo($body));
        return $erros;
    }

    private function validarSaldo(mixed $body): array
    {
        if (!is_array($body)) {
            return ['body deve ser um JSON válido'];
        }
        $balance = $body['balance'] ?? null;
        if ($balance === null
            || (!is_int($balance) && !is_float($balance) && !preg_match('/^\d+(\.\d{1,2})?$/', (string) $balance))
            || (float) $balance < 0
        ) {
            return ['balance deve ser um número >= 0 com no máximo 2 casas decimais (ex: 500.00)'];
        }
        return [];
    }

    private function validarSaque(mixed $body, string $contaId): array
    {
        $erros = [];
        if (!is_array($body)) {
            return ['body deve ser um JSON válido'];
        }
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $contaId)) {
            $erros[] = 'accountId deve ser um UUID válido';
        }
        if (($body['method'] ?? '') !== 'PIX') {
            $erros[] = 'method deve ser "PIX"';
        }
        $tiposValidos = ['email', 'cpf', 'cnpj', 'telefone', 'aleatoria'];
        $pixTipo = $body['pix']['type'] ?? null;
        if (!in_array($pixTipo, $tiposValidos, true)) {
            $erros[] = 'pix.type deve ser um de: ' . implode(', ', $tiposValidos);
        }
        if (!isset($body['pix']['key'])) {
            $erros[] = 'pix.key é obrigatório';
        } elseif ($pixTipo !== null && in_array($pixTipo, $tiposValidos, true)) {
            $erroChave = $this->validarChavePix($pixTipo, (string) $body['pix']['key']);
            if ($erroChave !== null) {
                $erros[] = $erroChave;
            }
        }
        $amount = $body['amount'] ?? null;
        if ($amount === null
            || (!is_int($amount) && !is_float($amount) && !preg_match('/^\d+(\.\d{1,2})?$/', (string) $amount))
            || (is_float($amount) && round($amount, 2) !== (float) $amount)
            || (float) $amount <= 0
        ) {
            $erros[] = 'amount deve ser um número maior que zero com no máximo 2 casas decimais (ex: 150.75)';
        }
        if (array_key_exists('schedule', $body) && $body['schedule'] !== null) {
            if (!is_string($body['schedule'])) {
                $erros[] = 'schedule deve estar no formato YYYY-MM-DD HH:mm ou ser null';
            } else {
                $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $body['schedule'], new \DateTimeZone('America/Sao_Paulo'));
                $errosDt = \DateTimeImmutable::getLastErrors();
                if ($dt === false || ($errosDt && (!empty($errosDt['warnings']) || !empty($errosDt['errors'])))) {
                    $erros[] = 'schedule deve estar no formato YYYY-MM-DD HH:mm ou ser null';
                }
            }
        }
        return $erros;
    }

    private function validarChavePix(string $tipo, string $chave): ?string
    {
        return match ($tipo) {
            'email'    => filter_var($chave, FILTER_VALIDATE_EMAIL) ? null
                          : 'pix.key deve ser um email válido (ex: usuario@email.com)',
            'cpf'      => preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$|^\d{11}$/', $chave) ? null
                          : 'pix.key CPF inválido. Use 000.000.000-00 ou 00000000000',
            'cnpj'     => preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$|^\d{14}$/', $chave) ? null
                          : 'pix.key CNPJ inválido. Use 00.000.000/0001-00 ou 00000000000000',
            'telefone' => preg_match('/^\+55\d{10,11}$/', $chave) ? null
                          : 'pix.key telefone inválido. Use +55XXXXXXXXXXX',
            'aleatoria'=> preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $chave) ? null
                          : 'pix.key chave aleatória deve ser UUID (ex: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)',
            default    => null,
        };
    }
}
```

- [ ] **Step 3: Rodar testes unitários**

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit
```

Esperado: todos passam (os testes unitários não exercem o controller diretamente).

- [ ] **Step 4: Verificar se o container sobe sem erros de sintaxe**

```bash
docker compose restart app && sleep 5 && docker compose logs app --tail=20
```

Esperado: logs sem PHP Fatal/Parse errors.

- [ ] **Step 5: Smoke test dos novos endpoints via curl**

```bash
# Criar conta
curl -s -X POST http://localhost:9501/account \
  -H "Content-Type: application/json" \
  -d '{"name":"Teste","balance":500}' | cat

# Listar contas
curl -s http://localhost:9501/account | cat
```

Esperado: JSON com a conta criada e a listagem.

- [ ] **Step 6: Commit**

```bash
git add app/Infrastructure/Http/ContaController.php config/routes.php
git commit -m "adiciona endpoints CRUD de contas e histórico de saques"
```

---

## Task 8: Infraestrutura nginx

**Files:**
- Create: `docker/nginx/Dockerfile`
- Create: `docker/nginx/nginx.conf`
- Modify: `docker-compose.yml`

- [ ] **Step 1: Criar `docker/nginx/Dockerfile`**

```dockerfile
FROM nginx:alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
```

- [ ] **Step 2: Criar `docker/nginx/nginx.conf`**

```nginx
server {
    listen 80;

    location ^~ /account {
        proxy_pass         http://app:9501;
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_read_timeout 30s;
    }

    location / {
        root  /usr/share/nginx/html;
        index index.html;
        try_files $uri $uri/ /index.html;
    }
}
```

- [ ] **Step 3: Adicionar serviço nginx no `docker-compose.yml`**

Adicionar após o serviço `mailhog`:

```yaml
  nginx:
    build: ./docker/nginx
    ports:
      - "80:80"
    volumes:
      - ./public:/usr/share/nginx/html:ro
    depends_on:
      app:
        condition: service_started
    restart: unless-stopped
```

- [ ] **Step 4: Criar diretório `public/` com placeholder**

```bash
mkdir -p public
echo "ok" > public/index.html
```

- [ ] **Step 5: Subir e verificar**

```bash
docker compose up -d --build nginx
curl -s http://localhost/account
```

Esperado: JSON da listagem de contas (proxy funcionando). `curl -s http://localhost/` deve retornar `ok`.

- [ ] **Step 6: Commit**

```bash
git add docker/nginx/ docker-compose.yml public/
git commit -m "adiciona nginx como proxy e servidor de arquivos estáticos"
```

---

## Task 9: Frontend — `public/index.html`

**Files:**
- Create: `public/index.html`

- [ ] **Step 1: Criar o arquivo completo**

Substituir `public/index.html` pelo conteúdo abaixo. Salvar via Write tool — não usar cat/heredoc.

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CasePix</title>
  <style>
    :root {
      --bg: #020617; --bg-alt: #0f172a; --bg-card: #1e293b;
      --border: #334155; --border-dim: #1e293b;
      --text: #94a3b8; --text-bright: #f1f5f9; --text-dim: #475569;
      --green: #22c55e; --green-bg: #0a1f0f; --green-border: #166534;
      --red: #ef4444; --red-bg: #1c0a0a;
      --amber: #f59e0b; --amber-bg: #1c1203;
      --blue: #60a5fa;
      --font: 'Fira Code', 'Courier New', monospace;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }
    body { background: var(--bg); color: var(--text); font-family: var(--font); font-size: 13px; line-height: 1.5; overflow: hidden; }
    input, button, select { font-family: var(--font); font-size: 12px; }
    a { color: var(--text-dim); text-decoration: none; }
    a:hover { color: var(--text); }

    #app { display: flex; height: 100vh; }

    /* ── SIDEBAR ── */
    #sidebar { width: 260px; min-width: 260px; border-right: 1px solid var(--border-dim); display: flex; flex-direction: column; background: var(--bg); }
    .sidebar-top { padding: 16px; border-bottom: 1px solid var(--border-dim); display: flex; justify-content: space-between; align-items: center; }
    .logo { color: var(--green); font-weight: 700; font-size: 14px; letter-spacing: 1px; }
    .sidebar-section-label { padding: 12px 16px 4px; font-size: 9px; letter-spacing: 2px; color: var(--text-dim); }
    #lista-contas { flex: 1; overflow-y: auto; padding: 6px 8px; }
    .conta-item { padding: 9px 11px; border: 1px solid var(--border-dim); border-radius: 4px; cursor: pointer; margin-bottom: 5px; transition: border-color 0.1s; }
    .conta-item:hover { border-color: var(--border); }
    .conta-item.ativa { border-color: var(--green); background: var(--green-bg); }
    .conta-nome { color: var(--text); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .conta-item.ativa .conta-nome { color: var(--green); }
    .conta-saldo { font-size: 11px; color: var(--text-dim); margin-top: 1px; }
    .empty-sidebar { padding: 16px; color: var(--text-dim); font-size: 11px; text-align: center; }
    #form-nova-conta { margin: 6px 8px; border: 1px solid var(--green-border); border-radius: 4px; padding: 10px; background: var(--green-bg); display: none; }
    #form-nova-conta input { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 3px; padding: 6px 8px; color: var(--text-bright); margin-bottom: 6px; outline: none; }
    #form-nova-conta input:focus { border-color: var(--green); }
    #form-nova-conta input::placeholder { color: var(--text-dim); }
    .form-btns { display: flex; gap: 6px; }
    .btn-criar { flex: 1; background: var(--green); color: var(--bg); border: none; border-radius: 3px; padding: 6px; cursor: pointer; font-weight: 700; font-size: 11px; }
    .btn-criar:hover { opacity: 0.85; }
    .btn-cancelar { flex: 1; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: 3px; padding: 6px; cursor: pointer; font-size: 11px; }
    .btn-cancelar:hover { color: var(--text); border-color: var(--border); }
    .sidebar-footer { padding: 10px 8px; border-top: 1px solid var(--border-dim); }
    .btn-nova-conta { width: 100%; border: 1px dashed var(--border); background: transparent; color: var(--text-dim); border-radius: 4px; padding: 8px; cursor: pointer; transition: all 0.1s; font-size: 12px; }
    .btn-nova-conta:hover { border-color: var(--green); color: var(--green); }

    /* ── MAIN ── */
    #main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    #empty-state { flex: 1; display: flex; align-items: center; justify-content: center; color: var(--text-dim); font-size: 13px; }
    #painel-conta { flex: 1; display: none; flex-direction: column; overflow: hidden; }

    /* conta header */
    #conta-header { padding: 16px 20px; border-bottom: 1px solid var(--border-dim); display: flex; justify-content: space-between; align-items: flex-start; flex-shrink: 0; }
    .conta-header-info h2 { color: var(--text-bright); font-size: 16px; font-weight: 700; margin-bottom: 2px; }
    .conta-header-saldo { color: var(--green); font-size: 13px; }
    .conta-header-actions { display: flex; gap: 8px; align-items: center; }
    .btn-editar-saldo { background: transparent; border: 1px solid var(--border); color: var(--text-dim); border-radius: 3px; padding: 4px 10px; cursor: pointer; font-size: 11px; }
    .btn-editar-saldo:hover { border-color: var(--amber); color: var(--amber); }
    .btn-excluir { background: transparent; border: 1px solid var(--border-dim); color: var(--text-dim); border-radius: 3px; padding: 4px 10px; cursor: pointer; font-size: 11px; }
    .btn-excluir:hover { border-color: var(--red); color: var(--red); }
    #edit-saldo-form { display: none; align-items: center; gap: 6px; }
    #edit-saldo-form input { background: var(--bg-alt); border: 1px solid var(--amber); border-radius: 3px; padding: 4px 8px; color: var(--text-bright); width: 100px; outline: none; }
    .btn-salvar-saldo { background: var(--amber); color: var(--bg); border: none; border-radius: 3px; padding: 4px 10px; cursor: pointer; font-size: 11px; font-weight: 700; }
    .btn-salvar-saldo:hover { opacity: 0.85; }
    .btn-cancelar-edit { background: transparent; border: 1px solid var(--border); color: var(--text-dim); border-radius: 3px; padding: 4px 8px; cursor: pointer; font-size: 11px; }

    /* content area */
    #content-area { flex: 1; overflow-y: auto; padding: 16px 20px; display: flex; flex-direction: column; gap: 16px; }

    /* saque form */
    .secao-label { font-size: 9px; letter-spacing: 2px; color: var(--text-dim); margin-bottom: 8px; }
    #form-saque { background: var(--bg-alt); border: 1px solid var(--border-dim); border-radius: 6px; padding: 14px; }
    .saque-row { display: flex; gap: 8px; margin-bottom: 8px; align-items: flex-end; }
    .saque-row:last-child { margin-bottom: 0; }
    .field { display: flex; flex-direction: column; gap: 4px; }
    .field label { font-size: 9px; letter-spacing: 1px; color: var(--text-dim); }
    .field input, .field select { background: var(--bg); border: 1px solid var(--border); border-radius: 3px; padding: 6px 8px; color: var(--text-bright); outline: none; }
    .field input:focus, .field select:focus { border-color: var(--green); }
    .field input::placeholder { color: var(--text-dim); }
    .field select option { background: var(--bg-alt); }
    .field-valor { width: 120px; }
    .field-tipo { width: 110px; }
    .field-chave { flex: 1; }
    .field-schedule { flex: 1; }
    .btn-sacar { background: var(--green); color: var(--bg); border: none; border-radius: 3px; padding: 7px 18px; cursor: pointer; font-weight: 700; font-size: 12px; white-space: nowrap; align-self: flex-end; }
    .btn-sacar:hover { opacity: 0.85; }
    .btn-sacar:disabled { opacity: 0.4; cursor: default; }
    .schedule-toggle { display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text-dim); font-size: 11px; user-select: none; }
    .schedule-toggle input[type=checkbox] { accent-color: var(--green); width: 12px; height: 12px; }
    #schedule-row { display: none; }

    /* terminal */
    #terminal-output { background: var(--bg); border: 1px solid var(--border-dim); border-radius: 6px; padding: 12px; font-size: 11px; line-height: 1.7; display: none; }
    .terminal-line { display: flex; gap: 8px; }
    .terminal-prompt { color: var(--text-dim); flex-shrink: 0; }
    .terminal-method { color: var(--blue); }
    .terminal-path { color: var(--text); }
    .terminal-status-ok { color: var(--green); }
    .terminal-status-err { color: var(--red); }
    .terminal-json { color: var(--text); white-space: pre-wrap; word-break: break-all; padding-left: 20px; }
    .terminal-cursor { display: inline-block; width: 8px; height: 13px; background: var(--green); animation: blink 1s step-end infinite; vertical-align: middle; }
    @keyframes blink { 50% { opacity: 0; } }

    /* histórico */
    #historico { display: flex; flex-direction: column; gap: 4px; }
    .saque-row-hist { background: var(--bg-alt); border: 1px solid var(--border-dim); border-radius: 4px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; }
    .saque-status { font-size: 10px; font-weight: 700; letter-spacing: 0.5px; min-width: 90px; }
    .status-ok { color: var(--green); }
    .status-agendado { color: var(--amber); }
    .status-erro { color: var(--red); }
    .saque-info { color: var(--text); font-size: 11px; flex: 1; padding: 0 12px; }
    .saque-meta { color: var(--text-dim); font-size: 10px; text-align: right; }
    .empty-hist { color: var(--text-dim); font-size: 11px; padding: 8px 0; }

    /* scrollbar */
    ::-webkit-scrollbar { width: 4px; }
    ::-webkit-scrollbar-track { background: var(--bg); }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
  </style>
</head>
<body>
<div id="app">
  <aside id="sidebar">
    <div class="sidebar-top">
      <span class="logo">▶ CASEPIX</span>
      <a href="https://ygorstefan.com" target="_blank">ygorstefan.com ↗</a>
    </div>
    <div class="sidebar-section-label">CONTAS</div>
    <div id="lista-contas"><div class="empty-sidebar">Carregando...</div></div>
    <div id="form-nova-conta">
      <input id="inp-nome" type="text" placeholder="Nome da conta" maxlength="60">
      <input id="inp-saldo" type="text" placeholder="Saldo inicial (ex: 500.00)">
      <div class="form-btns">
        <button class="btn-criar" onclick="submitNovaConta()">Criar</button>
        <button class="btn-cancelar" onclick="fecharFormNovaConta()">Cancelar</button>
      </div>
    </div>
    <div class="sidebar-footer">
      <button class="btn-nova-conta" onclick="abrirFormNovaConta()">+ Nova conta</button>
    </div>
  </aside>

  <main id="main">
    <div id="empty-state">▶ Selecione uma conta ou crie uma nova.</div>
    <div id="painel-conta">
      <div id="conta-header">
        <div class="conta-header-info">
          <h2 id="hdr-nome"></h2>
          <div class="conta-header-saldo">Saldo: R$ <span id="hdr-saldo"></span></div>
        </div>
        <div class="conta-header-actions">
          <div id="edit-saldo-form">
            <input id="inp-novo-saldo" type="text" placeholder="0.00" style="width:110px">
            <button class="btn-salvar-saldo" onclick="salvarSaldo()">Salvar</button>
            <button class="btn-cancelar-edit" onclick="fecharEditSaldo()">✕</button>
          </div>
          <button class="btn-editar-saldo" onclick="abrirEditSaldo()">Editar saldo</button>
          <button class="btn-excluir" onclick="confirmarExcluir()">Excluir</button>
        </div>
      </div>
      <div id="content-area">
        <div>
          <div class="secao-label">NOVO SAQUE</div>
          <div id="form-saque">
            <div class="saque-row">
              <div class="field field-valor">
                <label>VALOR (R$)</label>
                <input id="inp-valor" type="text" placeholder="0.00">
              </div>
              <div class="field field-tipo">
                <label>TIPO PIX</label>
                <select id="sel-tipo" onchange="atualizarPlaceholderChave()">
                  <option value="email">email</option>
                  <option value="cpf">cpf</option>
                  <option value="cnpj">cnpj</option>
                  <option value="telefone">telefone</option>
                  <option value="aleatoria">aleatória</option>
                </select>
              </div>
              <div class="field field-chave">
                <label>CHAVE PIX</label>
                <input id="inp-chave" type="text" placeholder="usuario@email.com">
              </div>
              <button class="btn-sacar" onclick="executarSaque()">SACAR</button>
            </div>
            <div class="saque-row">
              <label class="schedule-toggle">
                <input type="checkbox" id="chk-schedule" onchange="toggleSchedule()">
                Agendar para data específica
              </label>
            </div>
            <div class="saque-row" id="schedule-row">
              <div class="field field-schedule">
                <label>DATA/HORA (America/Sao_Paulo)</label>
                <input id="inp-schedule" type="text" placeholder="YYYY-MM-DD HH:mm">
              </div>
            </div>
          </div>
        </div>
        <div id="terminal-output"></div>
        <div>
          <div class="secao-label" id="lbl-historico" style="display:none">HISTÓRICO DE SAQUES</div>
          <div id="historico"></div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
  const placeholders = {
    email:    'usuario@email.com',
    cpf:      '123.456.789-00 ou 12345678900',
    cnpj:     '12.345.678/0001-90 ou 12345678000190',
    telefone: '+5511999999999',
    aleatoria:'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
  };

  let contaAtualId = null;

  function atualizarPlaceholderChave() {
    const tipo = document.getElementById('sel-tipo').value;
    document.getElementById('inp-chave').placeholder = placeholders[tipo] || '';
  }

  function toggleSchedule() {
    const checked = document.getElementById('chk-schedule').checked;
    document.getElementById('schedule-row').style.display = checked ? 'flex' : 'none';
  }

  async function api(method, path, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (body !== undefined) opts.body = JSON.stringify(body);
    const res = await fetch(path, opts);
    let data = null;
    const ct = res.headers.get('content-type') || '';
    if (ct.includes('application/json') && res.status !== 204) {
      data = await res.json();
    }
    return { status: res.status, data };
  }

  function mostrarTerminal(method, path, status, data) {
    const el = document.getElementById('terminal-output');
    el.style.display = 'block';
    const statusClass = status >= 200 && status < 300 ? 'terminal-status-ok' : 'terminal-status-err';
    el.innerHTML = `
      <div class="terminal-line">
        <span class="terminal-prompt">▶</span>
        <span class="terminal-method">${method}</span>
        <span class="terminal-path">${path}</span>
      </div>
      <div class="terminal-line">
        <span class="terminal-prompt">◀</span>
        <span class="${statusClass}">${status}</span>
      </div>
      ${data !== null ? `<div class="terminal-json">${JSON.stringify(data, null, 2)}</div>` : ''}
    `;
  }

  function mostrarCarregando() {
    const el = document.getElementById('terminal-output');
    el.style.display = 'block';
    el.innerHTML = `<div class="terminal-line"><span class="terminal-prompt">▶</span><span class="terminal-cursor"></span></div>`;
  }

  async function carregarContas() {
    const { status, data } = await api('GET', '/account');
    const lista = document.getElementById('lista-contas');
    if (status !== 200 || !Array.isArray(data) || data.length === 0) {
      lista.innerHTML = '<div class="empty-sidebar">Nenhuma conta. Use o botão abaixo.</div>';
      return;
    }
    lista.innerHTML = data.map(c => `
      <div class="conta-item${c.id === contaAtualId ? ' ativa' : ''}" onclick="selecionarConta('${c.id}')">
        <div class="conta-nome">${esc(c.name)}</div>
        <div class="conta-saldo">R$ ${c.balance}</div>
      </div>
    `).join('');
  }

  async function selecionarConta(id) {
    contaAtualId = id;
    await carregarContas();
    const { status, data } = await api('GET', `/account/${id}`);
    if (status !== 200) return;
    document.getElementById('empty-state').style.display = 'none';
    const painel = document.getElementById('painel-conta');
    painel.style.display = 'flex';
    document.getElementById('hdr-nome').textContent = data.name;
    document.getElementById('hdr-saldo').textContent = data.balance;
    document.getElementById('terminal-output').style.display = 'none';
    await carregarHistorico(id);
  }

  async function carregarHistorico(id) {
    document.getElementById('lbl-historico').style.display = 'block';
    const { status, data } = await api('GET', `/account/${id}/withdrawals`);
    const hist = document.getElementById('historico');
    if (status !== 200 || !Array.isArray(data) || data.length === 0) {
      hist.innerHTML = '<div class="empty-hist">Nenhum saque registrado.</div>';
      return;
    }
    hist.innerHTML = data.map(s => {
      let statusHtml, meta;
      if (s.error)      { statusHtml = '<span class="saque-status status-erro">✗ ERRO</span>';      meta = s.error_reason || ''; }
      else if (!s.done) { statusHtml = '<span class="saque-status status-agendado">⏳ AGENDADO</span>'; meta = s.scheduled_for || ''; }
      else              { statusHtml = '<span class="saque-status status-ok">✓ CONCLUÍDO</span>';    meta = s.created_at || ''; }
      const pix = s.pix ? `${s.pix.type}: ${s.pix.key}` : '';
      return `
        <div class="saque-row-hist">
          ${statusHtml}
          <div class="saque-info">R$ ${s.amount} → ${esc(pix)}</div>
          <div class="saque-meta">${esc(meta)}</div>
        </div>
      `;
    }).join('');
  }

  function abrirFormNovaConta() {
    document.getElementById('form-nova-conta').style.display = 'block';
    document.querySelector('.btn-nova-conta').style.display = 'none';
    document.getElementById('inp-nome').focus();
  }

  function fecharFormNovaConta() {
    document.getElementById('form-nova-conta').style.display = 'none';
    document.querySelector('.btn-nova-conta').style.display = 'block';
    document.getElementById('inp-nome').value = '';
    document.getElementById('inp-saldo').value = '';
  }

  async function submitNovaConta() {
    const nome  = document.getElementById('inp-nome').value.trim();
    const saldo = document.getElementById('inp-saldo').value.trim();
    if (!nome || !saldo) return;
    const { status, data } = await api('POST', '/account', { name: nome, balance: parseFloat(saldo) });
    if (status === 201) {
      fecharFormNovaConta();
      await carregarContas();
      selecionarConta(data.id);
    } else {
      alert(data?.erros?.join('\n') || 'Erro ao criar conta.');
    }
  }

  function abrirEditSaldo() {
    document.getElementById('edit-saldo-form').style.display = 'flex';
    document.getElementById('inp-novo-saldo').value = document.getElementById('hdr-saldo').textContent;
    document.getElementById('inp-novo-saldo').focus();
  }

  function fecharEditSaldo() {
    document.getElementById('edit-saldo-form').style.display = 'none';
  }

  async function salvarSaldo() {
    const novoSaldo = document.getElementById('inp-novo-saldo').value.trim();
    const { status, data } = await api('PATCH', `/account/${contaAtualId}/balance`, { balance: parseFloat(novoSaldo) });
    if (status === 200) {
      document.getElementById('hdr-saldo').textContent = data.balance;
      fecharEditSaldo();
      await carregarContas();
    } else {
      alert(data?.erros?.join('\n') || 'Erro ao atualizar saldo.');
    }
  }

  async function confirmarExcluir() {
    if (!confirm(`Excluir a conta "${document.getElementById('hdr-nome').textContent}"? Esta ação não pode ser desfeita.`)) return;
    const { status, data } = await api('DELETE', `/account/${contaAtualId}`);
    if (status === 204) {
      contaAtualId = null;
      document.getElementById('painel-conta').style.display = 'none';
      document.getElementById('empty-state').style.display = 'flex';
      await carregarContas();
    } else if (status === 409) {
      mostrarTerminal('DELETE', `/account/${contaAtualId}`, 409, data);
    } else {
      alert('Erro ao excluir conta.');
    }
  }

  async function executarSaque() {
    const valor    = document.getElementById('inp-valor').value.trim();
    const tipo     = document.getElementById('sel-tipo').value;
    const chave    = document.getElementById('inp-chave').value.trim();
    const agendado = document.getElementById('chk-schedule').checked;
    const schedule = agendado ? document.getElementById('inp-schedule').value.trim() : null;

    const body = {
      method: 'PIX',
      amount: parseFloat(valor),
      pix:    { type: tipo, key: chave },
    };
    if (agendado) body.schedule = schedule;

    const path = `/account/${contaAtualId}/balance/withdraw`;
    mostrarCarregando();

    const { status, data } = await api('POST', path, body);
    mostrarTerminal('POST', path, status, data);

    if (status === 201) {
      const { data: c } = await api('GET', `/account/${contaAtualId}`);
      if (c) document.getElementById('hdr-saldo').textContent = c.balance;
      await carregarContas();
      await carregarHistorico(contaAtualId);
    }
  }

  function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  document.getElementById('inp-nome').addEventListener('keydown', e => { if (e.key === 'Enter') submitNovaConta(); });
  document.getElementById('inp-novo-saldo').addEventListener('keydown', e => { if (e.key === 'Enter') salvarSaldo(); });

  carregarContas();
</script>
</body>
</html>
```

- [ ] **Step 2: Verificar no browser**

Abrir `http://localhost` e confirmar:
- Sidebar carrega contas
- "+ Nova conta" abre form inline e cria
- Clicar na conta carrega painel principal com saldo e histórico
- Editar saldo atualiza o header e a sidebar
- Formulário de saque envia e mostra resposta no terminal
- Histórico atualiza após saque
- Excluir retorna ao empty-state ou mostra erro 409 no terminal

- [ ] **Step 3: Commit**

```bash
git add public/index.html
git commit -m "adiciona frontend dashboard terminal estilo hacker"
```

---

## Task 10: README — seção de deploy

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Adicionar seção de deploy ao README**

Adicionar ao final de `README.md`:

```markdown
## Deploy no servidor (subdomínio)

### Pré-requisitos no servidor

```bash
# Instalar Docker
curl -fsSL https://get.docker.com | sh

# Instalar Docker Compose plugin
sudo apt install docker-compose-plugin
```

### Subir o projeto

```bash
git clone <url-do-repo> casepix
cd casepix
cp .env.example .env
# Editar .env com as credenciais desejadas
docker compose up -d --build
```

O projeto estará acessível na porta 80 do servidor.

### Configurar subdomínio (Hostinger)

1. Acesse o painel da Hostinger → Domínios → casepix.ygorstefan.com
2. Aponte o subdomínio para o IP do servidor (registro A)
3. Aguarde a propagação DNS (pode levar até 24h)

### HTTPS (opcional mas recomendado)

```bash
sudo apt install certbot
sudo certbot certonly --standalone -d casepix.ygorstefan.com

# Atualizar nginx.conf para incluir SSL e redirecionar HTTP → HTTPS
```

Depois de obter o certificado, editar `docker/nginx/nginx.conf` para escutar na porta 443 com os certificados em `/etc/letsencrypt/live/casepix.ygorstefan.com/`.

### Serviços disponíveis após deploy

| Serviço    | URL                            |
|------------|--------------------------------|
| Dashboard  | http://casepix.ygorstefan.com  |
| API        | http://casepix.ygorstefan.com/account |
| Mailhog    | http://\<ip-servidor\>:8025    |
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "adiciona seção de deploy no servidor ao README"
```

---

## Self-Review

### Spec coverage

| Requisito | Task |
|-----------|------|
| Tipos PIX: email, cpf, cnpj, telefone, aleatoria | Task 1 |
| `Conta.alterarSaldo()` | Task 2 |
| `ContaRepositorio`: criar, listar, excluir | Task 2 + 3 |
| `SaqueRepositorio`: listarPorConta, contarPorConta | Task 4 |
| `CriarConta`, `AtualizarSaldo`, `ExcluirConta` com 409 | Task 5 |
| `ListarSaquesDaConta` | Task 6 |
| POST /account, GET /account, GET /account/{id}, PATCH /account/{id}/balance, DELETE /account/{id}, GET /account/{id}/withdrawals | Task 7 |
| nginx proxy + estático | Task 8 |
| Dashboard terminal hacker pt-BR | Task 9 |
| README deploy | Task 10 |
| ContaPossuiSaquesException → 409 | Task 5 + 7 |
| Respostas com `created_at` e `pix` em histórico | Task 4 |
| Estado vazio sidebar e histórico | Task 9 |
| Loading cursor no terminal | Task 9 |

Todas as seções do spec têm cobertura. ✓

### Consistência de tipos

- `ContaRepositorio::criar(Conta): void` → usado em `CriarConta` → `ContaRepositorioMySQL::criar()` ✓
- `ContaRepositorio::listar(): array` → `Conta[]` → `ContaController::listar()` itera com `$c->id()` ✓
- `SaqueRepositorio::listarPorConta(string): array` → retorna `array[]` → `ListarSaquesDaConta` retorna `array` → controller faz `json()` diretamente ✓
- `SaqueRepositorio::contarPorConta(string): int` → `ExcluirConta` compara com `> 0` ✓
- `ContaPossuiSaquesException` importada em `ExcluirConta` e em `ContaController` ✓
- `Conta::alterarSaldo(Dinheiro): void` → `AtualizarSaldo` cria `Dinheiro::deDecimal($novoSaldo)` → passa para `$conta->alterarSaldo()` ✓
- `salvar(Conta): void` — método já existente, `AtualizarSaldo` o usa após `alterarSaldo()` ✓
- PIX tipos em `SaquePix::TIPOS_VALIDOS` = `ContaController::$tiposValidos` ✓ (ambos definem `['email','cpf','cnpj','telefone','aleatoria']`)

### Placeholders

Nenhum TBD ou TODO encontrado. ✓
