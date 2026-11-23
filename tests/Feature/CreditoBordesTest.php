<?php

namespace Tests\Feature;

use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\TasaUsura;
use App\Models\Usuario;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * MOD-08 — Créditos: las pruebas de código que pide el Mapa de pruebas.
 *
 * - Pagos y abonos en el servidor, con el escenario del mapa
 *   (CP-CRE-014 a 017): $ 10.000.000 al 1 % mensual en 12 cuotas, francés.
 * - Semáforo 30/40 ± 0,01 (CP-CRE-019 a 023): indicador del servidor y
 *   semáforo de la tarjeta del resumen.
 * - Usura igual / + 0,01 (CP-CRE-005 a 007).
 *
 * Hoy es el 23/11/2026.
 */
class CreditoBordesTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    private string $fuente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-23 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $this->fuente = $this->crearFuente($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Pagos y abonos en el servidor (CP-CRE-014 a 017)
    |--------------------------------------------------------------------------
    */

    /**
     * CP-CRE-014 / CA-062.1 / RF-050: la cuota 1 ($ 888.488) baja el saldo en
     * su abono a capital ($ 788.488) y crea el gasto en Obligaciones financieras.
     */
    public function test_cp_cre_014_pago_de_la_cuota_1(): void
    {
        $id = $this->creditoDelMapa();

        $respuesta = $this->pagar($id, '888488')->json('data');

        $this->assertSame(
            ['INTERES_CORRIENTE' => '100000', 'CAPITAL' => '788488'],
            array_column($respuesta['pago']['imputaciones'], 'valor', 'concepto')
        );
        $this->assertSame('9211512', $respuesta['estado_cuenta']['saldo_capital']);
        $this->assertSame(1, $respuesta['estado_cuenta']['cuotas']['pagadas']);
        $this->assertSame(2, $respuesta['estado_cuenta']['proxima_cuota']['numero']);

        $gasto = Gasto::with('subcategoria')->findOrFail($respuesta['pago']['gasto_id']);
        $this->assertSame('888488.00', $gasto->monto_cop);
        $this->assertSame('2026-11-23', $gasto->fecha->toDateString());
        $this->assertSame('Cuota de crédito / hipoteca', $gasto->subcategoria->nombre);
    }

    /**
     * CP-CRE-016 y 017 / RF-052 / RN-052.2: un abono de $ 3.000.000 que
     * reduce el plazo deja la misma cuota y n = ⌈−ln(1 − S·i/C) / ln(1 + i)⌉
     * cuotas; el pago se imputa completo al capital, sin penalidad.
     */
    public function test_cp_cre_016_017_abono_que_reduce_el_plazo_sin_penalidad(): void
    {
        $id = $this->creditoDelMapa();
        $this->pagar($id, '888488');

        $abono = ['valor' => '3000000', 'aplicacion_excedente' => 'ABONO_CAPITAL', 'reducir' => 'PLAZO'];
        $simulacion = $this->postJson("/creditos/{$id}/pagos/simular", ['fecha' => '2026-11-23', 'medio' => 'TRANSFERENCIA', ...$abono])
            ->assertOk()->json('data');

        // CP-CRE-016: todo el valor va a capital; no hay ningún concepto de penalidad.
        $this->assertSame([['cuota_id' => null, 'cuota_numero' => null, 'concepto' => 'ABONO_EXTRAORDINARIO', 'valor' => '3000000']], $simulacion['imputaciones']);

        // CP-CRE-017: 6.211.512 de saldo con cuota de 888.488 al 1 % → 7,28 → 8 cuotas.
        $saldo = 6211512;
        $esperadas = (int) ceil(-log(1 - $saldo * 0.01 / 888488) / log(1.01));
        $this->assertSame(8, $esperadas);
        $this->assertCount($esperadas, $simulacion['plan_nuevo']);
        $this->assertSame([2, 9], [$simulacion['plan_nuevo'][0]['numero'], end($simulacion['plan_nuevo'])['numero']]);
        $this->assertSame(['888488'], array_values(array_unique(array_column(array_slice($simulacion['plan_nuevo'], 0, 7), 'cuota'))));
        $this->assertSame('252946', end($simulacion['plan_nuevo'])['cuota']);
        $this->assertSame('0', end($simulacion['plan_nuevo'])['saldo_final']);
        $this->assertSame('301006', $simulacion['ahorro_intereses']);

        $cuenta = $this->pagar($id, '3000000', 'ABONO_CAPITAL', 'PLAZO')->json('data.estado_cuenta');
        $this->assertSame('6211512', $cuenta['saldo_capital']);
        $this->assertSame(9, $cuenta['cuotas']['total']);
        $this->assertSame('3888488', $cuenta['total_pagado']['total']);
    }

    /**
     * CP-CRE-015 / RF-037: un pago igual al saldo para cancelar deja el crédito
     * pagado, sin cuotas futuras. Lo pagado es capital más los intereses
     * causados: ningún cargo extra.
     */
    public function test_cp_cre_015_abono_igual_al_saldo_cancela_el_credito(): void
    {
        $id = $this->creditoDelMapa();
        $this->pagar($id, '888488');
        $this->pagar($id, '3000000', 'ABONO_CAPITAL', 'PLAZO');

        $saldo = $this->getJson("/creditos/{$id}")->assertOk()->json('data.estado_cuenta.saldo_para_cancelar');
        $this->assertSame('6211512', $saldo);

        $simulacion = $this->postJson("/creditos/{$id}/pagos/simular", ['fecha' => '2026-11-23', 'medio' => 'TRANSFERENCIA', 'valor' => $saldo])
            ->assertOk()->json('data');
        $this->assertTrue($simulacion['cancela_credito']);

        $cuenta = $this->pagar($id, $saldo)->json('data.estado_cuenta');
        $this->assertSame('PAGADO', $cuenta['estado']);
        $this->assertSame('0', $cuenta['saldo_capital']);
        $this->assertSame('0', $cuenta['saldo_para_cancelar']);
        $this->assertSame(0, $cuenta['cuotas']['pendientes']);
        $this->assertNull($cuenta['proxima_cuota']);
        $this->assertSame('10000000', $cuenta['total_pagado']['capital']);
        $this->assertSame('100000', $cuenta['total_pagado']['interes_corriente']);
        $this->assertSame('10100000', $cuenta['total_pagado']['total']);

        $this->pagar($id, '1000', esperado: 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Semáforo 30/40 ± 0,01 (CP-CRE-019 a 023)
    |--------------------------------------------------------------------------
    */

    /**
     * RN-053.1 / RN-053.2: con ingresos de $ 4.000.000 en los tres meses
     * anteriores, las cuotas del mes dan el indicador exacto y el semáforo
     * del resumen cambia justo después de 30 % y de 40 %.
     */
    public function test_semaforo_en_30_y_40_mas_o_menos_0_01(): void
    {
        foreach (['2026-08-10', '2026-09-10', '2026-10-10'] as $fecha) {
            $this->ingreso($fecha, '4000000.00');
        }

        $casos = [
            'CP-CRE-020' => ['1200000', '30.00', 'VERDE'],
            'CP-CRE-021' => ['1200400', '30.01', 'AMARILLO'],
            'CP-CRE-019' => ['1500000', '37.50', 'AMARILLO'],
            'CP-CRE-022' => ['1600000', '40.00', 'AMARILLO'],
            'CP-CRE-023' => ['1600400', '40.01', 'ROJO'],
        ];

        foreach ($casos as $caso => [$cuota, $indicador, $semaforo]) {
            // Una sola cuota sin intereses que vence el 20/11.
            $id = $this->postJson('/creditos', [
                'tipo_credito' => 'LIBRE_INVERSION', 'banco' => 'Banco QA', 'proposito' => "Semáforo {$caso}",
                'monto' => $cuota, 'tasa' => '0', 'tipo_tasa' => 'EA', 'numero_cuotas' => 1,
                'periodicidad' => 'MENSUAL', 'fecha_inicio' => '2026-10-20',
            ])->assertCreated()->json('data.id');

            $endeudamiento = $this->getJson('/creditos/endeudamiento')->assertOk()->json('data');
            $this->assertSame($cuota, $endeudamiento['pagos_mes']['total'], "{$caso}: cuotas del mes");
            $this->assertSame('4000000', $endeudamiento['ingresos_netos']['promedio'], "{$caso}: ingreso promedio");
            $this->assertSame($indicador, $endeudamiento['indicador_porc'], "{$caso}: indicador");

            $tarjeta = collect($this->getJson('/resumen?periodo=mes-actual')->assertOk()->json('data.kpis'))->firstWhere('clave', 'endeudamiento');
            $this->assertSame($indicador, $tarjeta['valor'], "{$caso}: tarjeta del resumen");
            $this->assertSame($semaforo, $tarjeta['semaforo'], "{$caso}: semáforo");

            $this->deleteJson("/creditos/{$id}")->assertOk();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Usura igual / + 0,01 (CP-CRE-005 a 007)
    |--------------------------------------------------------------------------
    */

    /**
     * CP-CRE-005 y 006 / Créditos RN-01: una tasa igual a la usura no es usura
     * (Código de Comercio art. 884: lo que excede el límite); 0,01 puntos
     * encima sí, y la advertencia trae las dos tasas.
     */
    public function test_cp_cre_005_006_usura_igual_y_001_encima(): void
    {
        $this->usura('25.0000', '2026-11-01', '2026-11-30', 'Resolución QA noviembre');

        $igual = $this->alertas($this->postJson('/creditos/calcular', $this->datosUsura('25'))->assertOk()->json('data.alertas'));
        $this->assertSame([], $igual, 'CP-CRE-005: tasa = usura');

        $encima = $this->postJson('/creditos/calcular', $this->datosUsura('25.01'))->assertOk()->json('data.alertas');
        $alerta = collect($encima)->firstWhere('codigo', 'TASA_SUPERA_USURA');
        $this->assertNotNull($alerta, 'CP-CRE-006: usura + 0,01');
        $this->assertSame('ADVERTENCIA', $alerta['nivel']);
        $this->assertSame('25.0100', $alerta['tasa_ea_porc']);
        $this->assertSame('25.0000', $alerta['usura_ea_porc']);

        // El crédito se registra igual y su detalle conserva la advertencia.
        $id = $this->postJson('/creditos', $this->datosUsura('25.01'))->assertCreated()->json('data.id');
        $this->assertContains('TASA_SUPERA_USURA', array_column($this->getJson("/creditos/{$id}")->json('data.alertas'), 'codigo'));
    }

    /**
     * CP-CRE-007 / Créditos RN-02: la tasa se compara con la usura vigente en
     * el desembolso, no con la de hoy.
     */
    public function test_cp_cre_007_usa_la_usura_del_mes_del_desembolso(): void
    {
        $this->usura('30.0000', '2026-03-01', '2026-03-31', 'Resolución QA marzo');
        $this->usura('25.0000', '2026-11-01', '2026-11-30', 'Resolución QA noviembre');

        $marzo = $this->postJson('/creditos/calcular', $this->datosUsura('27', '2026-03-15'))->assertOk()->json('data.alertas');
        $this->assertSame([], $this->alertas($marzo), 'Desembolso en marzo: 27 % < 30 %');

        $noviembre = $this->postJson('/creditos/calcular', $this->datosUsura('27', '2026-11-15'))->assertOk()->json('data.alertas');
        $this->assertSame(['TASA_SUPERA_USURA'], $this->alertas($noviembre), 'Desembolso en noviembre: 27 % > 25 %');

        $id = $this->postJson('/creditos', $this->datosUsura('27', '2026-03-15'))->assertCreated()->json('data.id');
        $this->assertSame([], $this->alertas($this->getJson("/creditos/{$id}")->json('data.alertas')), 'Detalle del crédito de marzo');
    }

    /*
    |--------------------------------------------------------------------------
    | Apoyo
    |--------------------------------------------------------------------------
    */

    /** $ 10.000.000 al 1 % mensual, 12 cuotas, francés; la cuota 1 vence hoy. */
    private function creditoDelMapa(): string
    {
        return $this->postJson('/creditos', [
            'tipo_credito' => 'LIBRE_INVERSION', 'banco' => 'Banco QA', 'proposito' => 'Prueba pagos QA',
            'monto' => '10000000', 'tasa' => '1', 'tipo_tasa' => 'MENSUAL', 'numero_cuotas' => 12,
            'periodicidad' => 'MENSUAL', 'sistema_amortizacion' => 'FRANCES', 'fecha_inicio' => '2026-10-23',
        ])->assertCreated()->assertJsonPath('data.calculo.cuota', '888488')->json('data.id');
    }

    private function pagar(string $id, string $valor, ?string $aplicacion = null, ?string $reducir = null, int $esperado = 201)
    {
        return $this->postJson("/creditos/{$id}/pagos", array_filter([
            'fecha' => '2026-11-23',
            'valor' => $valor,
            'medio' => 'TRANSFERENCIA',
            'aplicacion_excedente' => $aplicacion,
            'reducir' => $reducir,
        ]))->assertStatus($esperado);
    }

    private function usura(string $tasaEa, string $desde, string $hasta, string $acto): void
    {
        TasaUsura::create([
            'modalidad' => 'CONSUMO_ORDINARIO',
            'tasa_ea' => $tasaEa,
            'vigente_desde' => $desde,
            'vigente_hasta' => $hasta,
            'acto_administrativo' => $acto,
        ]);
    }

    private function datosUsura(string $tasaEa, string $desembolso = '2026-11-23'): array
    {
        return [
            'tipo_credito' => 'LIBRE_INVERSION', 'banco' => 'Banco QA', 'proposito' => 'Prueba usura QA',
            'monto' => '5000000', 'tasa' => $tasaEa, 'tipo_tasa' => 'EA', 'numero_cuotas' => 12,
            'periodicidad' => 'MENSUAL', 'fecha_inicio' => $desembolso,
        ];
    }

    /** Ingreso confirmado en COP; el neto lo calcula Postgres (RN-021.2). */
    private function ingreso(string $fecha, string $bruto): void
    {
        (new Ingreso)->forceFill([
            'usuario_id' => $this->usuario->id,
            'fuente_id' => $this->fuente,
            'fecha_recepcion' => $fecha,
            'clasificacion_tributaria' => 'RENTAS_TRABAJO',
            'moneda' => 'COP',
            'monto_original' => $bruto,
            'tasa_cambio_usada' => '1.0000',
            'monto_bruto_cop' => $bruto,
            'retencion_fuente_cop' => '0.00',
            'aportes_descontados_cop' => '0.00',
            'origen' => 'MANUAL',
            'estado' => 'CONFIRMADO',
        ])->save();
    }

    /** @return array<int, string> códigos de las alertas de usura */
    private function alertas(array $alertas): array
    {
        return array_values(array_filter(
            array_column($alertas, 'codigo'),
            fn (string $codigo) => str_contains($codigo, 'USURA')
        ));
    }
}
