<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Auth\TokenService;
use Hyperf\DbConnection\Db;
use Hyperf\Testing\TestCase;
use Ramsey\Uuid\Uuid;

abstract class IntegracaoTestCase extends TestCase
{
    /**
     * PHPUnit 10 tornou runTest() private, quebrando o mecanismo de coroutine do Hyperf.
     * Sobrescrevemos doRequest() para que cada chamada HTTP rode dentro de um contexto
     * de coroutine Swoole, necessário para wait() → Channel->pop() funcionar.
     */
    protected function doRequest(string $method, ...$args): \Hyperf\Testing\Http\TestResponse
    {
        $response  = null;
        $exception = null;

        \Swoole\Coroutine\run(function () use ($method, $args, &$response, &$exception) {
            try {
                $response = \Hyperf\Support\make(\Tests\Integration\Support\TestClient::class)->{$method}(...$args);
            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw $exception;
        }

        return new \Hyperf\Testing\Http\TestResponse($response);
    }

    protected function setUp(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Extensão Swoole não disponível neste ambiente.');
        }
        parent::setUp();
        Db::table('account_withdraw_pix')->delete();
        Db::table('account_withdraw')->delete();
        Db::table('account')->delete();
    }

    protected function criarConta(string $nome, string $saldo): string
    {
        $id = Uuid::uuid4()->toString();
        Db::table('account')->insert([
            'id'            => $id,
            'name'          => $nome,
            'balance'       => $saldo,
            'email'         => "{$id}@teste.com",
            'password_hash' => password_hash('senha123', PASSWORD_BCRYPT),
        ]);
        return $id;
    }

    protected function obterSaldo(string $contaId): string
    {
        return Db::table('account')->where('id', $contaId)->value('balance');
    }

    protected function tokenParaConta(string $contaId): string
    {
        return make(TokenService::class)->gerar($contaId, 'conta');
    }

    protected function tokenAdmin(): string
    {
        return make(TokenService::class)->gerar((string) env('ADMIN_EMAIL'), 'admin');
    }

    protected function authHeader(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }
}
