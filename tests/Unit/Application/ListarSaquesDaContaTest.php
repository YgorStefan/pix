<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Saque\ListarSaquesDaConta;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Saque\Dinheiro;
use App\Domain\Saque\SaqueRepositorio;
use Mockery;
use PHPUnit\Framework\TestCase;

class ListarSaquesDaContaTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private SaqueRepositorio $saqueRepo;
    private ListarSaquesDaConta $useCase;

    protected function setUp(): void
    {
        $this->contaRepo = Mockery::mock(ContaRepositorio::class);
        $this->saqueRepo = Mockery::mock(SaqueRepositorio::class);
        $this->useCase   = new ListarSaquesDaConta($this->contaRepo, $this->saqueRepo);
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testRetornaSaquesFormatados(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('500.00'));
        $this->contaRepo->expects('buscarPorId')->with('id-1')->andReturn($conta);
        $saques = [
            ['id' => 'saque-1', 'amount' => '150.00', 'done' => true, 'error' => false],
            ['id' => 'saque-2', 'amount' => '50.00',  'done' => false, 'error' => false],
        ];
        $this->saqueRepo->expects('listarPorConta')->with('id-1')->andReturn($saques);
        $resultado = $this->useCase->executar('id-1');
        $this->assertCount(2, $resultado);
        $this->assertSame('150.00', $resultado[0]['amount']);
    }

    public function testRetornaListaVaziaSeNaoHaSaques(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('500.00'));
        $this->contaRepo->expects('buscarPorId')->andReturn($conta);
        $this->saqueRepo->expects('listarPorConta')->andReturn([]);
        $this->assertSame([], $this->useCase->executar('id-1'));
    }

    public function testContaNaoEncontradaLancaExcecao(): void
    {
        $this->contaRepo->expects('buscarPorId')->andThrow(new ContaNaoEncontradaException('não encontrada'));
        $this->saqueRepo->shouldNotReceive('listarPorConta');
        $this->expectException(ContaNaoEncontradaException::class);
        $this->useCase->executar('id-inexistente');
    }
}
