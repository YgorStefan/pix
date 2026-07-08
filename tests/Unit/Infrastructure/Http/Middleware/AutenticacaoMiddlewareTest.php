<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Middleware;

use App\Infrastructure\Auth\TokenService;
use App\Infrastructure\Http\Middleware\AutenticacaoMiddleware;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AutenticacaoMiddlewareTest extends TestCase
{
    protected function tearDown(): void { Mockery::close(); }

    public function testBloqueiaRequisicaoSemToken(): void
    {
        $tokenService = Mockery::mock(TokenService::class);
        $httpResponse = Mockery::mock(HttpResponse::class);
        $psrResponse  = Mockery::mock(PsrResponse::class);
        $httpResponse->expects('json')->andReturn($psrResponse);
        $psrResponse->expects('withStatus')->with(401)->andReturn($psrResponse);

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->expects('getHeaderLine')->with('Authorization')->andReturn('');

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $middleware = new AutenticacaoMiddleware($tokenService, $httpResponse);
        $resultado  = $middleware->process($request, $handler);

        $this->assertSame($psrResponse, $resultado);
    }

    public function testPermiteRequisicaoComTokenValidoDeConta(): void
    {
        $tokenService = Mockery::mock(TokenService::class);
        $tokenService->expects('validar')->with('token-valido')->andReturn(['sub' => 'conta-1', 'role' => 'conta']);
        $httpResponse = Mockery::mock(HttpResponse::class);

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->expects('getHeaderLine')->with('Authorization')->andReturn('Bearer token-valido');
        $request->expects('withAttribute')->with('contaId', 'conta-1')->andReturnSelf();

        $respostaEsperada = Mockery::mock(PsrResponse::class);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->expects('handle')->with($request)->andReturn($respostaEsperada);

        $middleware = new AutenticacaoMiddleware($tokenService, $httpResponse);
        $resultado  = $middleware->process($request, $handler);

        $this->assertSame($respostaEsperada, $resultado);
    }

    public function testBloqueiaTokenDeAdminNaRotaDeConta(): void
    {
        $tokenService = Mockery::mock(TokenService::class);
        $tokenService->expects('validar')->andReturn(['sub' => 'admin@casepix.com', 'role' => 'admin']);
        $httpResponse = Mockery::mock(HttpResponse::class);
        $psrResponse  = Mockery::mock(PsrResponse::class);
        $httpResponse->expects('json')->andReturn($psrResponse);
        $psrResponse->expects('withStatus')->with(401)->andReturn($psrResponse);

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->expects('getHeaderLine')->andReturn('Bearer token-admin');

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $middleware = new AutenticacaoMiddleware($tokenService, $httpResponse);
        $resultado  = $middleware->process($request, $handler);

        $this->assertSame($psrResponse, $resultado);
    }

    public function testBloqueiaTokenInvalido(): void
    {
        $tokenService = Mockery::mock(TokenService::class);
        $tokenService->expects('validar')->andThrow(new \UnexpectedValueException('token inválido'));
        $httpResponse = Mockery::mock(HttpResponse::class);
        $psrResponse  = Mockery::mock(PsrResponse::class);
        $httpResponse->expects('json')->andReturn($psrResponse);
        $psrResponse->expects('withStatus')->with(401)->andReturn($psrResponse);

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->expects('getHeaderLine')->andReturn('Bearer token-invalido');

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $middleware = new AutenticacaoMiddleware($tokenService, $httpResponse);
        $resultado  = $middleware->process($request, $handler);

        $this->assertSame($psrResponse, $resultado);
    }
}
