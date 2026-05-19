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

    public function listarPorConta(string $contaId): array
    {
        return SaqueModel::query()
            ->where('account_id', $contaId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (SaqueModel $m) {
                $pix = SaquePixModel::find($m->id);
                $scheduledFor = $m->scheduled_for !== null
                    ? (new DateTimeImmutable($m->scheduled_for, new DateTimeZone('UTC')))
                        ->setTimezone(new DateTimeZone('America/Sao_Paulo'))
                        ->format('Y-m-d H:i:s')
                    : null;
                $createdAt = (new DateTimeImmutable($m->created_at, new DateTimeZone('UTC')))
                    ->setTimezone(new DateTimeZone('America/Sao_Paulo'))
                    ->format('Y-m-d H:i:s');
                return [
                    'id'           => $m->id,
                    'amount'       => number_format((float) $m->amount, 2, '.', ''),
                    'method'       => $m->method,
                    'pix'          => $pix ? ['type' => $pix->type, 'key' => $pix->key] : null,
                    'scheduled'    => (bool) $m->scheduled,
                    'scheduled_for'=> $scheduledFor,
                    'done'         => (bool) $m->done,
                    'error'        => (bool) $m->error,
                    'error_reason' => $m->error_reason,
                    'created_at'   => $createdAt,
                ];
            })
            ->all();
    }

    public function contarPorConta(string $contaId): int
    {
        return SaqueModel::query()->where('account_id', $contaId)->count();
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
