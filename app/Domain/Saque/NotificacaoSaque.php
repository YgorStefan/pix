<?php
declare(strict_types=1);

namespace App\Domain\Saque;

interface NotificacaoSaque
{
    public function enviar(Saque $saque, MetodoDeSaque $metodo): void;
}
