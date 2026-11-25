<?php

namespace Tests\Feature;

use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\ValorDeducibleRegistro;
use App\Services\InversionService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H12: Retenciones > "Total bruto sujeto a descuento" es solo el rendimiento (sin el capital devuelto
 * de un CDT). H13: Utilidades > "Total valores deducibles generados" excluye la retencion en la fuente.
 */
class ReportesInversionesRetencionesUtilidadesTest extends TestCase
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

    /** CDT de 5.000.000: abono de interes 24/10 y redencion 24/11 (capital + rendimiento). */
    private function escenarioCdt(): Inversion
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $inv = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'CDT Davivienda', 'tipo' => 'CDT', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => '5000000.00', 'estado' => 'ACTIVA',
        ]);

        $abono = MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $inv->id, 'tipo' => 'ABONO_INTERES', 'fecha' => '2026-10-24',
            'monto' => '43137.98', 'monto_bruto' => '44935.40', 'retencion_aplicada' => '1797.42',
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => '43137.98',
        ]);
        InversionService::registrarPuentesTributarios($abono, $inv);

        $redencion = MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $inv->id, 'tipo' => 'REDENCION', 'fecha' => '2026-11-24',
            'monto' => '5221575.37', 'monto_bruto' => '5230807.68', 'retencion_aplicada' => '9232.31',
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => '5221575.37',
        ]);
        InversionService::registrarPuentesTributarios($redencion, $inv, ['parte_utilidad' => '230807.68']);

        return $inv;
    }

    public function test_h12_la_base_sujeta_a_retencion_es_solo_el_rendimiento(): void
    {
        $this->escenarioCdt();

        $json = $this->getJson(route('inversiones.reportes.retenciones', ['anio' => 2026]))->assertOk()->json();

        $this->assertEqualsWithDelta(275743.08, (float) $json['totales']['total_bruto'], 0.005);
        $this->assertEqualsWithDelta(11029.73, (float) $json['totales']['total_retencion'], 0.005);
        $this->assertEqualsWithDelta(275743.08, (float) $json['resumen_producto']['CDT']['total_bruto'], 0.005);
        foreach ($json['resumen_entidad'] as $entidad) {
            $this->assertEqualsWithDelta(275743.08, (float) $entidad['total_bruto'], 0.005);
        }
    }

    public function test_h13_los_valores_deducibles_no_incluyen_la_retencion(): void
    {
        $inv = $this->escenarioCdt();
        ValorDeducibleRegistro::create([
            'usuario_id' => $inv->usuario_id, 'inversion_id' => $inv->id, 'anio' => 2026,
            'concepto' => 'UTILIDAD_NO_GRAVADA_BVC', 'valor' => '60066.67',
        ]);
        $this->assertGreaterThan(0, ValorDeducibleRegistro::where('concepto', 'like', 'RETE%')->count(), 'el escenario genera retencion');

        $json = $this->getJson(route('inversiones.reportes.utilidades', ['anio' => 2026]))->assertOk()->json();

        $this->assertEqualsWithDelta(60066.67, (float) $json['totales']['deducibles_total'], 0.005);
    }

    public function test_t12b_sin_puente_ni_monto_bruto_el_movimiento_no_suma_su_monto_neto(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $inv = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'Fondo', 'tipo' => 'FIC', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => '1000000.00', 'estado' => 'ACTIVA',
        ]);
        MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $inv->id, 'tipo' => 'ABONO_INTERES', 'fecha' => '2026-10-24',
            'monto' => '43137.98', 'retencion_aplicada' => '1797.42',
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => '43137.98',
        ]);

        $json = $this->getJson(route('inversiones.reportes.retenciones', ['anio' => 2026]))->assertOk()->json();

        $this->assertEqualsWithDelta(0.0, (float) $json['totales']['total_bruto'], 0.005);
        $this->assertEqualsWithDelta(1797.42, (float) $json['totales']['total_retencion'], 0.005);
    }

    public function test_t12b_redencion_cdt_sin_puente_usa_solo_el_rendimiento(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $inv = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'CDT', 'tipo' => 'CDT', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => '5000000.00', 'estado' => 'ACTIVA',
        ]);
        MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $inv->id, 'tipo' => 'REDENCION', 'fecha' => '2026-11-24',
            'monto' => '5221575.37', 'monto_bruto' => '5230807.68', 'retencion_aplicada' => '9232.31',
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => '5221575.37',
        ]);

        $json = $this->getJson(route('inversiones.reportes.retenciones', ['anio' => 2026]))->assertOk()->json();

        $this->assertEqualsWithDelta(230807.68, (float) $json['totales']['total_bruto'], 0.005);
    }

    public function test_t12b_las_filas_de_deducibles_listadas_suman_el_total(): void
    {
        $inv = $this->escenarioCdt();
        ValorDeducibleRegistro::create([
            'usuario_id' => $inv->usuario_id, 'inversion_id' => $inv->id, 'anio' => 2026,
            'concepto' => 'UTILIDAD_NO_GRAVADA_BVC', 'valor' => '60066.67',
        ]);

        $json = $this->getJson(route('inversiones.reportes.utilidades', ['anio' => 2026]))->assertOk()->json();

        $filas = collect($json['deducibles']);
        $this->assertTrue($filas->every(fn ($d) => ! str_starts_with((string) $d['concepto'], 'RETE')));
        $this->assertEqualsWithDelta((float) $json['totales']['deducibles_total'], (float) $filas->sum('valor'), 0.005);
    }
}
