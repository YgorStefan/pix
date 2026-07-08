<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Auth;

use App\Domain\Conta\Exception\CredenciaisInvalidasException;
use App\Infrastructure\Auth\AutenticarAdmin;
use PHPUnit\Framework\TestCase;

class AutenticarAdminTest extends TestCase
{
    public function testCredenciaisCorretasNaoLancamExcecao(): void
    {
        (new AutenticarAdmin())->executar((string) env('ADMIN_EMAIL'), 'admin123');
        $this->addToAssertionCount(1);
    }

    public function testSenhaErradaLancaExcecao(): void
    {
        $this->expectException(CredenciaisInvalidasException::class);
        (new AutenticarAdmin())->executar((string) env('ADMIN_EMAIL'), 'senha-errada');
    }

    public function testEmailErradoLancaExcecao(): void
    {
        $this->expectException(CredenciaisInvalidasException::class);
        (new AutenticarAdmin())->executar('outro@casepix.com', 'admin123');
    }
}
