<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\PagoSeguridadSocial;
use App\Services\InversionService;
use App\Services\Renta\ConsolidadoRentaService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * Seguimiento de T1/T4/T5 (H01-H03): sin doble conteo de retenciones, aportes
 * independientes de PILA y utilidad de venta sin fila puente.
 */
class RentaConsolidadoSeguimientoTest extends TestCase
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

    private function cdt(string $usuarioId): Inversion
    {
        return Inversion::create([
            'usuario_id' => $usuarioId, 'nombre' => 'CDT Davivienda', 'tipo' => 'CDT', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => '5000000.00', 'estado' => 'ACTIVA',
        ]);
    }

    public function test_retencion_de_rendimientos_se_cuenta_una_sola_vez_en_renta(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $inv = $this->cdt($u->id);
        $mov = MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $inv->id, 'tipo' => 'ABONO_INTERES', 'fecha' => '2026-10-24',
            'monto' => '43137.98', 'monto_bruto' => '44935.40', 'retencion_aplicada' => '1797.42',
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => '43137.98',
        ]);
        InversionService::registrarPuentesTributarios($mov, $inv);

        $datos = app(ConsolidadoRentaService::class)->consolidar($u, 2026);

        $this->assertEqualsWithDelta(1797.42, $datos['retenciones_practicadas']['total_cop'], 0.005);
    }

    public function test_aportes_de_nomina_y_pila_son_fuentes_independientes_y_se_suman(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $fuente = InversionService::fuenteDeRendimientos($u->id, 'Empleador', 'Salario', 'RENTAS_TRABAJO');
        Ingreso::create([
            'usuario_id' => $u->id, 'fuente_id' => $fuente->id, 'fecha_recepcion' => '2026-03-01',
            'clasificacion_tributaria' => 'RENTAS_TRABAJO', 'moneda' => 'COP', 'monto_original' => '3000000.00',
            'tasa_cambio_usada' => '1.0000', 'monto_bruto_cop' => '3000000.00', 'retencion_fuente_cop' => '0.00',
            'aportes_descontados_cop' => '240000.00', 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ]);
        PagoSeguridadSocial::create([
            'usuario_id' => $u->id, 'periodo' => '2026-10', 'fecha_pago' => '2026-11-24',
            'salud' => '450000', 'pension' => '576000', 'fsp' => '0', 'arl' => '0',
        ]);

        $datos = app(ConsolidadoRentaService::class)->consolidar($u, 2026);

        // Nomina del asalariado (RN-034.5 impide aplicarla a honorarios) + PILA del independiente.
        $this->assertEqualsWithDelta(1266000.0, $datos['incrngo']['salud_pension_cop'], 0.01);
    }

    public function test_venta_sin_fila_puente_usa_costo_promedio_ponderado(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $inv = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'PFBCOLOM', 'tipo' => 'ACCION_ETF', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => '4000000.00',
            'unidades_iniciales' => '100.00000000', 'precio_unitario_compra' => '40000.00000000', 'estado' => 'ACTIVA',
        ]);
        $inv->detalleRentaVariable()->create([
            'simbolo' => 'PFBCOLOM', 'nombre_instrumento' => 'Preferencial Bancolombia', 'tipo' => 'ACCION', 'mercado' => 'BVC',
            'esta_inscrito_bvc' => true, 'unidades_total' => '130.00000000', 'costo_promedio_unitario' => '40746.66670000',
            'fecha_primera_compra' => '2026-09-24', 'tipo_reparto' => 'DISTRIBUTIVO',
        ]);
        // Venta legada: sin registrarPuentesTributarios, no existe IngresoInversion UTILIDAD_VENTA.
        MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $inv->id, 'tipo' => 'VENTA', 'fecha' => '2026-11-22',
            'monto' => '875000.00', 'monto_bruto' => '880000.00', 'monto_cop' => '875000.00', 'tasa_cambio_usada' => '1.0000',
            'unidades' => '20.00000000', 'valor_unidad' => '44000.00000000', 'comision' => '5000.00',
        ]);

        $datos = app(ConsolidadoRentaService::class)->consolidar($u, 2026);

        $this->assertEqualsWithDelta(60066.67, $datos['ingresos_brutos']['utilidad_acciones_menor_2a_cop'], 0.005);
    }
}
