<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Services\InversionService;
use App\Services\Renta\EstimacionRentaService;
use App\Services\Reportes\DashboardResumenService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H22: el Resumen muestra la ejecucion del presupuesto a 2 decimales (como Presupuestos y Reportes), el % de
 * valorizacion redondea en vez de truncar, y el ahorro por inversiones de Renta usa la retencion exacta.
 */
class RedondeosConsistentesH22Test extends TestCase
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

    public function test_el_resumen_muestra_la_ejecucion_del_presupuesto_con_dos_decimales(): void
    {
        $u = $this->crearUsuario();
        $this->entrarComo($u);
        $padre = new Categoria;
        $padre->forceFill(['usuario_id' => $u->id, 'tipo' => 'GASTO', 'nombre' => 'P '.Str::random(6), 'es_predeterminada' => false, 'activa' => true, 'creado_en' => now()])->save();
        $sub = new Categoria;
        $sub->forceFill([
            'usuario_id' => $u->id, 'categoria_padre_id' => $padre->id, 'tipo' => 'GASTO', 'nombre' => 'S '.Str::random(6),
            'naturaleza_sugerida' => 'VARIABLE', 'necesidad_sugerida' => 'DISCRECIONAL', 'es_predeterminada' => false, 'activa' => true, 'creado_en' => now(),
        ])->save();
        (new Gasto)->forceFill([
            'usuario_id' => $u->id, 'categoria_id' => $padre->id, 'subcategoria_id' => $sub->id,
            'fecha' => '2026-11-05', 'moneda' => 'COP', 'monto_original' => '3465390', 'tasa_cambio_usada' => '1.0000',
            'monto_cop' => '3465390', 'medio_pago' => 'EFECTIVO', 'naturaleza' => 'VARIABLE', 'necesidad' => 'DISCRECIONAL',
            'relevancia_tributaria' => 'NO_DEDUCIBLE', 'etiquetas' => [], 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false, 'trasladar_saldo' => false,
            'lineas' => [['categoria_id' => $sub->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '3160000']],
        ])->assertSuccessful();

        $datos = app(DashboardResumenService::class)->obtenerDashboard($u, 'mes-actual', null, null, CarbonImmutable::parse('2026-11-24'));
        $kpi = collect($datos['kpis'])->firstWhere('clave', 'ejecucion_presupuesto');

        $this->assertEqualsWithDelta(109.66, $kpi['valor'], 0.00001);
        $this->assertSame('109.66%', $kpi['valor_formateado']);
    }

    public function test_el_porcentaje_de_valoracion_redondea_en_vez_de_truncar(): void
    {
        $u = $this->crearUsuario();
        $accion = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'Accion', 'tipo' => 'ACCION_ETF', 'moneda' => 'COP',
            'fecha_apertura' => '2026-01-01', 'monto_inicial' => 1000.00, 'unidades_iniciales' => '10.00000000',
            'precio_unitario_compra' => '100.00000000', 'estado' => 'ACTIVA',
        ]);
        $accion->detalleRentaVariable()->create([
            'simbolo' => 'XX', 'nombre_instrumento' => 'XX', 'tipo' => 'ACCION', 'mercado' => 'BVC',
            'unidades_total' => '10.00000000', 'costo_promedio_unitario' => '100.00000000',
            'valor_actual_unidad' => '106.75700000', 'fecha_primera_compra' => '2026-01-01', 'tipo_reparto' => 'NO_APLICA',
        ]);

        $vnr = InversionService::valoracionNoRealizada($accion->fresh());

        $this->assertSame('6.76', $vnr['porcentaje']);
    }

    public function test_el_ahorro_por_inversiones_usa_la_retencion_exacta_y_el_saldo_sigue_a_miles(): void
    {
        $u = $this->crearUsuario();
        $this->entrarComo($u);
        $cdt = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'CDT', 'tipo' => 'CDT', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => '5000000.00', 'estado' => 'ACTIVA',
        ]);
        MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $cdt->id, 'tipo' => 'ABONO_INTERES', 'fecha' => '2026-10-24',
            'monto' => '43137.98', 'monto_bruto' => '44935.40', 'retencion_aplicada' => '1797.42',
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => '43137.98',
        ]);

        $e = app(EstimacionRentaService::class)->estimar($u, 2026);

        $this->assertEqualsWithDelta(1797.42, $e['impacto_inversiones']['retenciones_inversiones_cop'], 0.001);
        $this->assertEqualsWithDelta(1797.42, $e['impacto_inversiones']['ahorro_total_cop'] - $e['impacto_inversiones']['ahorro_impuesto_directo_cop'], 0.001);
        $this->assertEqualsWithDelta(2000.0, $e['paso8']['retenciones_totales_cop'], 0.001);
    }
}
