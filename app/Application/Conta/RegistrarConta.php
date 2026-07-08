<?php
declare(strict_types=1);

namespace App\Application\Conta;

use App\Domain\Conta\Conta;
use App\Domain\Conta\ContaRepositorio;
use App\Domain\Conta\Exception\ContaNaoEncontradaException;
use App\Domain\Conta\Exception\EmailJaCadastradoException;
use App\Domain\Saque\Dinheiro;
use Ramsey\Uuid\Uuid;

class RegistrarConta
{
    public function __construct(private readonly ContaRepositorio $contaRepositorio) {}

    public function executar(string $nome, string $email, string $senha, string $saldoInicial): Conta
    {
        if (!$this->emailDisponivel($email)) {
            throw new EmailJaCadastradoException("E-mail '{$email}' já está cadastrado.");
        }

        $conta = new Conta(
            Uuid::uuid4()->toString(),
            $nome,
            Dinheiro::deDecimal($saldoInicial),
            $email,
            password_hash($senha, PASSWORD_BCRYPT)
        );
        $this->contaRepositorio->criar($conta);
        return $conta;
    }

    private function emailDisponivel(string $email): bool
    {
        try {
            $this->contaRepositorio->buscarPorEmail($email);
            return false;
        } catch (ContaNaoEncontradaException) {
            return true;
        }
    }
}
