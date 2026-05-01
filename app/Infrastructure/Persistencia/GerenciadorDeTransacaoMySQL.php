<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistencia;

use App\Application\GerenciadorDeTransacao;
use Hyperf\DbConnection\Db;

class GerenciadorDeTransacaoMySQL implements GerenciadorDeTransacao
{
    public function executar(callable $operacao): mixed
    {
        return Db::transaction($operacao);
    }
}
