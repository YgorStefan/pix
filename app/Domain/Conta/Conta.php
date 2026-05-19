<?php
declare(strict_types=1);

namespace App\Domain\Conta;

use App\Domain\Conta\Exception\SaldoInsuficienteException;
use App\Domain\Saque\Dinheiro;

class Conta
{
    public function __construct(
        private readonly string $id,
        private readonly string $nome,
        private Dinheiro $saldo
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function nome(): string
    {
        return $this->nome;
    }

    public function obterSaldo(): Dinheiro
    {
        return $this->saldo;
    }

    public function temSaldoSuficiente(Dinheiro $valor): bool
    {
        return !$this->saldo->menorQue($valor);
    }

    public function deduzirSaldo(Dinheiro $valor): void
    {
        if (!$this->temSaldoSuficiente($valor)) {
            throw new SaldoInsuficienteException('Saldo insuficiente para realizar o saque.');
        }
        $this->saldo = $this->saldo->subtrair($valor);
    }

    public function alterarSaldo(Dinheiro $novoSaldo): void
    {
        $this->saldo = $novoSaldo;
    }
}
