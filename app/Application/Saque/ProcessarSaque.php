<?php
declare(strict_types=1);

namespace App\Application\Saque;

use App\Application\GerenciadorDeTransacao;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Saque\{Dinheiro, NotificacaoSaque, Saque, SaquePix, SaqueRepositorio};
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class ProcessarSaque
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

    public function executar(string $contaId, string $pixTipo, string $pixChave, string $valor): Saque
    {
        $dinheiro = Dinheiro::deDecimal($valor);
        $pix = new SaquePix($pixTipo, $pixChave);

        $saque = $this->transacao->executar(function () use ($contaId, $dinheiro, $pix) {
            $conta = $this->contaRepositorio->buscarPorIdComLock($contaId);
            $conta->deduzirSaldo($dinheiro);

            $saque = Saque::imediato(Uuid::uuid4()->toString(), $contaId, $dinheiro);

            $this->saqueRepositorio->salvar($saque, $pix);
            $this->contaRepositorio->salvar($conta);

            return $saque;
        });

        $this->logger->info('Saque imediato processado', [
            'saque_id'  => $saque->id(),
            'conta_id'  => $contaId,
            'valor'     => $valor,
        ]);

        try {
            $this->notificacaoSaque->enviar($saque, $pix);
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao enviar email de notificação', [
                'saque_id' => $saque->id(),
                'erro'     => $e->getMessage(),
            ]);
        }

        return $saque;
    }
}
