<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\GerenciadorDeTransacao;
use App\Application\Saque\AgendarSaque;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Saque\Dinheiro;
use App\Domain\Saque\Exception\AgendamentoNoPassadoException;
use App\Domain\Saque\Saque;
use App\Domain\Saque\SaqueRepositorio;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class AgendarSaqueTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private SaqueRepositorio $saqueRepo;
    private GerenciadorDeTransacao $transacao;
    private AgendarSaque $useCase;

    protected function setUp(): void
    {
        $this->contaRepo = Mockery::mock(ContaRepositorio::class);
        $this->saqueRepo = Mockery::mock(SaqueRepositorio::class);
        $this->transacao = Mockery::mock(GerenciadorDeTransacao::class);
        $this->transacao->allows('executar')->andReturnUsing(fn(callable $op) => $op());
        $this->useCase   = new AgendarSaque($this->contaRepo, $this->saqueRepo, $this->transacao);
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testAgendaSaqueComDataFutura(): void
    {
        $futuro = new DateTimeImmutable('+2 hours', new \DateTimeZone('America/Sao_Paulo'));
        $conta  = new Conta('conta-1', 'Maria', Dinheiro::deDecimal('500.00'));

        $this->contaRepo->expects('buscarPorId')->with('conta-1')->andReturn($conta);
        $this->saqueRepo->expects('salvar')->with(Mockery::type(Saque::class), Mockery::any());

        $saque = $this->useCase->executar('conta-1', 'email', 'maria@email.com', '200.00', $futuro->format('Y-m-d H:i'));

        $this->assertTrue($saque->estaAgendado());
        $this->assertFalse($saque->concluido());
    }

    public function testLancaExcecaoSeContaNaoExiste(): void
    {
        $this->contaRepo->expects('buscarPorId')->andThrow(new ContaNaoEncontradaException('não encontrada'));
        $this->expectException(ContaNaoEncontradaException::class);
        $this->useCase->executar('conta-x', 'email', 'a@b.com', '100.00', (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i'));
    }

    public function testLancaExcecaoSeDataNoPassado(): void
    {
        $conta = new Conta('conta-1', 'Maria', Dinheiro::deDecimal('500.00'));
        $this->contaRepo->expects('buscarPorId')->andReturn($conta);
        $this->saqueRepo->shouldNotReceive('salvar');

        $this->expectException(AgendamentoNoPassadoException::class);
        $this->useCase->executar('conta-1', 'email', 'a@b.com', '100.00', '2020-01-01 10:00');
    }

    public function testFormatoDataInvalidoLancaExcecao(): void
    {
        $conta = new Conta('conta-1', 'Maria', Dinheiro::deDecimal('500.00'));
        $this->contaRepo->expects('buscarPorId')->andReturn($conta);
        $this->saqueRepo->shouldNotReceive('salvar');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->executar('conta-1', 'email', 'a@b.com', '100.00', 'data-invalida');
    }

    public function testDataComMesInvalidoLancaExcecao(): void
    {
        $conta = new Conta('conta-1', 'Maria', Dinheiro::deDecimal('500.00'));
        $this->contaRepo->expects('buscarPorId')->andReturn($conta);
        $this->saqueRepo->shouldNotReceive('salvar');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->executar('conta-1', 'email', 'a@b.com', '100.00', '2026-13-01 10:00');
    }

    public function testTipoPixInvalidoLancaExcecao(): void
    {
        $conta = new Conta('conta-1', 'Maria', Dinheiro::deDecimal('500.00'));
        $this->contaRepo->expects('buscarPorId')->andReturn($conta);
        $this->saqueRepo->shouldNotReceive('salvar');

        $this->expectException(\App\Domain\Saque\Exception\TipoPixInvalidoException::class);
        $futuro = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i');
        $this->useCase->executar('conta-1', 'bitcoin', '1A2B3C', '100.00', $futuro);
    }
}
