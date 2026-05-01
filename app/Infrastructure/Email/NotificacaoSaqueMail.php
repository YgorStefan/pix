<?php
declare(strict_types=1);

namespace App\Infrastructure\Email;

use App\Domain\Saque\Saque;
use App\Domain\Saque\SaquePix;
use DateTimeImmutable;
use DateTimeZone;

class NotificacaoSaqueMail
{
    public function __construct(
        private readonly Saque $saque,
        private readonly SaquePix $pix
    ) {}

    public function build(): string
    {
        $dataHora = $this->saque->agendadoPara()
            ? $this->saque->agendadoPara()->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s')
            : (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i:s');

        $valor = 'R$ ' . number_format((float) $this->saque->valor()->toDecimal(), 2, ',', '.');

        if ($this->saque->erro()) {
            return "
            <html><body>
            <h2>Saque PIX não realizado - Saldo insuficiente</h2>
            <p>Seu saque agendado não pôde ser processado pois o saldo disponível era insuficiente no momento da execução.</p>
            <p><strong>Data e hora da tentativa:</strong> {$dataHora}</p>
            <p><strong>Valor solicitado:</strong> {$valor}</p>
            <p><strong>Tipo de chave PIX:</strong> {$this->pix->tipo()}</p>
            <p><strong>Chave PIX:</strong> {$this->pix->chave()}</p>
            </body></html>";
        }

        return "
        <html><body>
        <h2>Saque PIX Realizado</h2>
        <p><strong>Data e hora:</strong> {$dataHora}</p>
        <p><strong>Valor sacado:</strong> {$valor}</p>
        <p><strong>Tipo de chave PIX:</strong> {$this->pix->tipo()}</p>
        <p><strong>Chave PIX:</strong> {$this->pix->chave()}</p>
        </body></html>";
    }
}
