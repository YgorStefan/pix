<?php
declare(strict_types=1);

namespace App\Infrastructure\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenService
{
    private const ALGORITMO = 'HS256';
    private const TTL_SEGUNDOS = 7 * 24 * 60 * 60;

    public function gerar(string $subject, string $role): string
    {
        $agora = time();
        return JWT::encode([
            'sub'  => $subject,
            'role' => $role,
            'iat'  => $agora,
            'exp'  => $agora + self::TTL_SEGUNDOS,
        ], $this->chave(), self::ALGORITMO);
    }

    /** @return array{sub: string, role: string} */
    public function validar(string $token): array
    {
        $payload = JWT::decode($token, new Key($this->chave(), self::ALGORITMO));
        return (array) $payload;
    }

    private function chave(): string
    {
        return (string) env('APP_KEY', '');
    }
}
