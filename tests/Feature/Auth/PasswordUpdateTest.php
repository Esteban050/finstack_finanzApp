<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Hash;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** RF-007: cambio de contraseña desde el perfil (adaptada de Breeze a SCFP). */
class PasswordUpdateTest extends TestCase
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

    public function test_password_can_be_updated(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrarComo($usuario);

        $this->from(route('profile.show'))->put(route('password.custom.change'), [
            'current_password' => $this->claveDePrueba, 'password' => 'NuevaClave#2027x', 'password_confirmation' => 'NuevaClave#2027x',
        ])->assertSessionHas('status', 'Contraseña actualizada');
        $this->assertTrue(Hash::check('NuevaClave#2027x', $usuario->fresh()->password_hash));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrarComo($usuario);

        $this->from(route('profile.show'))->put(route('password.custom.change'), [
            'current_password' => 'Incorrecta#2026', 'password' => 'NuevaClave#2027x', 'password_confirmation' => 'NuevaClave#2027x',
        ])->assertSessionHasErrors(['current_password' => 'La contraseña actual no es correcta']);
        $this->assertTrue(Hash::check($this->claveDePrueba, $usuario->fresh()->password_hash));
    }
}
