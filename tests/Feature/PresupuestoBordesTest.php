<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Notificacion;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * Presupuestos (MOD-09) en los bordes que pide el Mapa de pruebas:
 * - Ejecución, estados y alertas únicas (CP-PRE-012, 017 a 020).
 * - Gasto atípico con 4/5 registros (CP-PRE-009, 010; RN-057.4).
 * - Proyección al cierre (CP-PRE-021 a 023; RN-057.3).
 *
 * Noviembre de 2026 tiene 30 días. El usuario no tiene gastos antes de
 * noviembre salvo los que crea cada prueba, así que la proyección es lineal
 * (RN-056.1): gastado / día de corte × 30.
 */
class PresupuestoBordesTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    private Categoria $categoria;

    private Categoria $subcategoria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-10 10:00:00');
        $this->alertasDentroDeLaTransaccion();

        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $this->categoria = $this->categoriaPropia();
        $this->subcategoria = $this->categoriaPropia($this->categoria);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Ejecución, estados y alertas únicas
    |--------------------------------------------------------------------------
    */

    /**
     * CP-PRE-017 a 020 / RN-056.2 / CA-057.1: la ejecución llega exacta a los
     * cortes de estado (80 % y 100 %) y la alerta de cada umbral sale justo al
     * llegar, no antes. Antes de la mitad del mes no hay "Vas a pasarte".
     */
    public function test_ejecucion_y_alertas_en_los_bordes_de_80_y_100(): void
    {
        $this->presupuesto('1000000');

        $pasos = [
            ['799900', '79.99', '200100', []],
            ['100', '80.00', '200000', [80 => 'ADVERTENCIA']],
            ['199900', '99.99', '100', [80 => 'ADVERTENCIA']],
            ['100', '100.00', '0', [80 => 'ADVERTENCIA', 100 => 'CRITICA']],
        ];

        foreach ($pasos as [$monto, $ejecucion, $disponible, $alertas]) {
            $this->gasto('2026-11-05', $monto);
            $linea = $this->linea();

            $this->assertSame($ejecucion, $linea['ejecucion_porc'], "Ejecución al sumar {$monto}");
            $this->assertSame($disponible, $linea['disponible'], "Disponible al {$ejecucion} %");
            $this->assertSame($alertas, $this->alertasDeLinea(), "Alertas al {$ejecucion} %");
        }

        $this->assertSame(0, Notificacion::where('tipo', 'PROYECCION_LINEA')->count());
    }

    /**
     * CP-PRE-012 / RN-057.2: cada umbral alerta una sola vez por categoría y
     * mes, aunque la ejecución suba más, el gasto se borre y se registre de
     * nuevo o el presupuesto se edite (las líneas se recrean).
     */
    public function test_la_alerta_de_cada_umbral_es_unica(): void
    {
        $this->presupuesto('1000000');

        $gasto = $this->gasto('2026-11-05', '850000');
        $this->assertSame([80 => 'ADVERTENCIA'], $this->alertasDeLinea());

        $this->gasto('2026-11-06', '50000');
        $gasto->delete();
        $this->gasto('2026-11-07', '850000');
        $this->presupuesto('1000000');

        $this->assertSame([80 => 'ADVERTENCIA'], $this->alertasDeLinea());
        $this->assertSame('90.00', $this->linea()['ejecucion_porc']);
        $this->assertSame(1, Notificacion::where('clave', 'PRESUPUESTO_TOTAL:2026-11:90')->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Gasto atípico (RN-057.4)
    |--------------------------------------------------------------------------
    */

    /** CP-PRE-009: con 4 gastos previos no alerta; con 5 sí, como informativa. */
    public function test_cp_pre_009_gasto_atipico_con_4_y_con_5_registros(): void
    {
        $this->irA('2026-11-23 10:00:00');
        $this->previos(['2026-10-01', '2026-10-15', '2026-11-01', '2026-11-15']);

        $this->gasto('2026-11-23', '350000');
        $this->assertSame(0, $this->inusuales()->count(), '4 previos');

        // El de $ 350.000 es del mismo día: no cuenta como previo.
        $this->previos(['2026-11-20']);
        $atipico = $this->gasto('2026-11-23', '350000');

        $alertas = $this->inusuales()->get();
        $this->assertCount(1, $alertas, '5 previos');
        $this->assertSame("GASTO_INUSUAL:{$atipico->id}", $alertas[0]->clave);
        $this->assertSame('INFORMATIVA', $alertas[0]->prioridad);
        $this->assertSame(5, $alertas[0]->datos['gastos_previos']);
        $this->assertSame('100000', $alertas[0]->datos['promedio']);
        $this->assertSame('3.50', $alertas[0]->datos['veces_promedio']);
    }

    /** CP-PRE-010: exactamente 3 veces el promedio no alerta (la regla es "mayor que"); $ 1 más sí. */
    public function test_cp_pre_010_exactamente_3_veces_no_alerta(): void
    {
        $this->irA('2026-11-23 10:00:00');
        $this->previos(['2026-10-01', '2026-10-15', '2026-11-01', '2026-11-15', '2026-11-20']);

        $this->gasto('2026-11-23', '300000');
        $this->assertSame(0, $this->inusuales()->count(), '3 × promedio');

        $this->gasto('2026-11-23', '300001');
        $this->assertSame(1, $this->inusuales()->count(), '3 × promedio + $ 1');
    }

    /**
     * RN-057.4: los previos son de la misma subcategoría, de días anteriores y
     * de los 6 meses anteriores (desde el 23/05 para un gasto del 23/11).
     */
    public function test_gasto_atipico_solo_cuenta_previos_de_la_subcategoria_en_6_meses(): void
    {
        $this->irA('2026-11-23 10:00:00');
        $this->previos(['2026-08-01', '2026-09-01', '2026-10-01', '2026-11-01']);
        $this->previos(['2026-05-22']);
        $this->gasto('2026-11-10', '100000', $this->categoriaPropia($this->categoria));
        $this->previos(['2026-11-23', '2026-11-23']);

        $this->gasto('2026-11-23', '350000');
        $this->assertSame(0, $this->inusuales()->count(), 'Fuera de la ventana, de otra subcategoría o del mismo día');

        $this->previos(['2026-05-23']);
        $this->gasto('2026-11-23', '350000');
        $this->assertSame(1, $this->inusuales()->count(), 'Con el previo del 23/05 ya son 5');
    }

    /*
    |--------------------------------------------------------------------------
    | Proyección al cierre (RN-057.3)
    |--------------------------------------------------------------------------
    */

    /**
     * CP-PRE-021: el día 24 con $ 880.000 gastados la proyección lineal es
     * 880.000 / 24 × 30 = $ 1.100.000, exactamente el 110 %. La regla es
     * "supera el 110 %", así que no alerta.
     */
    public function test_cp_pre_021_proyeccion_de_110_exacto_no_alerta(): void
    {
        $this->irA('2026-11-24 10:00:00');
        $this->presupuesto('1000000');

        $this->gasto('2026-11-20', '880000');

        $linea = $this->linea();
        $this->assertSame('1100000', $linea['proyeccion']);
        $this->assertSame('110.00', $linea['proyeccion_porc']);
        $this->assertSame(0, Notificacion::where('tipo', 'PROYECCION_LINEA')->count());
    }

    /** CP-PRE-022: con $ 880.080 la proyección es $ 1.100.100 (110,01 %): una sola alerta preventiva. */
    public function test_cp_pre_022_proyeccion_de_110_01_alerta_una_vez(): void
    {
        $this->irA('2026-11-24 10:00:00');
        $this->presupuesto('1000000');

        $this->gasto('2026-11-20', '880080');
        $linea = $this->linea();
        $this->assertSame('1100100', $linea['proyeccion']);
        $this->assertSame('110.01', $linea['proyeccion_porc']);

        $this->gasto('2026-11-21', '1000');

        $alertas = Notificacion::where('tipo', 'PROYECCION_LINEA')->get();
        $this->assertCount(1, $alertas);
        $this->assertSame("PROYECCION_LINEA:{$this->categoria->id}:2026-11", $alertas[0]->clave);
        $this->assertSame('ADVERTENCIA', $alertas[0]->prioridad);
        $this->assertSame('110.01', $alertas[0]->datos['proyeccion_porc']);
    }

    /**
     * CP-PRE-023 / RN-057.3: el día 14 de 30 la proyección (128,57 %) no
     * alerta porque no ha pasado la mitad del mes; el día 16 el job diario sí
     * (112,50 %).
     */
    public function test_cp_pre_023_proyeccion_solo_desde_la_mitad_del_mes(): void
    {
        $this->irA('2026-11-14 10:00:00');
        $this->presupuesto('1000000');
        $this->gasto('2026-11-10', '600000');

        $this->assertSame('128.57', $this->linea()['proyeccion_porc']);
        $this->assertSame(0, Notificacion::where('tipo', 'PROYECCION_LINEA')->count(), 'Día 14');

        $this->irA('2026-11-16 06:30:00');
        $this->artisan('scfp:evaluar-alertas')->assertSuccessful();

        $this->assertSame('112.50', $this->linea()['proyeccion_porc']);
        $this->assertSame(1, Notificacion::where('tipo', 'PROYECCION_LINEA')->count(), 'Día 16');
    }

    /*
    |--------------------------------------------------------------------------
    | Apoyo
    |--------------------------------------------------------------------------
    */

    /**
     * Las alertas se evalúan con DB::afterCommit (AlertaService::programar).
     * La transacción de UsaBaseLocal nunca se confirma, así que se usa el
     * administrador de transacciones de prueba de Laravel: ejecuta esos
     * callbacks al confirmar cada transacción interna de la app.
     */
    private function alertasDentroDeLaTransaccion(): void
    {
        $administrador = new DatabaseTransactionsManager(['pgsql']);
        $this->app->instance('db.transactions', $administrador);
        DB::connection('pgsql')->setTransactionManager($administrador);
    }

    /**
     * Mueve el reloj y vuelve a iniciar sesión: con días de diferencia el
     * middleware de inactividad (RN-006.1) cerraría la sesión anterior.
     */
    private function irA(string $momento): void
    {
        $this->travelTo(CarbonImmutable::parse($momento, 'America/Bogota'));
        $this->entrarComo($this->usuario);
    }

    private function presupuesto(string $monto): void
    {
        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false,
            'trasladar_saldo' => false,
            'lineas' => [['categoria_id' => $this->categoria->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => $monto]],
        ])->assertSuccessful();
    }

    /** La línea del presupuesto del mes actual, como la entrega GET /presupuestos. */
    private function linea(): array
    {
        return $this->getJson('/presupuestos')->assertOk()->json('data.lineas.0');
    }

    /** @return array<int, string> umbral => prioridad de las alertas de la línea */
    private function alertasDeLinea(): array
    {
        return Notificacion::where('tipo', 'UMBRAL_LINEA')->get()
            ->mapWithKeys(fn (Notificacion $n) => [$n->datos['umbral'] => $n->prioridad])
            ->sortKeys()
            ->all();
    }

    private function inusuales()
    {
        return Notificacion::where('tipo', 'GASTO_INUSUAL');
    }

    /** Gastos previos de $ 100.000 en la subcategoría. */
    private function previos(array $fechas): void
    {
        foreach ($fechas as $fecha) {
            $this->gasto($fecha, '100000');
        }
    }

    /** Categoría de gasto propia; con padre es subcategoría (RN-026.2). */
    private function categoriaPropia(?Categoria $padre = null): Categoria
    {
        $categoria = new Categoria;
        $categoria->forceFill([
            'usuario_id' => $this->usuario->id,
            'categoria_padre_id' => $padre?->id,
            'tipo' => 'GASTO',
            'nombre' => 'Prueba '.Str::random(8),
            'naturaleza_sugerida' => $padre === null ? null : 'VARIABLE',
            'necesidad_sugerida' => $padre === null ? null : 'DISCRECIONAL',
            'es_predeterminada' => false,
            'activa' => true,
            'creado_en' => now(),
        ])->save();

        return $categoria;
    }

    /** Gasto confirmado en COP; pasa por el observador, que programa las alertas. */
    private function gasto(string $fecha, string $monto, ?Categoria $subcategoria = null): Gasto
    {
        $subcategoria ??= $this->subcategoria;
        $gasto = new Gasto;
        $gasto->forceFill([
            'usuario_id' => $this->usuario->id,
            'categoria_id' => $subcategoria->categoria_padre_id,
            'subcategoria_id' => $subcategoria->id,
            'fecha' => $fecha,
            'moneda' => 'COP',
            'monto_original' => $monto,
            'tasa_cambio_usada' => '1.0000',
            'monto_cop' => $monto,
            'naturaleza' => 'VARIABLE',
            'necesidad' => 'DISCRECIONAL',
            'medio_pago' => 'EFECTIVO',
            'relevancia_tributaria' => 'NO_DEDUCIBLE',
            'etiquetas' => [],
            'origen' => 'MANUAL',
            'estado' => 'CONFIRMADO',
        ])->save();

        return $gasto;
    }
}
