<?php
declare(strict_types=1);

namespace App\Infrastructure\Cron;

use App\Application\Saque\ProcessarSaquesAgendados;
use Hyperf\Crontab\Annotation\Crontab;

#[Crontab(
    rule: '*/5 * * * * *',
    name: 'ProcessarSaquesAgendados',
    memo: 'Processa saques agendados pendentes a cada 5 segundos'
)]
class CronSaquesAgendados
{
    public function __construct(private readonly ProcessarSaquesAgendados $processarSaquesAgendados) {}

    public function execute(): void
    {
        $this->processarSaquesAgendados->executar();
    }
}
