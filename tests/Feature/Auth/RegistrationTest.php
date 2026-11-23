<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** RF-001 / RF-002: registro de cuenta (adaptada de Breeze a SCFP). */
class RegistrationTest extends TestCase
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

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_new_users_can_register_and_must_verify_their_email(): void
    {
        $correo = 'nuevo.' . Str::lower(Str::random(8)) . '@scfp.test';

        $this->post(route('register'), [
            'nombres' => 'Ana María', 'apellidos' => 'Pérez Gómez', 'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(10000000, 99999999), 'correo' => $correo,
            'password' => 'Registro#2026x', 'password_confirmation' => 'Registro#2026x', 'acepta_politica' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $usuario = Usuario::where('correo', $correo)->first();
        $this->assertNotNull($usuario);
        $this->assertSame('PENDIENTE_VERIFICACION', $usuario->estado);
        $this->assertDatabaseHas('tokens_seguridad', ['usuario_id' => $usuario->id, 'tipo' => 'VERIFICACION_CORREO']);
        $this->assertDatabaseHas('aceptaciones_politica', ['usuario_id' => $usuario->id]);
        $this->assertGuest();
    }
}
