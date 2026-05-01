<?php
declare(strict_types=1);

namespace App\Domain\Saque;

use App\Domain\Saque\Exception\AgendamentoNoPassadoException;
use DateTimeImmutable;

class Saque
{
    private function __construct(
        private readonly string $id,
        private readonly string $contaId,
        private readonly Dinheiro $valor,
        private readonly ?DateTimeImmutable $agendadoPara,
        private bool $concluido,
        private bool $erro,
        private ?string $motivoErro
    ) {}

    public static function imediato(string $id, string $contaId, Dinheiro $valor): self
    {
        return new self($id, $contaId, $valor, null, true, false, null);
    }

    public static function agendar(string $id, string $contaId, Dinheiro $valor, DateTimeImmutable $agendadoPara): self
    {
        if ($agendadoPara <= new DateTimeImmutable()) {
            throw new AgendamentoNoPassadoException('Não é permitido agendar saque para um momento no passado.');
        }
        return new self($id, $contaId, $valor, $agendadoPara, false, false, null);
    }

    public static function reconstituir(
        string $id,
        string $contaId,
        Dinheiro $valor,
        ?DateTimeImmutable $agendadoPara,
        bool $concluido,
        bool $erro,
        ?string $motivoErro
    ): self {
        return new self($id, $contaId, $valor, $agendadoPara, $concluido, $erro, $motivoErro);
    }

    public function id(): string { return $this->id; }
    public function contaId(): string { return $this->contaId; }
    public function valor(): Dinheiro { return $this->valor; }
    public function agendadoPara(): ?DateTimeImmutable { return $this->agendadoPara; }
    public function concluido(): bool { return $this->concluido; }
    public function erro(): bool { return $this->erro; }
    public function motivoErro(): ?string { return $this->motivoErro; }

    public function estaAgendado(): bool
    {
        return $this->agendadoPara !== null;
    }

    public function marcarComoConcluido(): void
    {
        $this->concluido = true;
    }

    public function marcarComoErro(string $motivo): void
    {
        $this->erro = true;
        $this->motivoErro = $motivo;
    }
}
