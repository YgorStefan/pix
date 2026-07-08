<?php
declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Domain\Conta\Exception\CredenciaisInvalidasException;

class AutenticarAdmin
{
    public function executar(string $email, string $senha): void
    {
        $emailAdmin = (string) env('ADMIN_EMAIL', '');
        $hashAdmin  = (string) env('ADMIN_PASSWORD_HASH', '');

        if ($emailAdmin === '' || $hashAdmin === '' || !hash_equals($emailAdmin, $email) || !password_verify($senha, $hashAdmin)) {
            throw new CredenciaisInvalidasException('E-mail ou senha inválidos.');
        }
    }
}
