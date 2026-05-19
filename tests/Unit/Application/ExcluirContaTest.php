<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Conta\ExcluirConta;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Conta\Exception\ContaPossuiSaquesException;
use App\Domain\Saque\Dinheiro;
use App\Domain\Saque\SaqueRepositorio;
use Mockery;
use PHPUnit\Framework\TestCase;

class ExcluirContaTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private SaqueRepositorio $saqueRepo;
    private ExcluirConta $useCase;

    protected function setUp(): void
    {
        $this->contaRepo = Mockery::mock(ContaRepositorio::class);
        $this->saqueRepo = Mockery::mock(SaqueRepositorio::class);
        $this->useCase   = new ExcluirConta($this->contaRepo, $this->saqueRepo);
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testExcluiContaSemSaques(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('0.00'));
        $this->contaRepo->expects('buscarPorId')->with('id-1')->andReturn($conta);
        $this->saqueRepo->expects('contarPorConta')->with('id-1')->andReturn(0);
        $this->contaRepo->expects('excluir')->with('id-1');
        $this->useCase->executar('id-1');
        $this->addToAssertionCount(1);
    }

    public function testLancaExcecaoSeContaPossuiSaques(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('0.00'));
        $this->contaRepo->expects('buscarPorId')->andReturn($conta);
        $this->saqueRepo->expects('contarPorConta')->andReturn(3);
        $this->contaRepo->shouldNotReceive('excluir');
        $this->expectException(ContaPossuiSaquesException::class);
        $this->useCase->executar('id-1');
    }

    public function testContaNaoEncontradaLancaExcecao(): void
    {
        $this->contaRepo->expects('buscarPorId')->andThrow(new ContaNaoEncontradaException('não encontrada'));
        $this->expectException(ContaNaoEncontradaException::class);
        $this->useCase->executar('id-inexistente');
    }
}
