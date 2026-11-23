<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use App\Services\SecurityTokenService;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * RF-012: código MFA. Vence a los 10 min, admite 5 intentos, máximo 3 reenvíos
 * y entre reenvíos hay que esperar 60 s. Reloj simulado con Carbon::setTestNow.
 */
class CodigoMfaTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal();
        $this->usuario = $this->crearUsuario(null, ['mfa_activo' => true]);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function avanzar(int $segundos): void
    {
        Carbon::setTestNow(now()->addSeconds($segundos));
    }

    public function test_el_codigo_es_valido_hasta_los_10_minutos(): void
    {
        $codigo = SecurityTokenService::generarCodigoMFA($this->usuario)['codigo'];
        $this->avanzar(9 * 60 + 59);
        $this->assertTrue(SecurityTokenService::validarCodigoMFA($this->usuario, $codigo)['valido']);
    }

    public function test_el_codigo_vence_pasados_10_minutos(): void
    {
        $codigo = SecurityTokenService::generarCodigoMFA($this->usuario)['codigo'];
        $this->avanzar(10 * 60 + 1);
        $r = SecurityTokenService::validarCodigoMFA($this->usuario, $codigo);
        $this->assertFalse($r['valido']);
        $this->assertSame('El código expiró. Solicita uno nuevo.', $r['mensaje']);
    }

    public function test_cinco_intentos_fallidos_agotan_el_codigo(): void
    {
        $codigo = SecurityTokenService::generarCodigoMFA($this->usuario)['codigo'];
        $malo = $codigo === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 4; $i++) {
            $r = SecurityTokenService::validarCodigoMFA($this->usuario, $malo);
            $this->assertSame('Código incorrecto. Te quedan ' . (5 - $i) . ' intentos.', $r['mensaje']);
        }
        $r = SecurityTokenService::validarCodigoMFA($this->usuario, $malo);
        $this->assertSame('Has agotado el número máximo de intentos para este código.', $r['mensaje']);

        // Ya agotado, ni el código correcto sirve.
        $this->assertFalse(SecurityTokenService::validarCodigoMFA($this->usuario, $codigo)['valido']);
    }

    public function test_reenvio_exige_60_segundos_y_maximo_tres(): void
    {
        SecurityTokenService::generarCodigoMFA($this->usuario);

        $this->avanzar(59);
        $this->assertArrayHasKey('error', SecurityTokenService::generarCodigoMFA($this->usuario, true));

        for ($i = 1; $i <= 3; $i++) {
            $this->avanzar(60);
            $this->assertArrayHasKey('codigo', SecurityTokenService::generarCodigoMFA($this->usuario, true), "reenvío $i");
        }

        $this->avanzar(60);
        $r = SecurityTokenService::generarCodigoMFA($this->usuario, true);
        $this->assertStringStartsWith('Ya no hay más reenvíos de código permitidos', $r['error']);
    }

    public function test_tras_un_reenvio_el_codigo_anterior_no_sirve(): void
    {
        $anterior = SecurityTokenService::generarCodigoMFA($this->usuario)['codigo'];
        $this->avanzar(60);
        $nuevo = SecurityTokenService::generarCodigoMFA($this->usuario, true)['codigo'];

        if ($anterior !== $nuevo) {
            $this->assertFalse(SecurityTokenService::validarCodigoMFA($this->usuario, $anterior)['valido']);
        }
        $this->assertTrue(SecurityTokenService::validarCodigoMFA($this->usuario, $nuevo)['valido']);
    }
}
