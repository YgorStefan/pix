<?php
declare(strict_types=1);

namespace App\Application\Saque;

use App\Domain\Conta\ContaRepositorio;
use App\Domain\Saque\SaqueRepositorio;

class ListarSaquesDaConta
{
    public function __construct(
        private readonly ContaRepositorio $contaRepositorio,
        private readonly SaqueRepositorio $saqueRepositorio
    ) {}

    public function executar(string $contaId): array
    {
        $this->contaRepositorio->buscarPorId($contaId);
        return $this->saqueRepositorio->listarPorConta($contaId);
    }
}
