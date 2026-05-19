<?php
declare(strict_types=1);

namespace App\Application\Conta;

use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaPossuiSaquesException;
use App\Domain\Saque\SaqueRepositorio;

class ExcluirConta
{
    public function __construct(
        private readonly ContaRepositorio $contaRepositorio,
        private readonly SaqueRepositorio $saqueRepositorio
    ) {}

    public function executar(string $id): void
    {
        $this->contaRepositorio->buscarPorId($id);

        if ($this->saqueRepositorio->contarPorConta($id) > 0) {
            throw new ContaPossuiSaquesException("Conta '{$id}' possui saques e não pode ser excluída.");
        }

        $this->contaRepositorio->excluir($id);
    }
}
