<?php
declare(strict_types=1);

namespace App\Application\Conta;

use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Conta\Exception\CredenciaisInvalidasException;

class AutenticarConta
{
    public function __construct(private readonly ContaRepositorio $contaRepositorio) {}

    public function executar(string $email, string $senha): Conta
    {
        try {
            $conta = $this->contaRepositorio->buscarPorEmail($email);
        } catch (ContaNaoEncontradaException) {
            throw new CredenciaisInvalidasException('E-mail ou senha inválidos.');
        }

        if (!$conta->senhaConfere($senha)) {
            throw new CredenciaisInvalidasException('E-mail ou senha inválidos.');
        }

        return $conta;
    }
}
