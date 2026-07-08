<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Conta\{AutenticarConta, RegistrarConta};
use App\Domain\Conta\Exception\{CredenciaisInvalidasException, EmailJaCadastradoException};
use App\Infrastructure\Auth\TokenService;
use Hyperf\HttpServer\Annotation\{Controller, PostMapping};
use Hyperf\HttpServer\Contract\{RequestInterface, ResponseInterface};

#[Controller(prefix: '/auth')]
class AuthController
{
    public function __construct(
        private readonly RegistrarConta $registrarConta,
        private readonly AutenticarConta $autenticarConta,
        private readonly TokenService $tokenService
    ) {}

    #[PostMapping(path: 'register')]
    public function registrar(RequestInterface $request, ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $body = $request->getParsedBody();
        $erros = $this->validarRegistro($body);
        if (!empty($erros)) {
            return $response->json(['erros' => $erros])->withStatus(422);
        }

        try {
            $conta = $this->registrarConta->executar(
                trim($body['name']),
                strtolower(trim($body['email'])),
                (string) $body['password'],
                (string) ($body['balance'] ?? '0')
            );
            $token = $this->tokenService->gerar($conta->id(), 'conta');
            return $response->json([
                'token'   => $token,
                'id'      => $conta->id(),
                'name'    => $conta->nome(),
                'email'   => $conta->email(),
                'balance' => $conta->obterSaldo()->toDecimal(),
            ])->withStatus(201);
        } catch (EmailJaCadastradoException) {
            return $response->json(['message' => 'E-mail já cadastrado.'])->withStatus(409);
        } catch (\Throwable) {
            return $response->json(['message' => 'Erro interno. Tente novamente.'])->withStatus(500);
        }
    }

    #[PostMapping(path: 'login')]
    public function login(RequestInterface $request, ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $body = $request->getParsedBody();
        $erros = $this->validarLogin($body);
        if (!empty($erros)) {
            return $response->json(['erros' => $erros])->withStatus(422);
        }

        try {
            $conta = $this->autenticarConta->executar(strtolower(trim($body['email'])), (string) $body['password']);
            $token = $this->tokenService->gerar($conta->id(), 'conta');
            return $response->json([
                'token'   => $token,
                'id'      => $conta->id(),
                'name'    => $conta->nome(),
                'email'   => $conta->email(),
                'balance' => $conta->obterSaldo()->toDecimal(),
            ]);
        } catch (CredenciaisInvalidasException) {
            return $response->json(['message' => 'E-mail ou senha inválidos.'])->withStatus(401);
        }
    }

    private function validarRegistro(mixed $body): array
    {
        if (!is_array($body)) {
            return ['body deve ser um JSON válido'];
        }
        $erros = [];
        if (empty(trim($body['name'] ?? ''))) {
            $erros[] = 'name é obrigatório e não pode ser vazio';
        }
        if (!filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'email deve ser um endereço válido';
        }
        if (strlen((string) ($body['password'] ?? '')) < 6) {
            $erros[] = 'password deve ter no mínimo 6 caracteres';
        }
        if (array_key_exists('balance', $body) && $body['balance'] !== null) {
            $balance = $body['balance'];
            if ((!is_int($balance) && !is_float($balance) && !preg_match('/^\d+(\.\d{1,2})?$/', (string) $balance))
                || (float) $balance < 0
            ) {
                $erros[] = 'balance deve ser um número >= 0 com no máximo 2 casas decimais (ex: 500.00)';
            }
        }
        return $erros;
    }

    private function validarLogin(mixed $body): array
    {
        if (!is_array($body)) {
            return ['body deve ser um JSON válido'];
        }
        $erros = [];
        if (!filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'email deve ser um endereço válido';
        }
        if (empty($body['password'] ?? '')) {
            $erros[] = 'password é obrigatório';
        }
        return $erros;
    }
}
