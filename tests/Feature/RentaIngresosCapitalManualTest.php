<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Services\Renta\ConsolidadoRentaService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * RF-043: los ingresos clasificados como RENTAS_CAPITAL registrados en el módulo de Ingresos
 * suman a las rentas de capital y a los ingresos brutos (C2), sin duplicar los rendimientos
 * que ya entran desde Inversiones ni aplicarles el componente inflacionario (Art. 40-1 E.T.).
 */
class RentaIngresosCapitalManualTest extends TestCase
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

    private function ingreso(string $usuarioId, string $fuente, string $clasificacion, string $fecha, string $bruto, string $retencion = '0.00'): void
    {
        (new Ingreso)->forceFill([
            'usuario_id' => $usuarioId, 'fuente_id' => $fuente, 'fecha_recepcion' => $fecha,
            'clasificacion_tributaria' => $clasificacion, 'moneda' => 'COP', 'monto_original' => $bruto,
            'tasa_cambio_usada' => '1.0000', 'monto_bruto_cop' => $bruto, 'retencion_fuente_cop' => $retencion,
            'aportes_descontados_cop' => '0.00', 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
    }

    public function test_ingreso_de_capital_manual_suma_a_capital_y_a_ingresos_brutos(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $fuente = $this->crearFuente($u);

        $this->ingreso($u->id, $fuente, 'RENTAS_CAPITAL', '2026-11-18', '3000000.00', '360000.00');
        $this->ingreso($u->id, $fuente, 'RENTAS_CAPITAL', '2025-11-18', '999999.00'); // otro año: no cuenta

        $consolidado = app(ConsolidadoRentaService::class)->consolidar($u, 2026);
        $brutos = $consolidado['ingresos_brutos'];

        $this->assertEqualsWithDelta(3000000.0, $brutos['capital_inversiones_cop'], 0.005);
        $this->assertEqualsWithDelta(3000000.0, $brutos['total_cop'], 0.005);
        $this->assertCount(1, $brutos['drill_down']['capital']);
        $this->assertEqualsWithDelta(360000.0, $consolidado['retenciones_practicadas']['total_cop'], 0.005);
        // Arriendos, regalías, etc. no son rendimientos financieros: sin componente inflacionario.
        $this->assertEqualsWithDelta(0.0, $consolidado['incrngo']['componente_inflacionario_cop'], 0.005);
    }

    public function test_rendimiento_de_inversion_cuenta_una_sola_vez_como_capital(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);

        $cuenta = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'Cuenta rendimiento', 'tipo' => 'CUENTA_ALTO_RENDIMIENTO', 'moneda' => 'COP',
            'fecha_apertura' => '2026-01-01', 'monto_inicial' => '10000000.00', 'estado' => 'ACTIVA',
        ]);

        $this->post(route('inversiones.movimientos.store', $cuenta->id), [
            'tipo' => 'ABONO_INTERES', 'fecha' => '2026-10-31', 'monto' => '100000', 'moneda' => 'COP',
        ])->assertSessionHasNoErrors();

        $movimiento = MovimientoInversion::where('inversion_id', $cuenta->id)->where('tipo', 'ABONO_INTERES')->firstOrFail();
        $this->assertNotNull($movimiento->ingreso_id, 'El abono debe generar su ingreso RENTAS_CAPITAL.');

        $brutos = app(ConsolidadoRentaService::class)->consolidar($u, 2026)['ingresos_brutos'];

        $this->assertEqualsWithDelta(100000.0, $brutos['capital_inversiones_cop'], 0.005);
        $this->assertEqualsWithDelta(100000.0, $brutos['total_cop'], 0.005);
    }
}
