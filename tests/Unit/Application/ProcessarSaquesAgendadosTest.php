<?php
declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\GerenciadorDeTransacao;
use App\Application\Saque\ProcessarSaquesAgendados;
use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Saque\{Dinheiro, NotificacaoSaque, Saque, SaqueComMetodo, SaquePix, SaqueRepositorio};
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ProcessarSaquesAgendadosTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    private ContaRepositorio $contaRepo;
    private SaqueRepositorio $saqueRepo;
    private NotificacaoSaque $notificacao;
    private GerenciadorDeTransacao $transacao;
    private ProcessarSaquesAgendados $useCase;

    protected function setUp(): void
    {
        $this->contaRepo   = Mockery::mock(ContaRepositorio::class);
        $this->saqueRepo   = Mockery::mock(SaqueRepositorio::class);
        $this->notificacao = Mockery::mock(NotificacaoSaque::class);
        $this->transacao   = new class implements GerenciadorDeTransacao {
            public function executar(callable $operacao): mixed { return $operacao(); }
        };
        $this->useCase = new ProcessarSaquesAgendados(
            $this->contaRepo, $this->saqueRepo, $this->notificacao, $this->transacao
        );
    }

    protected function tearDown(): void { Mockery::close(); }

    private function pendente(string $saqueId, string $contaId, string $valor): SaqueComMetodo
    {
        $passado = new DateTimeImmutable('-1 minute');
        $saque   = Saque::reconstituir($saqueId, $contaId, Dinheiro::deDecimal($valor), $passado, false, false, null);
        $pix     = new SaquePix('email', 'teste@email.com');
        return new SaqueComMetodo($saque, $pix);
    }

    public function testProcessaSaquesAgendadosPendentes(): void
    {
        $pendente = $this->pendente('s-1', 'c-1', '100.00');
        $conta    = new Conta('c-1', 'João', Dinheiro::deDecimal('500.00'));

        $this->saqueRepo->expects('buscarAgendadosPendentes')->andReturn([$pendente]);
        $this->saqueRepo->expects('reservarParaProcessamento')->with('s-1')->andReturn(true);
        $this->contaRepo->expects('buscarPorIdComLock')->with('c-1')->andReturn($conta);
        $this->contaRepo->expects('salvar');
        $this->saqueRepo->expects('atualizarSaque')->with(Mockery::on(fn($s) => $s->concluido()));
        $this->notificacao->expects('enviar');

        $this->useCase->executar();
    }

    public function testRegistraErroSeSaldoInsuficiente(): void
    {
        $pendente = $this->pendente('s-1', 'c-1', '1000.00');
        $conta    = new Conta('c-1', 'João', Dinheiro::deDecimal('50.00'));

        $this->saqueRepo->expects('buscarAgendadosPendentes')->andReturn([$pendente]);
        $this->saqueRepo->expects('reservarParaProcessamento')->with('s-1')->andReturn(true);
        $this->contaRepo->expects('buscarPorIdComLock')->with('c-1')->andReturn($conta);
        $this->saqueRepo->expects('atualizarSaque')->with(Mockery::on(fn($s) => $s->erro() && $s->motivoErro() === 'saldo insuficiente'));
        $this->notificacao->expects('enviar')->with(Mockery::on(fn($s) => $s->erro()), Mockery::any());
        $this->contaRepo->shouldNotReceive('salvar');

        $this->useCase->executar();
    }

    public function testIgnoraSaqueJaReservado(): void
    {
        $pendente = $this->pendente('s-1', 'c-1', '100.00');

        $this->saqueRepo->expects('buscarAgendadosPendentes')->andReturn([$pendente]);
        $this->saqueRepo->expects('reservarParaProcessamento')->with('s-1')->andReturn(false);
        $this->contaRepo->shouldNotReceive('buscarPorIdComLock');

        $this->useCase->executar();
    }

    public function testListaVaziaNaoFazNada(): void
    {
        $this->saqueRepo->expects('buscarAgendadosPendentes')->andReturn([]);
        $this->contaRepo->shouldNotReceive('buscarPorIdComLock');
        $this->useCase->executar();
    }

    public function testFalhaDeNotificacaoNaoInterrompeProcessamento(): void
    {
        $pendente = $this->pendente('s-1', 'c-1', '100.00');
        $conta    = new Conta('c-1', 'João', Dinheiro::deDecimal('500.00'));

        $this->saqueRepo->expects('buscarAgendadosPendentes')->andReturn([$pendente]);
        $this->saqueRepo->expects('reservarParaProcessamento')->with('s-1')->andReturn(true);
        $this->contaRepo->expects('buscarPorIdComLock')->with('c-1')->andReturn($conta);
        $this->contaRepo->expects('salvar');
        $this->saqueRepo->expects('atualizarSaque')->with(Mockery::on(fn($s) => $s->concluido()));
        $this->notificacao->expects('enviar')->andThrow(new \RuntimeException('SMTP indisponível'));

        $this->useCase->executar();
    }

    public function testContinuaSeUmSaqueFalhar(): void
    {
        $pendente1 = $this->pendente('s-1', 'c-1', '100.00');
        $pendente2 = $this->pendente('s-2', 'c-2', '50.00');
        $conta2    = new Conta('c-2', 'Maria', Dinheiro::deDecimal('500.00'));

        $this->saqueRepo->expects('buscarAgendadosPendentes')->andReturn([$pendente1, $pendente2]);
        $this->saqueRepo->expects('reservarParaProcessamento')->with('s-1')->andReturn(true);
        $this->saqueRepo->expects('reservarParaProcessamento')->with('s-2')->andReturn(true);
        $this->contaRepo->expects('buscarPorIdComLock')->with('c-1')->andThrow(new \RuntimeException('erro inesperado'));
        $this->contaRepo->expects('buscarPorIdComLock')->with('c-2')->andReturn($conta2);
        $this->contaRepo->expects('salvar');
        $this->saqueRepo->expects('atualizarSaque')->with(Mockery::on(fn($s) => $s->id() === 's-2' && $s->concluido()));
        $this->notificacao->expects('enviar');

        $this->useCase->executar();
    }
}
