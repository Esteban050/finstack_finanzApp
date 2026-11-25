<?php

namespace Tests\Feature;

use App\Models\PagoSeguridadSocial;
use App\Services\Renta\ConsolidadoRentaService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H01: el INCRNGO "Salud y pensión obligatorias" de Renta suma la salud y la
 * pensión de los pagos PILA del año gravable.
 */
class RentaIncrngoSeguridadSocialTest extends TestCase
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

    private function pagar(string $usuarioId, string $periodo, string $fechaPago, string $salud, string $pension): void
    {
        PagoSeguridadSocial::create([
            'usuario_id' => $usuarioId, 'periodo' => $periodo, 'fecha_pago' => $fechaPago,
            'salud' => $salud, 'pension' => $pension, 'fsp' => '0', 'arl' => '0',
        ]);
    }

    public function test_pila_pagada_alimenta_el_incrngo_de_salud_y_pension(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $this->pagar($u->id, '2026-10', '2026-11-24', '450000', '576000');
        // Otro año gravable: no debe sumar al 2026.
        $this->pagar($u->id, '2025-12', '2026-01-10', '100', '200');
        $this->pagar($u->id, '2025-11', '2025-12-10', '999', '999');

        $datos = app(ConsolidadoRentaService::class)->consolidar($u, 2026);

        $this->assertEqualsWithDelta(1026300.0, $datos['incrngo']['salud_pension_cop'], 0.01);
    }
}
