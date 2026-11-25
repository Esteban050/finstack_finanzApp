<?php

namespace Tests\Feature;

use App\Models\Gasto;
use App\Models\Usuario;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H11 (decisión: reconocer al comprar): pagar una cuota de una tarjeta no crea
 * un gasto por el capital, que ya se registró con la compra; solo los intereses
 * (y mora, seguros y cargos) son gasto de Obligaciones financieras.
 */
class PagoTarjetaGastoSoloInteresesTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-23 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function credito(string $tipo): array
    {
        return $this->postJson('/creditos', [
            'tipo_credito' => $tipo, 'banco' => 'Banco QA', 'proposito' => 'Prueba',
            'monto' => '1000000', 'tasa' => '2', 'tipo_tasa' => 'MENSUAL', 'numero_cuotas' => 12,
            'periodicidad' => 'MENSUAL', 'fecha_inicio' => '2026-10-23',
        ])->assertCreated()->json('data');
    }

    private function pagarCuota(array $credito): array
    {
        return $this->postJson("/creditos/{$credito['id']}/pagos", [
            'fecha' => '2026-11-23', 'valor' => $credito['calculo']['cuota'], 'medio' => 'TRANSFERENCIA',
        ])->assertCreated()->json('data');
    }

    public function test_el_pago_de_una_cuota_de_tarjeta_solo_crea_gasto_por_los_intereses(): void
    {
        $credito = $this->credito('TARJETA_CREDITO');
        $respuesta = $this->pagarCuota($credito);

        // Interés de la cuota 1: 1.000.000 x 2 % = 20.000; el resto es capital ya gastado en la compra.
        $this->assertSame('20000', array_column($respuesta['pago']['imputaciones'], 'valor', 'concepto')['INTERES_CORRIENTE']);
        $gasto = Gasto::with('subcategoria')->findOrFail($respuesta['pago']['gasto_id']);
        $this->assertSame('20000.00', $gasto->monto_cop);
        $this->assertSame('Cuota de crédito / hipoteca', $gasto->subcategoria->nombre);
        $this->assertSame(1, Gasto::where('usuario_id', $this->usuario->id)->count());
    }

    public function test_el_pago_de_una_cuota_de_libre_inversion_conserva_el_gasto_por_el_valor_completo(): void
    {
        $credito = $this->credito('LIBRE_INVERSION');
        $respuesta = $this->pagarCuota($credito);

        $gasto = Gasto::findOrFail($respuesta['pago']['gasto_id']);
        $this->assertSame(number_format((float) $credito['calculo']['cuota'], 2, '.', ''), $gasto->monto_cop);
    }
}
