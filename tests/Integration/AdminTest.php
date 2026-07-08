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

    public function testAdminCriaNovaConta(): void
    {
        $tokenAdmin = $this->tokenAdmin();

        $resposta = $this->post('/admin/accounts', [
            'name'     => 'Conta Criada Pelo Admin',
            'email'    => 'criada-pelo-admin@teste.com',
            'password' => 'senha123',
            'balance'  => '50.00',
        ], $this->authHeader($tokenAdmin));

        $this->assertSame(201, $resposta->getStatusCode());
        $dados = json_decode((string) $resposta->getBody(), true);
        $this->assertSame('Conta Criada Pelo Admin', $dados['name']);
        $this->assertSame('50.00', $dados['balance']);

        $lista = $this->get('/admin/accounts', [], $this->authHeader($tokenAdmin));
        $this->assertCount(1, json_decode((string) $lista->getBody(), true));
    }

    public function testAdminNaoConsegueCriarContaComEmailDuplicado(): void
    {
        $tokenAdmin = $this->tokenAdmin();
        $dadosConta = [
            'name'     => 'Conta Duplicada',
            'email'    => 'duplicada@teste.com',
            'password' => 'senha123',
        ];

        $this->post('/admin/accounts', $dadosConta, $this->authHeader($tokenAdmin));
        $resposta = $this->post('/admin/accounts', $dadosConta, $this->authHeader($tokenAdmin));

        $this->assertSame(409, $resposta->getStatusCode());
    }

    public function testAdminNaoConsegueCriarContaSemTokenRetorna401(): void
    {
        $resposta = $this->post('/admin/accounts', [
            'name' => 'Sem Token', 'email' => 'semtoken@teste.com', 'password' => 'senha123',
        ]);
        $this->assertSame(401, $resposta->getStatusCode());
    }

    public function testAdminNaoConsegueCriarContaComDadosInvalidos(): void
    {
        $tokenAdmin = $this->tokenAdmin();
        $resposta = $this->post('/admin/accounts', [
            'name' => '', 'email' => 'invalido', 'password' => '123',
        ], $this->authHeader($tokenAdmin));

        $this->assertSame(422, $resposta->getStatusCode());
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
