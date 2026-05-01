<?php
declare(strict_types=1);

namespace App\Infrastructure\Email;

use App\Domain\Saque\{MetodoDeSaque, NotificacaoSaque, Saque, SaquePix};
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class NotificacaoSaqueEmail implements NotificacaoSaque
{
    private LoggerInterface $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get('saque');
    }

    public function enviar(Saque $saque, MetodoDeSaque $metodo): void
    {
        if (! $metodo instanceof SaquePix) {
            return;
        }

        try {
            $dsn = sprintf('smtp://%s:%d', env('MAIL_HOST', 'mailhog'), (int) env('MAIL_PORT', 1025));
            $mailer = new Mailer(Transport::fromDsn($dsn));

            $subject = $saque->erro()
                ? 'Saque PIX não realizado - Saldo insuficiente'
                : 'Saque PIX realizado com sucesso';

            $email = (new Email())
                ->from(sprintf('%s <%s>', env('MAIL_FROM_NAME', 'CasePix'), env('MAIL_FROM_ADDRESS', 'noreply@casepix.com')))
                ->to($metodo->chave())
                ->subject($subject)
                ->html((new NotificacaoSaqueMail($saque, $metodo))->build());

            $mailer->send($email);

            $this->logger->info('Email de notificação enviado', [
                'saque_id' => $saque->id(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao enviar email de notificação', [
                'saque_id' => $saque->id(),
                'erro'     => $e->getMessage(),
            ]);
        }
    }
}
