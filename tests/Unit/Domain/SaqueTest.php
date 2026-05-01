<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Saque\Dinheiro;
use App\Domain\Saque\Saque;
use App\Domain\Saque\Exception\AgendamentoNoPassadoException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SaqueTest extends TestCase
{
    public function testCriaImediatoComDoneTrue(): void
    {
        $saque = Saque::imediato('uuid-1', 'conta-1', Dinheiro::deDecimal('100.00'));
        $this->assertFalse($saque->estaAgendado());
        $this->assertTrue($saque->concluido());
        $this->assertFalse($saque->erro());
    }

    public function testCriaAgendadoComDataFutura(): void
    {
        $futuro = new DateTimeImmutable('+1 hour');
        $saque = Saque::agendar('uuid-1', 'conta-1', Dinheiro::deDecimal('100.00'), $futuro);
        $this->assertTrue($saque->estaAgendado());
        $this->assertFalse($saque->concluido());
    }

    public function testAgendamentoNoPassadoLancaExcecao(): void
    {
        $this->expectException(AgendamentoNoPassadoException::class);
        Saque::agendar('uuid-1', 'conta-1', Dinheiro::deDecimal('100.00'), new DateTimeImmutable('-1 minute'));
    }

    public function testMarcarComoConcluido(): void
    {
        $futuro = new DateTimeImmutable('+1 hour');
        $saque = Saque::agendar('uuid-1', 'conta-1', Dinheiro::deDecimal('100.00'), $futuro);
        $saque->marcarComoConcluido();
        $this->assertTrue($saque->concluido());
        $this->assertFalse($saque->erro());
    }

    public function testMarcarComoErro(): void
    {
        $futuro = new DateTimeImmutable('+1 hour');
        $saque = Saque::agendar('uuid-1', 'conta-1', Dinheiro::deDecimal('100.00'), $futuro);
        $saque->marcarComoErro('saldo insuficiente');
        $this->assertTrue($saque->erro());
        $this->assertSame('saldo insuficiente', $saque->motivoErro());
    }

    public function testReconstituirNaoValidaData(): void
    {
        $passado = new DateTimeImmutable('-1 day');
        $saque = Saque::reconstituir('uuid-1', 'conta-1', Dinheiro::deDecimal('100.00'), $passado, false, false, null);
        $this->assertTrue($saque->estaAgendado());
    }

    public function testMotivoErroEhNuloQuandoSemErro(): void
    {
        $saque = Saque::imediato('uuid-1', 'conta-1', Dinheiro::deDecimal('100.00'));
        $this->assertNull($saque->motivoErro());
        $this->assertFalse($saque->erro());
    }

    public function testImediatoNaoEstaAgendado(): void
    {
        $saque = Saque::imediato('uuid-1', 'conta-1', Dinheiro::deDecimal('100.00'));
        $this->assertFalse($saque->estaAgendado());
        $this->assertNull($saque->agendadoPara());
    }

    public function testAgendadoParaRetornaDataCorreta(): void
    {
        $futuro = new DateTimeImmutable('+2 hours');
        $saque = Saque::agendar('uuid-1', 'conta-1', Dinheiro::deDecimal('100.00'), $futuro);
        $this->assertSame($futuro, $saque->agendadoPara());
    }

    public function testReconstituirComConcluidoTrue(): void
    {
        $saque = Saque::reconstituir('uuid-1', 'conta-1', Dinheiro::deDecimal('50.00'), null, true, false, null);
        $this->assertTrue($saque->concluido());
        $this->assertFalse($saque->estaAgendado());
    }
}
