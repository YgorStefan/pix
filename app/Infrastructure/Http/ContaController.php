<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Saque\{AgendarSaque, ProcessarSaque};
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Conta\Exception\SaldoInsuficienteException;
use App\Domain\Saque\Exception\AgendamentoNoPassadoException;
use App\Domain\Saque\Exception\TipoPixInvalidoException;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

#[Controller(prefix: '/account')]
class ContaController
{
    public function __construct(
        private readonly ProcessarSaque $processarSaque,
        private readonly AgendarSaque $agendarSaque
    ) {}

    #[PostMapping(path: '/{contaId}/balance/withdraw')]
    public function sacar(string $contaId, RequestInterface $request, ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $body = $request->getParsedBody();

        $erros = $this->validar($body, $contaId);
        if (!empty($erros)) {
            return $response->json(['erros' => $erros])->withStatus(422);
        }

        try {
            $pixTipo  = $body['pix']['type'];
            $pixChave = $body['pix']['key'];
            $valor    = (string) $body['amount'];
            $schedule = $body['schedule'] ?? null;

            if ($schedule === null) {
                $saque = $this->processarSaque->executar($contaId, $pixTipo, $pixChave, $valor);
            } else {
                $saque = $this->agendarSaque->executar($contaId, $pixTipo, $pixChave, $valor, $schedule);
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

        } catch (ContaNaoEncontradaException $e) {
            return $response->json(['message' => 'Conta não encontrada.'])->withStatus(404);
        } catch (SaldoInsuficienteException $e) {
            return $response->json(['message' => 'Saldo insuficiente.'])->withStatus(422);
        } catch (AgendamentoNoPassadoException $e) {
            return $response->json(['message' => 'Data de agendamento não pode ser no passado.'])->withStatus(422);
        } catch (TipoPixInvalidoException $e) {
            return $response->json(['message' => 'Tipo de chave PIX inválido.'])->withStatus(422);
        } catch (\Throwable $e) {
            return $response->json(['message' => 'Erro interno. Tente novamente.'])->withStatus(500);
        }
    }

    private function validar(mixed $body, string $contaId): array
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
        if (!isset($body['pix']['type']) || $body['pix']['type'] !== 'email') {
            $erros[] = 'pix.type deve ser "email"';
        }
        if (!isset($body['pix']['key']) || !filter_var($body['pix']['key'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'pix.key deve ser um email válido';
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
}
