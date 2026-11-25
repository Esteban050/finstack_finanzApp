<?php

namespace Tests\Feature;

use App\Models\Credito;
use App\Models\Usuario;
use App\Services\CreditoService;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H16 (decisión: días reales, como ABONO_CAPITAL): un pago antes del vencimiento de la cuota
 * ("Anticipar cuotas") cobra el interés corriente causado por días reales; el resto va a capital
 * y el plan se regenera igual que en el abono a capital (misma cuota, menos plazo).
 */
class PagoAdelantadoInteresPorDiasTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function libreInversion(): array
    {
        return $this->postJson('/creditos', [
            'tipo_credito' => 'LIBRE_INVERSION', 'banco' => 'Banco QA', 'proposito' => 'Libre inversión',
            'monto' => '10000000', 'tasa' => '18', 'tipo_tasa' => 'EA', 'numero_cuotas' => 36,
            'periodicidad' => 'MENSUAL', 'fecha_inicio' => '2026-11-24', 'fecha_primer_pago' => '2026-12-24',
        ])->assertCreated()->json('data');
    }

    private function pagar(array $credito, string $fecha, string $valor): array
    {
        return $this->postJson("/creditos/{$credito['id']}/pagos", [
            'fecha' => $fecha, 'valor' => $valor, 'medio' => 'TRANSFERENCIA', 'aplicacion_excedente' => 'ANTICIPO',
        ])->assertCreated()->json('data');
    }

    private function conceptos(array $respuesta): array
    {
        return array_column($respuesta['pago']['imputaciones'], 'valor', 'concepto');
    }

    public function test_pago_el_dia_del_desembolso_no_cobra_interes_y_todo_va_a_capital(): void
    {
        $credito = $this->libreInversion();
        $this->assertSame('354868', (string) $credito['calculo']['cuota']);

        $respuesta = $this->pagar($credito, '2026-11-24', '354868');
        $conceptos = $this->conceptos($respuesta);

        $this->assertArrayNotHasKey('INTERES_CORRIENTE', $conceptos);
        $this->assertSame('354868', $conceptos['ABONO_EXTRAORDINARIO']);
        $this->assertSame('ABONO_CAPITAL', $respuesta['pago']['aplicacion_excedente']);

        // Misma cuota, menos plazo: quedan menos de 36 cuotas vigentes y la cuota se conserva.
        $cuotas = Credito::findOrFail($credito['id'])->cuotasVigentes()->orderBy('numero')->get();
        $this->assertLessThan(36, $cuotas->count());
        $this->assertSame('354868.00', (string) $cuotas->first()->cuota);
    }

    public function test_pago_con_dias_transcurridos_cobra_interes_por_dias_reales(): void
    {
        $credito = $this->libreInversion();
        $this->travelTo(CarbonImmutable::parse('2026-12-04 10:00:00', 'America/Bogota'));
        $this->entrarComo($this->usuario);

        $respuesta = $this->pagar($credito, '2026-12-04', '354868');
        $conceptos = $this->conceptos($respuesta);

        // 10 dias: 10.000.000 x ((1,18)^(10/365) - 1)
        $interes = CreditoService::interesPorDias('10000000', '0.18', 10);
        $this->assertSame($interes, $conceptos['INTERES_CORRIENTE']);
        $this->assertSame(bcsub('354868', $interes, 0), $conceptos['ABONO_EXTRAORDINARIO']);
    }

    public function test_pago_total_sin_aplicacion_excedente_cancela_el_credito(): void
    {
        $credito = $this->libreInversion();
        $saldo = $this->getJson("/creditos/{$credito['id']}")->assertOk()->json('data.estado_cuenta.saldo_para_cancelar');

        $respuesta = $this->postJson("/creditos/{$credito['id']}/pagos", [
            'fecha' => '2026-11-24', 'valor' => $saldo, 'medio' => 'TRANSFERENCIA',
        ])->assertCreated()->json('data');

        $this->assertSame('ABONO_CAPITAL', $respuesta['pago']['aplicacion_excedente']);
        $this->assertSame('0', $this->getJson("/creditos/{$credito['id']}")->json('data.estado_cuenta.saldo_para_cancelar'));
    }

    public function test_anticipo_no_exige_ni_acepta_elegir_reducir_cuota(): void
    {
        $credito = $this->libreInversion();

        // reducir=CUOTA con ANTICIPO esta prohibido por la validacion: ANTICIPO siempre reduce plazo.
        $this->postJson("/creditos/{$credito['id']}/pagos", [
            'fecha' => '2026-11-24', 'valor' => '354868', 'medio' => 'TRANSFERENCIA',
            'aplicacion_excedente' => 'ANTICIPO', 'reducir' => 'CUOTA',
        ])->assertStatus(422)->assertJsonValidationErrors(['reducir']);
    }

    public function test_anticipo_que_solo_cubre_el_interes_causado_da_un_mensaje_claro(): void
    {
        $credito = $this->libreInversion();
        $this->travelTo(CarbonImmutable::parse('2026-12-04 10:00:00', 'America/Bogota'));
        $this->entrarComo($this->usuario);

        $interes = CreditoService::interesPorDias('10000000', '0.18', 10);

        $respuesta = $this->postJson("/creditos/{$credito['id']}/pagos", [
            'fecha' => '2026-12-04', 'valor' => $interes, 'medio' => 'TRANSFERENCIA', 'aplicacion_excedente' => 'ANTICIPO',
        ])->assertStatus(422)->assertJsonValidationErrors(['valor']);

        $mensaje = $respuesta->json('errors.valor.0');
        $this->assertStringContainsString('anticipo', mb_strtolower($mensaje));
        $this->assertStringContainsString('interés causado', $mensaje);
        $this->assertStringNotContainsString('CUOTA', $mensaje);
    }
}
