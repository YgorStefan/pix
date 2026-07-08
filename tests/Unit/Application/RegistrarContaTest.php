<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Conta\RegistrarConta;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Conta\Exception\EmailJaCadastradoException;
use App\Domain\Saque\Dinheiro;
use App\Domain\Saque\Exception\ValorInvalidoException;
use Mockery;
use PHPUnit\Framework\TestCase;

class RegistrarContaTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private RegistrarConta $useCase;

    protected function setUp(): void
    {
        $this->contaRepo = Mockery::mock(ContaRepositorio::class);
        $this->useCase   = new RegistrarConta($this->contaRepo);
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testRegistraContaComUUIDESenhaHasheada(): void
    {
        $this->contaRepo->expects('buscarPorEmail')->with('ygor@teste.com')
            ->andThrow(new ContaNaoEncontradaException());
        $this->contaRepo->expects('criar')->with(Mockery::on(function (Conta $conta) {
            return $conta->email() === 'ygor@teste.com'
                && $conta->senhaConfere('senha123')
                && !$conta->senhaConfere('senha-errada');
        }));

        $conta = $this->useCase->executar('Ygor', 'ygor@teste.com', 'senha123', '500.00');

        $this->assertSame('Ygor', $conta->nome());
        $this->assertSame('500.00', $conta->obterSaldo()->toDecimal());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $conta->id());
    }

    public function testEmailJaCadastradoLancaExcecao(): void
    {
        $contaExistente = new Conta('id-1', 'Outro', Dinheiro::deDecimal('0.00'), 'ygor@teste.com', password_hash('x', PASSWORD_BCRYPT));
        $this->contaRepo->expects('buscarPorEmail')->andReturn($contaExistente);
        $this->contaRepo->shouldNotReceive('criar');

        $this->expectException(EmailJaCadastradoException::class);
        $this->useCase->executar('Ygor', 'ygor@teste.com', 'senha123', '0.00');
    }

    public function testSaldoNegativoLancaExcecao(): void
    {
        $this->contaRepo->expects('buscarPorEmail')->andThrow(new ContaNaoEncontradaException());
        $this->contaRepo->shouldNotReceive('criar');

        $this->expectException(ValorInvalidoException::class);
        $this->useCase->executar('Ygor', 'ygor@teste.com', 'senha123', '-10.00');
    }
}
