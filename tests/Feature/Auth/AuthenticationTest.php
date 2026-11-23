<?php

namespace Tests\Feature\Auth;

use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** RF-003: inicio y cierre de sesión con el correo (adaptada de Breeze a SCFP). */
class AuthenticationTest extends TestCase
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

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $usuario = $this->crearUsuario();

        $this->post(route('login'), ['correo' => $usuario->correo, 'password' => $this->claveDePrueba])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $usuario = $this->crearUsuario();

        $this->post(route('login'), ['correo' => $usuario->correo, 'password' => 'Incorrecta#2026'])
            ->assertSessionHasErrors(['correo' => 'Correo o contraseña incorrectos']);
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $this->entrarComo($this->crearUsuario());

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }
}
