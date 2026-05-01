<?php
declare(strict_types=1);

namespace App\Domain\Saque;

use App\Domain\Saque\Exception\ValorInvalidoException;

final class Dinheiro
{
    private function __construct(private readonly int $centavos)
    {
        if ($centavos < 0) {
            throw new ValorInvalidoException('Valor monetário não pode ser negativo.');
        }
    }

    public static function deCentavos(int $centavos): self
    {
        return new self($centavos);
    }

    public static function deDecimal(string $valor): self
    {
        $centavos = (int) bcmul($valor, '100', 0);
        return new self($centavos);
    }

    public function centavos(): int
    {
        return $this->centavos;
    }

    public function toDecimal(): string
    {
        return number_format($this->centavos / 100, 2, '.', '');
    }

    public function subtrair(self $outro): self
    {
        $resultado = $this->centavos - $outro->centavos;
        if ($resultado < 0) {
            throw new ValorInvalidoException('Resultado da subtração não pode ser negativo.');
        }
        return new self($resultado);
    }

    public function menorQue(self $outro): bool
    {
        return $this->centavos < $outro->centavos;
    }

    public function igual(self $outro): bool
    {
        return $this->centavos === $outro->centavos;
    }
}
