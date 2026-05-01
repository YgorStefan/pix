<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistencia;

use App\Domain\Saque\{Dinheiro, MetodoDeSaque, Saque, SaqueComMetodo, SaquePix, SaqueRepositorio};
use DateTimeImmutable;
use DateTimeZone;
use Hyperf\DbConnection\Db;

class SaqueRepositorioMySQL implements SaqueRepositorio
{
    public function salvar(Saque $saque, MetodoDeSaque $metodo): void
    {
        SaqueModel::create([
            'id'               => $saque->id(),
            'account_id'       => $saque->contaId(),
            'method'           => $metodo->obterTipo(),
            'amount'           => $saque->valor()->toDecimal(),
            'scheduled'        => $saque->estaAgendado(),
            'scheduled_for'    => $saque->agendadoPara()?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'done'             => $saque->concluido(),
            'error'            => $saque->erro(),
            'error_reason'     => $saque->motivoErro(),
            'processing_since' => null,
            'created_at'       => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);

        if ($metodo instanceof SaquePix) {
            SaquePixModel::create([
                'account_withdraw_id' => $saque->id(),
                'type'                => $metodo->tipo(),
                'key'                 => $metodo->chave(),
            ]);
        }
    }

    public function buscarAgendadosPendentes(): array
    {
        $agora = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $modelos = SaqueModel::query()
            ->where('scheduled', true)
            ->where('done', false)
            ->where('error', false)
            ->whereNull('processing_since')
            ->where('scheduled_for', '<=', $agora)
            ->get();

        return $modelos->map(function (SaqueModel $m) {
            $saque = $this->paraEntidade($m);
            $pixModel = SaquePixModel::find($m->id);
            $metodo = new SaquePix($pixModel->type, $pixModel->key);
            return new SaqueComMetodo($saque, $metodo);
        })->all();
    }

    public function reservarParaProcessamento(string $saqueId): bool
    {
        $afetadas = Db::table('account_withdraw')
            ->where('id', $saqueId)
            ->whereNull('processing_since')
            ->update([
                'processing_since' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ]);

        return $afetadas > 0;
    }

    public function liberarReserva(string $saqueId): void
    {
        Db::table('account_withdraw')
            ->where('id', $saqueId)
            ->where('done', false)
            ->where('error', false)
            ->update(['processing_since' => null]);
    }

    public function atualizarSaque(Saque $saque): void
    {
        SaqueModel::query()->where('id', $saque->id())->update([
            'done'         => $saque->concluido(),
            'error'        => $saque->erro(),
            'error_reason' => $saque->motivoErro(),
        ]);
    }

    private function paraEntidade(SaqueModel $model): Saque
    {
        $agendadoPara = $model->scheduled_for !== null
            ? new DateTimeImmutable($model->scheduled_for, new DateTimeZone('UTC'))
            : null;

        return Saque::reconstituir(
            $model->id,
            $model->account_id,
            Dinheiro::deDecimal((string) $model->amount),
            $agendadoPara,
            (bool) $model->done,
            (bool) $model->error,
            $model->error_reason
        );
    }
}
