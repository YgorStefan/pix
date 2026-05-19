<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\GerenciadorDeTransacao;
use App\Application\Saque\ProcessarSaque;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\SaldoInsuficienteException;
use App\Domain\Saque\Dinheiro;
use App\Domain\Saque\NotificacaoSaque;
use App\Domain\Saque\Saque;
use App\Domain\Saque\SaqueRepositorio;
use Mockery;
use PHPUnit\Framework\TestCase;

class ProcessarSaqueTest extends TestCase
{
    private ContaRepositorio $contaRepo;
    private SaqueRepositorio $saqueRepo;
    private NotificacaoSaque $notificacao;
    private GerenciadorDeTransacao $transacao;
    private ProcessarSaque $useCase;

    protected function setUp(): void
    {
        $this->contaRepo   = Mockery::mock(ContaRepositorio::class);
        $this->saqueRepo   = Mockery::mock(SaqueRepositorio::class);
        $this->notificacao = Mockery::mock(NotificacaoSaque::class);
        $this->transacao   = new class implements GerenciadorDeTransacao {
            public function executar(callable $operacao): mixed { return $operacao(); }
        };
        $this->useCase = new ProcessarSaque(
            $this->contaRepo, $this->saqueRepo, $this->notificacao, $this->transacao
        );
    }

    protected function tearDown(): void { Mockery::close(); }

    public function testProcessaSaqueComSucesso(): void
    {
        $conta = new Conta('conta-1', 'João', Dinheiro::deDecimal('500.00'));

        $this->contaRepo->expects('buscarPorIdComLock')->with('conta-1')->andReturn($conta);
        $this->contaRepo->expects('salvar')->with(Mockery::type(Conta::class));
        $this->saqueRepo->expects('salvar')->with(Mockery::type(Saque::class), Mockery::any());
        $this->notificacao->expects('enviar')->with(Mockery::type(Saque::class), Mockery::any());

        $saque = $this->useCase->executar('conta-1', 'email', 'fulano@email.com', '150.75');

        $this->assertTrue($saque->concluido());
        $this->assertFalse($saque->estaAgendado());
        $this->assertSame('150.75', $saque->valor()->toDecimal());
    }

    public function testLancaExcecaoSeSaldoInsuficiente(): void
    {
        $conta = new Conta('conta-1', 'João', Dinheiro::deDecimal('10.00'));
        $this->contaRepo->expects('buscarPorIdComLock')->andReturn($conta);
        $this->saqueRepo->shouldNotReceive('salvar');
        $this->notificacao->shouldNotReceive('enviar');

        $this->expectException(SaldoInsuficienteException::class);
        $this->useCase->executar('conta-1', 'email', 'fulano@email.com', '100.00');
    }

    public function testEmailFalhaNaoReverteSaque(): void
    {
        $conta = new Conta('conta-1', 'João', Dinheiro::deDecimal('500.00'));
        $this->contaRepo->expects('buscarPorIdComLock')->andReturn($conta);
        $this->contaRepo->expects('salvar');
        $this->saqueRepo->expects('salvar');
        $this->notificacao->expects('enviar')->andThrow(new \RuntimeException('Mailhog fora do ar'));

        $saque = $this->useCase->executar('conta-1', 'email', 'fulano@email.com', '100.00');
        $this->assertTrue($saque->concluido());
    }

    public function testTipoPixInvalidoLancaExcecaoAntesDeTransacionar(): void
    {
        $this->contaRepo->shouldNotReceive('buscarPorIdComLock');
        $this->saqueRepo->shouldNotReceive('salvar');

        $this->expectException(\App\Domain\Saque\Exception\TipoPixInvalidoException::class);
        $this->useCase->executar('conta-1', 'bitcoin', '1A2B3C', '100.00');
    }

    public function testValorZeroEProcessado(): void
    {
        $conta = new Conta('conta-1', 'João', Dinheiro::deDecimal('500.00'));
        $this->contaRepo->expects('buscarPorIdComLock')->andReturn($conta);
        $this->contaRepo->expects('salvar');
        $this->saqueRepo->expects('salvar');
        $this->notificacao->expects('enviar');

        $saque = $this->useCase->executar('conta-1', 'email', 'fulano@email.com', '0.00');
        $this->assertTrue($saque->concluido());
        $this->assertSame('0.00', $saque->valor()->toDecimal());
    }
}
