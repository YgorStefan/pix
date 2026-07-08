<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Auth\TokenService;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly HttpResponse $response
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extrairToken($request);
        if ($token === null) {
            return $this->naoAutorizado();
        }

        try {
            $claims = $this->tokenService->validar($token);
        } catch (\Throwable) {
            return $this->naoAutorizado();
        }

        if (($claims['role'] ?? null) !== 'admin') {
            return $this->naoAutorizado();
        }

        return $handler->handle($request);
    }

    private function extrairToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }
        return substr($header, 7);
    }

    private function naoAutorizado(): ResponseInterface
    {
        return $this->response->json(['message' => 'Não autenticado.'])->withStatus(401);
    }
}
