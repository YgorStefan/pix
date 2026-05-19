<?php
declare(strict_types=1);

namespace App\Application\Conta;

use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Saque\Dinheiro;
use Ramsey\Uuid\Uuid;

class CriarConta
{
    public function __construct(private readonly ContaRepositorio $contaRepositorio) {}

    public function executar(string $nome, string $saldo): Conta
    {
        $conta = new Conta(Uuid::uuid4()->toString(), $nome, Dinheiro::deDecimal($saldo));
        $this->contaRepositorio->criar($conta);
        return $conta;
    }
}
