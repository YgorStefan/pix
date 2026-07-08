<?php
declare(strict_types=1);

namespace Tests\Integration;

class AdminTest extends IntegracaoTestCase
{
    public function testLoginAdminComCredenciaisCorretasRetornaToken(): void
    {
        $response = $this->post('/admin/login', [
            'email'    => (string) env('ADMIN_EMAIL'),
            'password' => 'admin123',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $dados = json_decode((string) $response->getBody(), true);
        $this->assertNotEmpty($dados['token']);
    }

    public function testLoginAdminComSenhaErradaRetorna401(): void
    {
        $response = $this->post('/admin/login', [
            'email'    => (string) env('ADMIN_EMAIL'),
            'password' => 'senha-errada',
        ]);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testRotasDeAdminSemTokenRetornam401(): void
    {
        $this->assertSame(401, $this->get('/admin/accounts')->getStatusCode());
    }

    public function testTokenDeContaComumNaoAcessaRotasDeAdmin(): void
    {
        $contaId = $this->criarConta('Ygor', '100.00');
        $tokenConta = $this->tokenParaConta($contaId);

        $response = $this->get('/admin/accounts', [], $this->authHeader($tokenConta));
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testAdminListaEditaEExcluiConta(): void
    {
        $contaId = $this->criarConta('Cliente Demo', '100.00');
        $tokenAdmin = $this->tokenAdmin();

        $lista = $this->get('/admin/accounts', [], $this->authHeader($tokenAdmin));
        $this->assertSame(200, $lista->getStatusCode());
        $this->assertCount(1, json_decode((string) $lista->getBody(), true));

        $edicao = $this->patch("/admin/accounts/{$contaId}/balance", ['balance' => '999.99'], $this->authHeader($tokenAdmin));
        $this->assertSame(200, $edicao->getStatusCode());
        $this->assertSame('999.99', $this->obterSaldo($contaId));

        $exclusao = $this->delete("/admin/accounts/{$contaId}", [], $this->authHeader($tokenAdmin));
        $this->assertSame(204, $exclusao->getStatusCode());
    }

    public function testAdminNaoConsegueExcluirContaComSaques(): void
    {
        $contaId = $this->criarConta('Cliente Com Saque', '500.00');
        $tokenConta = $this->tokenParaConta($contaId);
        $this->post('/account/me/balance/withdraw', [
            'method' => 'PIX', 'pix' => ['type' => 'email', 'key' => 'x@email.com'], 'amount' => 10.00, 'schedule' => null,
        ], $this->authHeader($tokenConta));

        $tokenAdmin = $this->tokenAdmin();
        $exclusao = $this->delete("/admin/accounts/{$contaId}", [], $this->authHeader($tokenAdmin));
        $this->assertSame(409, $exclusao->getStatusCode());
    }
}
