<?php

namespace Tests\Feature;

use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** RF-008 / RF-010: página del perfil, edición y solicitud de supresión (adaptada de Breeze a SCFP). */
class ProfileTest extends TestCase
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

    public function test_profile_page_is_displayed(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrarComo($usuario);

        $this->get(route('profile.show'))->assertOk()->assertSee($usuario->correo);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrarComo($usuario);

        $this->from(route('profile.show'))->patch(route('profile.update'), [
            'nombres' => 'Nombre Nuevo', 'apellidos' => 'Apellido Nuevo', 'fecha_nacimiento' => '1990-01-01', 'ciudad_residencia' => 'Cali',
            'personas_a_cargo' => 1, 'ocupaciones' => ['Contratista por prestación de servicios'], 'clase_riesgo_arl' => 1, 'responsable_iva' => 0,
        ])->assertSessionHas('status', 'Perfil actualizado');

        $this->assertSame('Nombre Nuevo', $usuario->fresh()->nombres);
        $this->assertSame('Cali', $usuario->fresh()->perfilTributarioActivo->ciudad_residencia);
    }

    public function test_user_can_request_account_deletion(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrarComo($usuario);

        $this->post(route('profile.delete.account'), ['password' => $this->claveDePrueba, 'confirmacion_palabra' => 'ELIMINAR'])
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame('PENDIENTE_SUPRESION', $usuario->fresh()->estado);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrarComo($usuario);

        $this->from(route('profile.show'))->post(route('profile.delete.account'), ['password' => 'Incorrecta#2026', 'confirmacion_palabra' => 'ELIMINAR'])
            ->assertSessionHasErrors(['password' => 'La contraseña no es correcta.']);
        $this->assertSame('ACTIVA', $usuario->fresh()->estado);
    }
}
