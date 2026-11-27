<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\ReglaAlertaService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * RF-057: una alerta inactiva no se evalúa, así que su correo nunca saldría.
 * Si llega "correo sí, activa no", la regla se guarda con el correo apagado.
 */
class ReglaAlertaCorreoInactivaTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-26 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_correo_de_una_alerta_inactiva_se_guarda_apagado(): void
    {
        $reglas = ReglaAlertaService::guardar($this->usuario->id, [
            ['tipo' => ReglaAlertaService::GASTOS_HORMIGA, 'activa' => false, 'enviar_correo' => true],
        ]);

        $this->assertFalse($reglas[ReglaAlertaService::GASTOS_HORMIGA]['activa']);
        $this->assertFalse($reglas[ReglaAlertaService::GASTOS_HORMIGA]['enviar_correo']);
    }

    public function test_correo_de_una_alerta_activa_se_conserva(): void
    {
        $reglas = ReglaAlertaService::guardar($this->usuario->id, [
            ['tipo' => ReglaAlertaService::GASTOS_HORMIGA, 'activa' => true, 'enviar_correo' => true],
        ]);

        $this->assertTrue($reglas[ReglaAlertaService::GASTOS_HORMIGA]['enviar_correo']);
    }

    public function test_desactivar_una_alerta_apaga_su_correo_aunque_no_se_envie(): void
    {
        ReglaAlertaService::guardar($this->usuario->id, [
            ['tipo' => ReglaAlertaService::GASTO_INUSUAL, 'activa' => true, 'enviar_correo' => true],
        ]);

        $reglas = ReglaAlertaService::guardar($this->usuario->id, [
            ['tipo' => ReglaAlertaService::GASTO_INUSUAL, 'activa' => false],
        ]);

        $this->assertFalse($reglas[ReglaAlertaService::GASTO_INUSUAL]['enviar_correo']);
    }
}
