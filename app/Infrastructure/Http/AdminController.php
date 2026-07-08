<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Conta\{AtualizarSaldo, ExcluirConta};
use App\Application\Saque\ListarSaquesDaConta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\{ContaNaoEncontradaException, ContaPossuiSaquesException, CredenciaisInvalidasException};
use App\Infrastructure\Auth\{AutenticarAdmin, TokenService};
use App\Infrastructure\Http\Middleware\AdminMiddleware;
use Hyperf\HttpServer\Annotation\{Controller, DeleteMapping, GetMapping, Middleware, PatchMapping, PostMapping};
use Hyperf\HttpServer\Contract\{RequestInterface, ResponseInterface};

#[Controller(prefix: '/admin')]
class AdminController
{
    public function __construct(
        private readonly AutenticarAdmin $autenticarAdmin,
        private readonly TokenService $tokenService,
        private readonly ContaRepositorio $contaRepositorio,
        private readonly AtualizarSaldo $atualizarSaldo,
        private readonly ExcluirConta $excluirConta,
        private readonly ListarSaquesDaConta $listarSaquesDaConta
    ) {}

    #[PostMapping(path: 'login')]
    public function login(RequestInterface $request, ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $body = $request->getParsedBody();
        if (!is_array($body) || empty($body['email']) || empty($body['password'])) {
            return $response->json(['erros' => ['email e password são obrigatórios']])->withStatus(422);
        }

        try {
            $this->autenticarAdmin->executar((string) $body['email'], (string) $body['password']);
        } catch (CredenciaisInvalidasException) {
            return $response->json(['message' => 'E-mail ou senha inválidos.'])->withStatus(401);
        }

        $token = $this->tokenService->gerar((string) $body['email'], 'admin');
        return $response->json(['token' => $token]);
    }

    #[GetMapping(path: 'accounts')]
    #[Middleware(AdminMiddleware::class)]
    public function listar(ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $contas = $this->contaRepositorio->listar();
        return $response->json(array_map(fn($c) => [
            'id'      => $c->id(),
            'name'    => $c->nome(),
            'email'   => $c->email(),
            'balance' => $c->obterSaldo()->toDecimal(),
        ], $contas));
    }

    #[GetMapping(path: 'accounts/{id}/withdrawals')]
    #[Middleware(AdminMiddleware::class)]
    public function listarSaques(string $id, ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        try {
            return $response->json($this->listarSaquesDaConta->executar($id));
        } catch (ContaNaoEncontradaException) {
            return $response->json(['message' => 'Conta não encontrada.'])->withStatus(404);
        }
    }

    #[PatchMapping(path: 'accounts/{id}/balance')]
    #[Middleware(AdminMiddleware::class)]
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
                'email'   => $conta->email(),
                'balance' => $conta->obterSaldo()->toDecimal(),
            ]);
        } catch (ContaNaoEncontradaException) {
            return $response->json(['message' => 'Conta não encontrada.'])->withStatus(404);
        } catch (\Throwable) {
            return $response->json(['message' => 'Erro interno. Tente novamente.'])->withStatus(500);
        }
    }

    #[DeleteMapping(path: 'accounts/{id}')]
    #[Middleware(AdminMiddleware::class)]
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
}
