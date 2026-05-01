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

    public function testTipoInvalidoLancaExcecao(): void
    {
        $this->expectException(TipoPixInvalidoException::class);
        new SaquePix('cpf', '123.456.789-00');
    }

    public function testOutrosTiposInvalidosLancamExcecao(): void
    {
        foreach (['telefone', 'cnpj', 'aleatoria', ''] as $tipo) {
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
        $this->expectExceptionMessageMatches('/telefone/');
        new SaquePix('telefone', '11999999999');
    }
}
