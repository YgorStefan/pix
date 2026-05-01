<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Saque\ProcessarSaquesAgendados;
use DateTimeImmutable;
use DateTimeZone;
use Hyperf\DbConnection\Db;
use Ramsey\Uuid\Uuid;

class CronSaquesAgendadosTest extends IntegracaoTestCase
{
    public function testCronProcessaSaqueAgendadoVencido(): void
    {
        $contaId = $this->criarConta('Lucas', '500.00');
        $saqueId = Uuid::uuid4()->toString();
        $passado = (new DateTimeImmutable('-1 minute', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        Db::table('account_withdraw')->insert([
            'id'              => $saqueId,
            'account_id'      => $contaId,
            'method'          => 'PIX',
            'amount'          => '200.00',
            'scheduled'       => true,
            'scheduled_for'   => $passado,
            'done'            => false,
            'error'           => false,
            'error_reason'    => null,
            'processing_since' => null,
            'created_at'      => $passado,
        ]);
        Db::table('account_withdraw_pix')->insert([
            'account_withdraw_id' => $saqueId,
            'type'                => 'email',
            'key'                 => 'lucas@email.com',
        ]);

        $processador = make(ProcessarSaquesAgendados::class);
        $processador->executar();

        $saque = Db::table('account_withdraw')->where('id', $saqueId)->first();
        $this->assertTrue((bool) $saque->done);
        $this->assertFalse((bool) $saque->error);

        $saldo = $this->obterSaldo($contaId);
        $this->assertSame('300.00', number_format((float) $saldo, 2, '.', ''));
    }
}
