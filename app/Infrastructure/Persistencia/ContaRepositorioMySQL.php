<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistencia;

use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Saque\Dinheiro;
use Hyperf\DbConnection\Db;

class ContaRepositorioMySQL implements ContaRepositorio
{
    public function buscarPorId(string $id): Conta
    {
        $model = ContaModel::find($id);
        if ($model === null) {
            throw new ContaNaoEncontradaException("Conta '{$id}' não encontrada.");
        }
        return $this->paraEntidade($model);
    }

    public function buscarPorIdComLock(string $id): Conta
    {
        $model = ContaModel::query()->where('id', $id)->lockForUpdate()->first();
        if ($model === null) {
            throw new ContaNaoEncontradaException("Conta '{$id}' não encontrada.");
        }
        return $this->paraEntidade($model);
    }

    public function salvar(Conta $conta): void
    {
        ContaModel::query()->where('id', $conta->id())->update([
            'balance' => $conta->obterSaldo()->toDecimal(),
        ]);
    }

    private function paraEntidade(ContaModel $model): Conta
    {
        return new Conta(
            $model->id,
            $model->name,
            Dinheiro::deDecimal((string) $model->balance)
        );
    }
}
