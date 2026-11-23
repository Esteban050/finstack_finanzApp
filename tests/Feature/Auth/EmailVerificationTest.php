<?php

namespace Tests\Feature\Auth;

use App\Services\SecurityTokenService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** RF-002: verificación del correo con el enlace (adaptada de Breeze a SCFP). */
class EmailVerificationTest extends TestCase
{
    use UsaBaseLocal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal();
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_email_can_be_verified_with_the_link(): void
    {
        $usuario = $this->crearUsuario(null, ['estado' => 'PENDIENTE_VERIFICACION']);
        $token = SecurityTokenService::generarTokenVerificacion($usuario)['token_plano'];

        $this->get(route('verification.verify.token', ['token' => $token]))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Cuenta verificada');
        $this->assertSame('ACTIVA', $usuario->fresh()->estado);
    }

    public function test_email_is_not_verified_with_an_invalid_link(): void
    {
        $usuario = $this->crearUsuario(null, ['estado' => 'PENDIENTE_VERIFICACION']);
        SecurityTokenService::generarTokenVerificacion($usuario);

        $this->get(route('verification.verify.token', ['token' => str_repeat('0', 32)]))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors();
        $this->assertSame('PENDIENTE_VERIFICACION', $usuario->fresh()->estado);
    }
}
