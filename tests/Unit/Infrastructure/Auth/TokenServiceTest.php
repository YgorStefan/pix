<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Auth;

use App\Infrastructure\Auth\TokenService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

class TokenServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        JWT::$timestamp = null;
    }

    public function testGeraTokenERecuperaClaims(): void
    {
        $service = new TokenService();
        $token   = $service->gerar('conta-1', 'conta');
        $claims  = $service->validar($token);

        $this->assertSame('conta-1', $claims['sub']);
        $this->assertSame('conta', $claims['role']);
    }

    public function testTokenExpiradoLancaExcecao(): void
    {
        $service = new TokenService();
        $token   = $service->gerar('conta-1', 'conta');

        JWT::$timestamp = time() + (8 * 24 * 60 * 60);

        $this->expectException(ExpiredException::class);
        $service->validar($token);
    }

    public function testTokenComAssinaturaInvalidaLancaExcecao(): void
    {
        $service = new TokenService();
        $token   = $service->gerar('conta-1', 'conta');
        $partes  = explode('.', $token);
        $partes[2] = strrev($partes[2]);
        $tokenAdulterado = implode('.', $partes);

        $this->expectException(\UnexpectedValueException::class);
        $service->validar($tokenAdulterado);
    }
}
