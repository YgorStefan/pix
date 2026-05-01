<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Hyperf\DbConnection\Db;

class SaqueAgendadoTest extends IntegracaoTestCase
{
    public function testSaqueAgendadoNaoDeduzSaldoImediatamente(): void
    {
        $contaId = $this->criarConta('Pedro', '300.00');
        $futuro  = (new DateTimeImmutable('+2 hours', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i');

        $response = $this->post("/account/{$contaId}/balance/withdraw", [
            'method'   => 'PIX',
            'pix'      => ['type' => 'email', 'key' => 'pedro@email.com'],
            'amount'   => 100.00,
            'schedule' => $futuro,
        ]);

        $this->assertSame(201, $response->getStatusCode());

        $dados = json_decode((string) $response->getBody(), true);
        $this->assertTrue($dados['scheduled']);
        $this->assertFalse($dados['done']);

        $saldo = $this->obterSaldo($contaId);
        $this->assertSame('300.00', number_format((float) $saldo, 2, '.', ''));
    }

    public function testAgendamentoNoPassadoRetorna422(): void
    {
        $contaId = $this->criarConta('Ana', '300.00');

        $response = $this->post("/account/{$contaId}/balance/withdraw", [
            'method'   => 'PIX',
            'pix'      => ['type' => 'email', 'key' => 'ana@email.com'],
            'amount'   => 50.00,
            'schedule' => '2020-01-01 10:00',
        ]);

        $this->assertSame(422, $response->getStatusCode());
    }
}
