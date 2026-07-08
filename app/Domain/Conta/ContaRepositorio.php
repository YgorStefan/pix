<?php
declare(strict_types=1);

namespace App\Domain\Conta;

interface ContaRepositorio
{
    public function buscarPorId(string $id): Conta;
    public function buscarPorIdComLock(string $id): Conta;
    /** @throws \App\Domain\Conta\Exception\ContaNaoEncontradaException */
    public function buscarPorEmail(string $email): Conta;
    public function criar(Conta $conta): void;
    /** @return Conta[] */
    public function listar(): array;
    public function salvar(Conta $conta): void;
    public function excluir(string $id): void;
}
