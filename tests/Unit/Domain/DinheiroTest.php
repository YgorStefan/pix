<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Saque\Dinheiro;
use App\Domain\Saque\Exception\ValorInvalidoException;
use PHPUnit\Framework\TestCase;

class DinheiroTest extends TestCase
{
    public function testCriaDeDecimal(): void
    {
        $dinheiro = Dinheiro::deDecimal('150.75');
        $this->assertSame(15075, $dinheiro->centavos());
        $this->assertSame('150.75', $dinheiro->toDecimal());
    }

    public function testCriaDeCentavos(): void
    {
        $dinheiro = Dinheiro::deCentavos(100);
        $this->assertSame('1.00', $dinheiro->toDecimal());
    }

    public function testSubtrai(): void
    {
        $total = Dinheiro::deDecimal('200.00');
        $parcela = Dinheiro::deDecimal('50.75');
        $resultado = $total->subtrair($parcela);
        $this->assertSame(14925, $resultado->centavos());
    }

    public function testSubtracaoExataRetornZero(): void
    {
        $valor = Dinheiro::deDecimal('100.00');
        $resultado = $valor->subtrair(Dinheiro::deDecimal('100.00'));
        $this->assertSame(0, $resultado->centavos());
    }

    public function testSubtracaoNegativaLancaExcecao(): void
    {
        $this->expectException(ValorInvalidoException::class);
        Dinheiro::deDecimal('10.00')->subtrair(Dinheiro::deDecimal('50.00'));
    }

    public function testValorNegativoLancaExcecao(): void
    {
        $this->expectException(ValorInvalidoException::class);
        Dinheiro::deCentavos(-1);
    }

    public function testMenorQue(): void
    {
        $pequeno = Dinheiro::deDecimal('10.00');
        $grande = Dinheiro::deDecimal('20.00');
        $this->assertTrue($pequeno->menorQue($grande));
        $this->assertFalse($grande->menorQue($pequeno));
    }

    public function testIgual(): void
    {
        $a = Dinheiro::deDecimal('10.00');
        $b = Dinheiro::deCentavos(1000);
        $this->assertTrue($a->igual($b));
    }

    public function testIgualRetornaFalsoQuandoDiferente(): void
    {
        $a = Dinheiro::deDecimal('10.00');
        $b = Dinheiro::deDecimal('10.01');
        $this->assertFalse($a->igual($b));
    }

    public function testMenorQueComValoresIguaisRetornaFalso(): void
    {
        $a = Dinheiro::deDecimal('10.00');
        $b = Dinheiro::deDecimal('10.00');
        $this->assertFalse($a->menorQue($b));
    }

    public function testZeroCentavosEValido(): void
    {
        $zero = Dinheiro::deCentavos(0);
        $this->assertSame(0, $zero->centavos());
        $this->assertSame('0.00', $zero->toDecimal());
    }

    public function testDeDecimalZero(): void
    {
        $zero = Dinheiro::deDecimal('0.00');
        $this->assertSame(0, $zero->centavos());
    }

    public function testToDecimalUmCentavo(): void
    {
        $this->assertSame('0.01', Dinheiro::deCentavos(1)->toDecimal());
    }
}
