<?php
declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Saque\SaquePix;
use App\Domain\Saque\Exception\TipoPixInvalidoException;
use PHPUnit\Framework\TestCase;

class SaquePixTest extends TestCase
{
    public function testCriaComEmailValido(): void
    {
        $pix = new SaquePix('email', 'fulano@email.com');
        $this->assertSame('PIX', $pix->obterTipo());
        $this->assertSame('email', $pix->tipo());
        $this->assertSame('fulano@email.com', $pix->chave());
    }

    public function testCriaComTodosOsTiposValidos(): void
    {
        $casos = [
            ['cpf',       '12345678900'],
            ['cnpj',      '12345678000190'],
            ['telefone',  '+5511999999999'],
            ['aleatoria', '550e8400-e29b-41d4-a716-446655440000'],
        ];
        foreach ($casos as [$tipo, $chave]) {
            $pix = new SaquePix($tipo, $chave);
            $this->assertSame('PIX', $pix->obterTipo());
            $this->assertSame($tipo, $pix->tipo());
        }
    }

    public function testTipoInvalidoLancaExcecao(): void
    {
        $this->expectException(TipoPixInvalidoException::class);
        new SaquePix('bitcoin', 'qualquer');
    }

    public function testOutrosTiposInvalidosLancamExcecao(): void
    {
        foreach (['ted', 'transferencia', 'debito', ''] as $tipo) {
            try {
                new SaquePix($tipo, 'qualquer');
                $this->fail("Esperava TipoPixInvalidoException para tipo '{$tipo}'");
            } catch (TipoPixInvalidoException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testMensagemDeErroContemTipoInvalido(): void
    {
        $this->expectException(TipoPixInvalidoException::class);
        $this->expectExceptionMessageMatches('/bitcoin/');
        new SaquePix('bitcoin', 'qualquer');
    }
}
