<?php
declare(strict_types=1);

namespace App\Application\Conta;

use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Saque\Dinheiro;

class AtualizarSaldo
{
    public function __construct(private readonly ContaRepositorio $contaRepositorio) {}

    public function executar(string $id, string $novoSaldo): Conta
    {
        $dinheiro = Dinheiro::deDecimal($novoSaldo);
        $conta = $this->contaRepositorio->buscarPorId($id);
        $conta->alterarSaldo($dinheiro);
        $this->contaRepositorio->salvar($conta);
        return $conta;
    }
}
