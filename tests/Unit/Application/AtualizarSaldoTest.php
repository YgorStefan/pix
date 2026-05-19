<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Conta\AtualizarSaldo;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Saque\Dinheiro;
use App\Domain\Saque\Exception\ValorInvalidoException;
use Mockery;
use PHPUnit\Framework\TestCase;

class AtualizarSaldoTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private AtualizarSaldo $useCase;

    protected function setUp(): void
    {
        $this->contaRepo = Mockery::mock(ContaRepositorio::class);
        $this->useCase   = new AtualizarSaldo($this->contaRepo);
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testAtualizaSaldoComSucesso(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('100.00'));
        $this->contaRepo->expects('buscarPorId')->with('id-1')->andReturn($conta);
        $this->contaRepo->expects('salvar')->with(Mockery::type(Conta::class));
        $resultado = $this->useCase->executar('id-1', '300.00');
        $this->assertSame('300.00', $resultado->obterSaldo()->toDecimal());
    }

    public function testSaldoNegativoLancaExcecao(): void
    {
        $this->contaRepo->shouldNotReceive('buscarPorId');
        $this->expectException(ValorInvalidoException::class);
        $this->useCase->executar('id-1', '-1.00');
    }

    public function testContaNaoEncontradaLancaExcecao(): void
    {
        $this->contaRepo->expects('buscarPorId')->andThrow(new ContaNaoEncontradaException('não encontrada'));
        $this->expectException(ContaNaoEncontradaException::class);
        $this->useCase->executar('id-inexistente', '100.00');
    }
}
