<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Conta\Conta;
use App\Domain\Conta\Exception\SaldoInsuficienteException;
use App\Domain\Saque\Dinheiro;
use PHPUnit\Framework\TestCase;

class ContaTest extends TestCase
{
    private Conta $conta;

    protected function setUp(): void
    {
        $this->conta = new Conta('uuid-1', 'João', Dinheiro::deDecimal('200.00'));
    }

    public function testDeduzSaldoComSuficiente(): void
    {
        $this->conta->deduzirSaldo(Dinheiro::deDecimal('150.75'));
        $this->assertSame('49.25', $this->conta->obterSaldo()->toDecimal());
    }

    public function testDeduzSaldoExatoZera(): void
    {
        $this->conta->deduzirSaldo(Dinheiro::deDecimal('200.00'));
        $this->assertSame('0.00', $this->conta->obterSaldo()->toDecimal());
    }

    public function testDeduzSaldoInsuficienteLancaExcecao(): void
    {
        $this->expectException(SaldoInsuficienteException::class);
        $this->conta->deduzirSaldo(Dinheiro::deDecimal('200.01'));
    }

    public function testTemSaldoSuficiente(): void
    {
        $this->assertTrue($this->conta->temSaldoSuficiente(Dinheiro::deDecimal('200.00')));
        $this->assertFalse($this->conta->temSaldoSuficiente(Dinheiro::deDecimal('200.01')));
    }

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
}
