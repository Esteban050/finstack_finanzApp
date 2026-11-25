<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Services\CalculoImpuestoInversionService;
use App\Services\InversionService;
use App\Services\Renta\ConsolidadoRentaService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H02: Renta y Los Ingresos usan un único método de costo (promedio ponderado)
 * para la utilidad en la venta de acciones.
 */
class RentaUtilidadVentaCostoPromedioTest extends TestCase
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

    public function test_renta_usa_la_utilidad_a_costo_promedio_ponderado_igual_que_ingresos(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);

        // Compra 100 x 40.000; compra 50 x 42.000 + 12.000 de comisión -> costo promedio 40.746,6667.
        $inv = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'PFBCOLOM', 'tipo' => 'ACCION_ETF', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => '4000000.00',
            'unidades_iniciales' => '100.00000000', 'precio_unitario_compra' => '40000.00000000', 'estado' => 'ACTIVA',
        ]);
        $inv->detalleRentaVariable()->create([
            'simbolo' => 'PFBCOLOM', 'nombre_instrumento' => 'Preferencial Bancolombia', 'tipo' => 'ACCION', 'mercado' => 'BVC',
            'esta_inscrito_bvc' => true, 'unidades_total' => '150.00000000', 'costo_promedio_unitario' => '40746.66670000',
            'fecha_primera_compra' => '2026-09-24', 'tipo_reparto' => 'DISTRIBUTIVO',
        ]);

        // Venta 22/11: 20 x 44.000, comisión 5.000.
        $venta = CalculoImpuestoInversionService::calcularVentaAcciones(
            unidadesVendidas: '20', precioVenta: '44000', costoPromedioUnitario: '40746.6667', comisionVenta: '5000',
            estaInscritoBvc: true, fechaPrimeraCompra: '2026-09-24', fechaVenta: '2026-11-22',
        );
        $this->assertSame('60066.67', $venta['utilidad_cop']);

        $mov = MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $inv->id, 'tipo' => 'VENTA', 'fecha' => '2026-11-22',
            'monto' => '875000.00', 'monto_bruto' => '880000.00', 'monto_cop' => '875000.00', 'tasa_cambio_usada' => '1.0000',
            'unidades' => '20.00000000', 'valor_unidad' => '44000.00000000', 'comision' => '5000.00',
        ]);
        InversionService::registrarPuentesTributarios($mov, $inv, [
            'utilidad_cop' => $venta['utilidad_cop'], 'utilidad_original' => $venta['utilidad_cop'],
            'clase_fiscal' => $venta['clase_fiscal'], 'anios_tenencia' => 0,
        ]);

        $ingreso = Ingreso::findOrFail($mov->fresh()->ingreso_id);
        $this->assertSame('60066.67', (string) $ingreso->monto_bruto_cop);

        $datos = app(ConsolidadoRentaService::class)->consolidar($u, 2026);

        $this->assertEqualsWithDelta(60066.67, $datos['ingresos_brutos']['utilidad_acciones_menor_2a_cop'], 0.005);
        $this->assertEqualsWithDelta(60066.67, $datos['incrngo']['utilidad_exenta_bvc_cop'], 0.005);
    }
}
