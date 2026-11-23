<?php

namespace Tests\Feature\Auth;

use App\Services\SecurityTokenService;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** RF-005: recuperación de contraseña con enlace (adaptada de Breeze a SCFP). */
class PasswordResetTest extends TestCase
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

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $usuario = $this->crearUsuario(null);

        $this->post(route('password.email'), ['correo' => $usuario->correo])
            ->assertSessionHas('status', 'Si el correo está registrado, recibirás un enlace');
        $this->assertDatabaseHas('tokens_seguridad', ['usuario_id' => $usuario->id, 'tipo' => 'RECUPERACION_PASS']);
    }

    public function test_unknown_email_gets_the_same_answer(): void
    {
        $this->post(route('password.email'), ['correo' => 'no.existe@scfp.test'])
            ->assertSessionHas('status', 'Si el correo está registrado, recibirás un enlace');
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $usuario = $this->crearUsuario(null);
        $token = SecurityTokenService::generarTokenRecuperacion($usuario);

        $this->get(route('password.reset.custom', ['token' => $token]))->assertOk();
        $this->post(route('password.update.custom'), ['token' => $token, 'password' => 'NuevaClave#2027x', 'password_confirmation' => 'NuevaClave#2027x'])
            ->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NuevaClave#2027x', $usuario->fresh()->password_hash));
    }
}
