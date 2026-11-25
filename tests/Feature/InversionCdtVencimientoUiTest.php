<?php

namespace Tests\Feature;

use App\Models\Inversion;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * T20b (H04): el minimo de fecha del formulario usa la misma fuente de vencimiento que el servidor
 * (detalleCdt primero) y se restablece sobre el mismo input.
 */
class InversionCdtVencimientoUiTest extends TestCase
{
    use UsaBaseLocal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_el_minimo_de_redencion_usa_el_vencimiento_del_detalle_cdt(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $cdt = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'CDT Davivienda', 'tipo' => 'CDT', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => 5000000.00, 'tasa_ea' => '11.5000',
            'plazo_dias' => 180, 'estado' => 'ACTIVA',
        ]);
        $cdt->detalleCdt()->create([
            'capital' => '5000000.00', 'tasa_valor' => '11.5000', 'tipo_tasa' => 'EA', 'plazo_dias' => 180,
            'base_dias' => 365, 'fecha_vencimiento' => '2027-03-23', 'periodicidad_pago' => 'VENCIMIENTO',
        ]);

        $html = $this->get(route('inversiones.show', $cdt->id))->assertOk()->getContent();

        $this->assertStringContainsString("fechaEl.min = '2027-03-23'", $html);
        $this->assertStringContainsString("fechaTrxEl.min = '2026-09-24'", $html);
        $this->assertStringContainsString("document.getElementById('fecha') || document.getElementById('trx_fecha')", $html);
    }

    public function test_t12b_sin_detalle_cdt_el_minimo_cae_al_vencimiento_de_la_inversion(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $cdt = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'CDT sin detalle', 'tipo' => 'CDT', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => 5000000.00, 'tasa_ea' => '11.5000',
            'plazo_dias' => 180, 'fecha_vencimiento' => '2027-03-20', 'estado' => 'ACTIVA',
        ]);
        $this->assertNull($cdt->detalleCdt);

        $html = $this->get(route('inversiones.show', $cdt->id))->assertOk()->getContent();

        $this->assertStringContainsString("fechaEl.min = '2027-03-20'", $html);
    }
}
