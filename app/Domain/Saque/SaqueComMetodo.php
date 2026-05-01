<?php
declare(strict_types=1);

namespace App\Domain\Saque;

final class SaqueComMetodo
{
    public function __construct(
        public readonly Saque $saque,
        public readonly MetodoDeSaque $metodo
    ) {}
}
