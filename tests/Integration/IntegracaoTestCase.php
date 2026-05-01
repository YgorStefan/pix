<?php
declare(strict_types=1);

namespace Tests\Integration;

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
                $response = \Hyperf\Support\make(\Hyperf\Testing\Http\Client::class)->{$method}(...$args);
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
        Db::table('account')->insert(['id' => $id, 'name' => $nome, 'balance' => $saldo]);
        return $id;
    }

    protected function obterSaldo(string $contaId): string
    {
        return Db::table('account')->where('id', $contaId)->value('balance');
    }
}
