<?php
declare(strict_types=1);

namespace App\Domain\Saque;

interface SaqueRepositorio
{
    public function salvar(Saque $saque, MetodoDeSaque $metodo): void;

    /** @return SaqueComMetodo[] saques agendados com scheduled_for <= agora e processing_since IS NULL */
    public function buscarAgendadosPendentes(): array;

    public function reservarParaProcessamento(string $saqueId): bool;

    public function liberarReserva(string $saqueId): void;

    public function atualizarSaque(Saque $saque): void;

    /** @return array[] array de arrays com dados do saque e PIX */
    public function listarPorConta(string $contaId): array;

    public function contarPorConta(string $contaId): int;
}
