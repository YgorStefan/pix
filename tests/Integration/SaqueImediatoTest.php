<?php
declare(strict_types=1);

namespace Tests\Integration;

use Hyperf\DbConnection\Db;

class SaqueImediatoTest extends IntegracaoTestCase
{
    public function testSaqueImediatoDedusSaldoERegistraNoBanco(): void
    {
        $contaId = $this->criarConta('João', '500.00');

        $response = $this->post("/account/{$contaId}/balance/withdraw", [
            'method'   => 'PIX',
            'pix'      => ['type' => 'email', 'key' => 'joao@email.com'],
            'amount'   => 150.75,
            'schedule' => null,
        ]);

        $this->assertSame(201, $response->getStatusCode());

        $dados = json_decode((string) $response->getBody(), true);
        $this->assertTrue($dados['done']);
        $this->assertFalse($dados['scheduled']);

        $saldo = $this->obterSaldo($contaId);
        $this->assertSame('349.25', number_format((float) $saldo, 2, '.', ''));

        $saque = Db::table('account_withdraw')->where('account_id', $contaId)->first();
        $this->assertNotNull($saque);
        $this->assertSame('PIX', $saque->method);
        $this->assertSame('150.75', number_format((float) $saque->amount, 2, '.', ''));
        $this->assertTrue((bool) $saque->done);

        $pix = Db::table('account_withdraw_pix')->where('account_withdraw_id', $saque->id)->first();
        $this->assertNotNull($pix);
        $this->assertSame('email', $pix->type);
        $this->assertSame('joao@email.com', $pix->key);
    }

    public function testSaqueRecusadoPorSaldoInsuficiente(): void
    {
        $contaId = $this->criarConta('Maria', '50.00');

        $response = $this->post("/account/{$contaId}/balance/withdraw", [
            'method'   => 'PIX',
            'pix'      => ['type' => 'email', 'key' => 'maria@email.com'],
            'amount'   => 100.00,
            'schedule' => null,
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $saldo = $this->obterSaldo($contaId);
        $this->assertSame('50.00', number_format((float) $saldo, 2, '.', ''));
    }

    public function testContaNaoEncontradaRetorna404(): void
    {
        $response = $this->post('/account/00000000-0000-0000-0000-000000000000/balance/withdraw', [
            'method'   => 'PIX',
            'pix'      => ['type' => 'email', 'key' => 'x@email.com'],
            'amount'   => 10.00,
            'schedule' => null,
        ]);
        $this->assertSame(404, $response->getStatusCode());
    }
}
