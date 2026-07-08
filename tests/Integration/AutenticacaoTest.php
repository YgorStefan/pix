<?php
declare(strict_types=1);

namespace Tests\Integration;

class AutenticacaoTest extends IntegracaoTestCase
{
    public function testRegistraContaERecebeToken(): void
    {
        $response = $this->post('/auth/register', [
            'name'     => 'Ygor',
            'email'    => 'ygor@teste.com',
            'password' => 'senha123',
            'balance'  => '500.00',
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $dados = json_decode((string) $response->getBody(), true);
        $this->assertNotEmpty($dados['token']);
        $this->assertSame('Ygor', $dados['name']);
        $this->assertSame('ygor@teste.com', $dados['email']);
        $this->assertSame('500.00', $dados['balance']);
    }

    public function testRegistroComEmailDuplicadoRetorna409(): void
    {
        $body = ['name' => 'Ygor', 'email' => 'duplicado@teste.com', 'password' => 'senha123', 'balance' => '0'];
        $this->post('/auth/register', $body);
        $response = $this->post('/auth/register', $body);

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testRegistroComDadosInvalidosRetorna422(): void
    {
        $response = $this->post('/auth/register', [
            'name'     => '',
            'email'    => 'email-invalido',
            'password' => '123',
        ]);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testLoginComCredenciaisCorretasRetornaToken(): void
    {
        $this->post('/auth/register', [
            'name' => 'Maria', 'email' => 'maria@teste.com', 'password' => 'senha123', 'balance' => '100.00',
        ]);

        $response = $this->post('/auth/login', ['email' => 'maria@teste.com', 'password' => 'senha123']);

        $this->assertSame(200, $response->getStatusCode());
        $dados = json_decode((string) $response->getBody(), true);
        $this->assertNotEmpty($dados['token']);
    }

    public function testLoginComSenhaErradaRetorna401(): void
    {
        $this->post('/auth/register', [
            'name' => 'João', 'email' => 'joao@teste.com', 'password' => 'senha123', 'balance' => '0',
        ]);

        $response = $this->post('/auth/login', ['email' => 'joao@teste.com', 'password' => 'senha-errada']);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testLoginComEmailInexistenteRetorna401(): void
    {
        $response = $this->post('/auth/login', ['email' => 'naoexiste@teste.com', 'password' => 'qualquer']);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testFluxoCompletoRegistrarLogarConsultarESacar(): void
    {
        $registro = json_decode((string) $this->post('/auth/register', [
            'name' => 'Carlos', 'email' => 'carlos@teste.com', 'password' => 'senha123', 'balance' => '300.00',
        ])->getBody(), true);
        $token = $registro['token'];

        $me = $this->get('/account/me', [], $this->authHeader($token));
        $this->assertSame(200, $me->getStatusCode());
        $dadosMe = json_decode((string) $me->getBody(), true);
        $this->assertSame('carlos@teste.com', $dadosMe['email']);

        $saque = $this->post('/account/me/balance/withdraw', [
            'method' => 'PIX', 'pix' => ['type' => 'email', 'key' => 'destino@email.com'], 'amount' => 50.00, 'schedule' => null,
        ], $this->authHeader($token));
        $this->assertSame(201, $saque->getStatusCode());

        $historico = $this->get('/account/me/withdrawals', [], $this->authHeader($token));
        $this->assertSame(200, $historico->getStatusCode());
        $this->assertCount(1, json_decode((string) $historico->getBody(), true));

        $exclusao = $this->delete('/account/me', [], $this->authHeader($token));
        $this->assertSame(409, $exclusao->getStatusCode());
    }

    public function testExcluirContaSemSaquesRetorna204(): void
    {
        $registro = json_decode((string) $this->post('/auth/register', [
            'name' => 'Bia', 'email' => 'bia@teste.com', 'password' => 'senha123', 'balance' => '0',
        ])->getBody(), true);

        $response = $this->delete('/account/me', [], $this->authHeader($registro['token']));
        $this->assertSame(204, $response->getStatusCode());
    }
}
