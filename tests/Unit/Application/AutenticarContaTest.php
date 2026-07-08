<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Conta\AutenticarConta;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Conta\Exception\CredenciaisInvalidasException;
use App\Domain\Saque\Dinheiro;
use Mockery;
use PHPUnit\Framework\TestCase;

class AutenticarContaTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private AutenticarConta $useCase;

    protected function setUp(): void
    {
        $this->contaRepo = Mockery::mock(ContaRepositorio::class);
        $this->useCase   = new AutenticarConta($this->contaRepo);
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testAutenticaComCredenciaisCorretas(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('100.00'), 'ygor@teste.com', password_hash('senha123', PASSWORD_BCRYPT));
        $this->contaRepo->expects('buscarPorEmail')->with('ygor@teste.com')->andReturn($conta);

        $resultado = $this->useCase->executar('ygor@teste.com', 'senha123');

        $this->assertSame($conta, $resultado);
    }

    public function testSenhaErradaLancaExcecao(): void
    {
        $conta = new Conta('id-1', 'Ygor', Dinheiro::deDecimal('100.00'), 'ygor@teste.com', password_hash('senha123', PASSWORD_BCRYPT));
        $this->contaRepo->expects('buscarPorEmail')->andReturn($conta);

        $this->expectException(CredenciaisInvalidasException::class);
        $this->useCase->executar('ygor@teste.com', 'senha-errada');
    }

    public function testEmailInexistenteLancaCredenciaisInvalidas(): void
    {
        $this->contaRepo->expects('buscarPorEmail')->andThrow(new ContaNaoEncontradaException());

        $this->expectException(CredenciaisInvalidasException::class);
        $this->useCase->executar('naoexiste@teste.com', 'senha123');
    }
}
