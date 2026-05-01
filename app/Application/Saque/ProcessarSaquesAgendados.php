<?php
declare(strict_types=1);

namespace App\Application\Saque;

use App\Application\GerenciadorDeTransacao;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\SaldoInsuficienteException;
use App\Domain\Saque\{NotificacaoSaque, SaqueRepositorio};
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class ProcessarSaquesAgendados
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ContaRepositorio $contaRepositorio,
        private readonly SaqueRepositorio $saqueRepositorio,
        private readonly NotificacaoSaque $notificacaoSaque,
        private readonly GerenciadorDeTransacao $transacao,
        ?LoggerFactory $loggerFactory = null
    ) {
        $this->logger = $loggerFactory?->get('saque') ?? new \Psr\Log\NullLogger();
    }

    public function executar(): void
    {
        $pendentes = $this->saqueRepositorio->buscarAgendadosPendentes();

        foreach ($pendentes as $saqueComMetodo) {
            $saque  = $saqueComMetodo->saque;
            $metodo = $saqueComMetodo->metodo;

            try {
                $reservado = $this->saqueRepositorio->reservarParaProcessamento($saque->id());
                if (!$reservado) {
                    continue;
                }

                $this->transacao->executar(function () use ($saque) {
                    $conta = $this->contaRepositorio->buscarPorIdComLock($saque->contaId());

                    try {
                        $conta->deduzirSaldo($saque->valor());
                        $saque->marcarComoConcluido();
                        $this->contaRepositorio->salvar($conta);
                    } catch (SaldoInsuficienteException) {
                        $saque->marcarComoErro('saldo insuficiente');
                    }

                    $this->saqueRepositorio->atualizarSaque($saque);
                });

                if ($saque->concluido() || $saque->erro()) {
                    try {
                        $this->notificacaoSaque->enviar($saque, $metodo);
                    } catch (\Throwable $e) {
                        $this->logger->error('Falha ao enviar email após saque agendado', [
                            'saque_id' => $saque->id(),
                            'erro'     => $e->getMessage(),
                        ]);
                    }
                }

                $this->logger->info('Saque agendado processado', [
                    'saque_id'  => $saque->id(),
                    'concluido' => $saque->concluido(),
                    'erro'      => $saque->erro(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Erro inesperado ao processar saque agendado', [
                    'saque_id' => $saque->id(),
                    'erro'     => $e->getMessage(),
                ]);
                try {
                    $this->saqueRepositorio->liberarReserva($saque->id());
                } catch (\Throwable) {
                    // best-effort: se falhar aqui, o saque será detectado como órfão no próximo ciclo
                }
            }
        }
    }
}
