<?php
declare(strict_types=1);

namespace App\Domain\Saque;

use App\Domain\Saque\Exception\TipoPixInvalidoException;

class SaquePix implements MetodoDeSaque
{
    private const TIPOS_VALIDOS = ['email'];

    public function __construct(
        private readonly string $tipo,
        private readonly string $chave
    ) {
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new TipoPixInvalidoException("Tipo PIX '{$tipo}' não é suportado. Tipos aceitos: " . implode(', ', self::TIPOS_VALIDOS));
        }
    }

    public function obterTipo(): string
    {
        return 'PIX';
    }

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function chave(): string
    {
        return $this->chave;
    }
}
