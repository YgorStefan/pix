<?php
declare(strict_types=1);

namespace App\Application;

interface GerenciadorDeTransacao
{
    public function executar(callable $operacao): mixed;
}
