<?php
declare(strict_types=1);

namespace App\Application\Saque;

use App\Application\GerenciadorDeTransacao;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Saque\{Dinheiro, Saque, SaquePix, SaqueRepositorio};
use DateTimeImmutable;
use DateTimeZone;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class AgendarSaque
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ContaRepositorio $contaRepositorio,
        private readonly SaqueRepositorio $saqueRepositorio,
        private readonly GerenciadorDeTransacao $transacao,
        ?LoggerFactory $loggerFactory = null
    ) {
        $this->logger = $loggerFactory?->get('saque') ?? new \Psr\Log\NullLogger();
    }

    public function executar(
        string $contaId,
        string $pixTipo,
        string $pixChave,
        string $valor,
        string $agendadoPara
    ): Saque {
        $this->contaRepositorio->buscarPorId($contaId);

        $dinheiro    = Dinheiro::deDecimal($valor);
        $pix         = new SaquePix($pixTipo, $pixChave);
        $dataHoraBr = DateTimeImmutable::createFromFormat('Y-m-d H:i', $agendadoPara, new DateTimeZone('America/Sao_Paulo'));
        $errosDt    = DateTimeImmutable::getLastErrors();
        if ($dataHoraBr === false || ($errosDt && (!empty($errosDt['warnings']) || !empty($errosDt['errors'])))) {
            throw new \InvalidArgumentException('Data inválida. Use Y-m-d H:i (ex: 2026-01-01 15:00)');
        }
        $dataHoraUtc = $dataHoraBr->setTimezone(new DateTimeZone('UTC'));

        $saque = Saque::agendar(Uuid::uuid4()->toString(), $contaId, $dinheiro, $dataHoraUtc);

        $this->transacao->executar(fn() => $this->saqueRepositorio->salvar($saque, $pix));

        $this->logger->info('Saque agendado registrado', [
            'saque_id'      => $saque->id(),
            'conta_id'      => $contaId,
            'valor'         => $valor,
            'agendado_para' => $dataHoraUtc->format('Y-m-d H:i:s'),
        ]);

        return $saque;
    }
}
