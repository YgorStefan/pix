<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Conta\CriarConta;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Saque\Exception\ValorInvalidoException;
use Mockery;
use PHPUnit\Framework\TestCase;

class CriarContaTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private CriarConta $useCase;

    protected function setUp(): void
    {
        $this->contaRepo = Mockery::mock(ContaRepositorio::class);
        $this->useCase   = new CriarConta($this->contaRepo);
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testCriaContaComUUIDEPersiste(): void
    {
        $this->contaRepo->expects('criar')->with(Mockery::type(Conta::class));
        $conta = $this->useCase->executar('Ygor', '500.00');
        $this->assertSame('Ygor', $conta->nome());
        $this->assertSame('500.00', $conta->obterSaldo()->toDecimal());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $conta->id());
    }

    public function testCriaComSaldoZero(): void
    {
        $this->contaRepo->expects('criar');
        $conta = $this->useCase->executar('Teste', '0.00');
        $this->assertSame('0.00', $conta->obterSaldo()->toDecimal());
    }

    public function testSaldoNegativoLancaExcecao(): void
    {
        $this->contaRepo->shouldNotReceive('criar');
        $this->expectException(ValorInvalidoException::class);
        $this->useCase->executar('Teste', '-10.00');
    }
}
