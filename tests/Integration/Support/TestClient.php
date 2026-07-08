<?php
declare(strict_types=1);

namespace Tests\Integration\Support;

use Hyperf\HttpMessage\Server\Response as Psr7Response;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Testing\Http\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Hyperf\Testing\Http\Client::execute() não chama MiddlewareManager::sortMiddlewares(),
 * diferente do Server::onRequest() real. Sem essa chamada, middlewares registrados por
 * rota (via anotação #[Middleware]) chegam ao dispatcher como objetos PriorityMiddleware
 * crus (em vez do nome da classe), que são ignorados silenciosamente: nenhuma exceção,
 * a rota simplesmente executa sem passar pelo middleware. Corrige replicando o
 * comportamento do servidor real.
 */
class TestClient extends Client
{
    protected function execute(ServerRequestInterface $psr7Request): ResponseInterface
    {
        $this->persistToContext($psr7Request, new Psr7Response());

        $psr7Request = $this->coreMiddleware->dispatch($psr7Request);
        /** @var Dispatched $dispatched */
        $dispatched = $psr7Request->getAttribute(Dispatched::class);
        $middlewares = $this->middlewares;
        if ($dispatched->isFound()) {
            $registeredMiddlewares = MiddlewareManager::get($this->serverName, $dispatched->handler->route, $psr7Request->getMethod());
            $middlewares = array_merge($middlewares, $registeredMiddlewares);
        }
        $middlewares = MiddlewareManager::sortMiddlewares($middlewares);

        try {
            return $this->dispatcher->dispatch($psr7Request, $middlewares, $this->coreMiddleware);
        } catch (Throwable $throwable) {
            return $this->exceptionHandlerDispatcher->dispatch($throwable, $this->exceptionHandlers);
        }
    }
}
