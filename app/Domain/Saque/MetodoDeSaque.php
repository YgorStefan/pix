<?php
declare(strict_types=1);

namespace App\Domain\Saque;

interface MetodoDeSaque
{
    public function obterTipo(): string;
}
