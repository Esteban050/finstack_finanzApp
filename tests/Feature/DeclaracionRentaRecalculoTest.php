<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Renta\CalendarioVencimientoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresión de los casos CP-REN-015, 022, 023, 026..034 y 035 del Mapa de pruebas SCFP:
 *  - la declaración debe reflejar ingresos e inversiones registrados DESPUÉS de abrirla por primera vez;
 *  - revertir un ajuste manual a $0 no exige nota explicativa;
 *  - la pantalla de estimación responde 200;
 *  - el formulario de perfil usa fecha ISO y marca las ocupaciones guardadas;
 *  - el vencimiento avisa cuando la fecha es una estimación (sin calendario cargado).
 */
class DeclaracionRentaRecalculoTest extends TestCase
{
    use DatabaseTransactions;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Recalculo',
            'correo' => 'recalculo.' . Str::random(8) . '@example.test',
            'password_hash' => bcrypt('Secret123!'),
            'tipo_documento' => 'CC',
            'numero_documento' => '10' . random_int(10000000, 99999999) . '42',
            'rol' => 'USUARIO',
            'estado' => 'ACTIVA',
        ]);


        $this->actingAs($this->usuario);
        $this->withoutMiddleware(\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class);
        // El requisito de perfil completo (CA-008.1) se prueba en PerfilLimitesTest.
        $this->withoutMiddleware(\App\Http\Middleware\PerfilCompletoMiddleware::class);
    }

    private function categoriaIngreso(): string
    {
        $id = DB::table('categorias')->where('tipo', 'INGRESO')->value('id');

        return $id ?? tap((string) Str::uuid(), fn ($nuevo) => DB::table('categorias')->insert([
            'id' => $nuevo, 'usuario_id' => null, 'tipo' => 'INGRESO',
            'nombre' => 'Sueldo ' . Str::random(4), 'es_predeterminada' => true,
        ]));
    }

    private function ingreso(string $clasificacion, string $monto): void
    {
        $fuente = (string) Str::uuid();
        DB::table('fuentes_ingreso')->insert([
            'id' => $fuente, 'usuario_id' => $this->usuario->id, 'tipo_fuente_id' => $this->categoriaIngreso(),
            'nombre' => 'Fuente ' . Str::random(5), 'clasificacion_tributaria' => $clasificacion,
        ]);
        DB::table('ingresos')->insert([
            'id' => (string) Str::uuid(), 'usuario_id' => $this->usuario->id, 'fuente_id' => $fuente,
            'fecha_recepcion' => '2025-06-15', 'clasificacion_tributaria' => $clasificacion,
            'monto_original' => $monto, 'monto_bruto_cop' => $monto,
            'retencion_fuente_cop' => '0', 'aportes_descontados_cop' => '0', 'descripcion' => 'Ingreso de prueba',
        ]);
    }

    private function inversion(string $monto): void
    {
        DB::table('inversiones')->insert([
            'id' => (string) Str::uuid(), 'usuario_id' => $this->usuario->id, 'nombre' => 'CDT ' . Str::random(3),
            'tipo' => 'CDT', 'entidad' => 'Banco de prueba', 'moneda' => 'COP',
            'fecha_apertura' => '2025-03-01', 'monto_inicial' => $monto,
        ]);
    }

    /** CP-REN-015: ingresos registrados después de abrir la declaración deben verse en C2. */
    public function test_consolidado_refleja_ingresos_registrados_despues_de_abrir_la_declaracion(): void
    {
        // Primera visita: la declaración se crea con todo en 0.
        $this->get(route('renta.consolidado', ['anio' => 2025]))->assertOk();
        $this->assertDatabaseHas('declaraciones_renta', [
            'usuario_id' => $this->usuario->id, 'anio_gravable' => 2025, 'c2_ingresos_brutos_cop' => 0,
        ]);

        $this->ingreso('RENTAS_TRABAJO', '25000000');
        $this->ingreso('RENTAS_NO_LABORALES', '19718600');

        $respuesta = $this->get(route('renta.consolidado', ['anio' => 2025]))->assertOk();

        $this->assertEquals(44718600.0, (float) $respuesta->viewData('declaracion')->c2_valor_final_cop);
        $this->assertEquals(44718600.0, (float) $respuesta->viewData('evaluacion')['criterios']['c2']['valor_cop']);
    }

    /** CP-REN-022: las inversiones registradas alimentan el piso de C5 y la pantalla complementaria. */
    public function test_inversiones_registradas_alimentan_c5_y_la_pantalla_complementaria(): void
    {
        $this->get(route('renta.complementaria', ['anio' => 2025]))->assertOk();

        $this->inversion('80000000');
        $this->inversion('80000000');

        $complementaria = $this->get(route('renta.complementaria', ['anio' => 2025]))->assertOk();
        $this->assertEquals(160000000.0, (float) $complementaria->viewData('declaracion')->c5_piso_inversiones_cop);
        // Los montos se formatean en el navegador a partir de data-money.
        $this->assertMatchesRegularExpression('/data-money="160000000(\.0+)?"/', $complementaria->getContent());

        // El usuario digita solo $1.000.000: C5 debe usar el piso de inversiones, no lo digitado.
        $this->post(route('renta.complementaria.guardar', ['anio' => 2025]), [
            'c5_consignaciones_declaradas_cop' => 1000000,
            'c5_confirmado' => 1,
        ])->assertSessionHasNoErrors();

        $evaluacion = $this->get(route('renta.consolidado', ['anio' => 2025]))->assertOk()->viewData('evaluacion');
        $this->assertEquals(160000000.0, (float) $evaluacion['criterios']['c5']['valor_cop']);
        $this->assertTrue($evaluacion['criterios']['c5']['cumple']);
    }

    /** CP-REN-015 (efecto lateral): revertir un ajuste a $0 no exige nota explicativa. */
    public function test_revertir_ajuste_a_cero_no_exige_nota(): void
    {
        $this->ingreso('RENTAS_TRABAJO', '25000000');

        $this->post(route('renta.complementaria.guardar', ['anio' => 2025]), [
            'c2_ajuste_manual_cop' => 5000000,
            'justificacion_ajuste' => 'Bonificación no reportada en el sistema',
        ])->assertSessionHasNoErrors();

        // Con un ajuste distinto de 0 la nota sigue siendo obligatoria.
        $this->post(route('renta.complementaria.guardar', ['anio' => 2025]), [
            'c2_ajuste_manual_cop' => 6000000,
            'justificacion_ajuste' => '',
        ])->assertSessionHasErrors('justificacion_ajuste');

        // Volver a $0 sin escribir nota es válido y queda auditado.
        $this->post(route('renta.complementaria.guardar', ['anio' => 2025]), [
            'c1_ajuste_manual_cop' => 0,
            'c2_ajuste_manual_cop' => 0,
            'justificacion_ajuste' => '',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('declaraciones_renta', [
            'usuario_id' => $this->usuario->id, 'anio_gravable' => 2025, 'c2_ajuste_manual_cop' => 0,
        ]);
        $this->assertDatabaseHas('declaracion_renta_ajustes', [
            'concepto' => 'INGRESOS_BRUTOS', 'monto_ajuste_cop' => 0, 'justificacion' => 'Ajuste manual revertido a $0',
        ]);
    }

    /** Una declaración PRESENTADA queda congelada aunque se registren nuevos datos. */
    public function test_declaracion_presentada_no_se_recalcula(): void
    {
        $this->ingreso('RENTAS_TRABAJO', '10000000');
        $this->get(route('renta.consolidado', ['anio' => 2025]))->assertOk();

        DB::table('declaraciones_renta')
            ->where('usuario_id', $this->usuario->id)->where('anio_gravable', 2025)
            ->update(['estado_declaracion' => 'PRESENTADA']);

        $this->ingreso('RENTAS_TRABAJO', '90000000');

        $declaracion = $this->get(route('renta.consolidado', ['anio' => 2025]))->assertOk()->viewData('declaracion');
        $this->assertEquals(10000000.0, (float) $declaracion->c2_ingresos_brutos_cop);
    }

    /** CP-REN-026..034: la pantalla de estimación no puede responder 500. */
    public function test_pantalla_de_estimacion_responde_200(): void
    {
        $this->ingreso('RENTAS_TRABAJO', '120000000');

        foreach ([2025, 2026] as $anio) {
            $this->get(route('renta.estimacion', ['anio' => $anio]))
                ->assertOk()
                ->assertSee('benefitsBase', false);
        }
    }

    /** CP-REN-023: el formulario de perfil usa fecha ISO y marca las ocupaciones guardadas (incluso en formato legado). */
    public function test_perfil_muestra_fecha_iso_y_ocupaciones_marcadas(): void
    {
        DB::table('perfiles_tributarios')->insert([
            'id' => (string) Str::uuid(), 'usuario_id' => $this->usuario->id, 'fecha_nacimiento' => '1990-05-15',
            'ciudad_residencia' => 'Bogotá D.C.', 'ultimos_digitos_nit' => '42',
            'ocupaciones' => '{ASALARIADO,INVERSIONISTA}', 'responsable_iva' => false,
            'vigente_desde' => '2025-01-01',
        ]);

        $html = $this->get(route('profile.show'))->assertOk()->getContent();

        $this->assertStringContainsString('value="1990-05-15"', $html);
        $this->assertMatchesRegularExpression('/name="ocupaciones\[\]" value="Asalariado"\s+checked/', $html);
        $this->assertMatchesRegularExpression('/name="ocupaciones\[\]" value="Rentista de capital"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="ocupaciones\[\]" value="Pensionado"\s+checked/', $html);
    }

    /** CP-REN-035: sin calendario RENTA cargado, la fecha se marca como estimada y se avisa en pantalla. */
    public function test_vencimiento_avisa_cuando_la_fecha_es_estimada(): void
    {
        // Se parte de un entorno sin calendario RENTA_PN 2026 (aunque la BD ya lo tenga cargado).
        DB::table('calendario_tributario')->where('anio_vigencia', 2026)->where('tipo_obligacion', 'RENTA_PN')->delete();

        $this->assertFalse(CalendarioVencimientoService::calendarioCargado('42', 2025));

        $this->get(route('renta.vencimiento', ['anio' => 2025]))
            ->assertOk()
            ->assertSee('Fecha estimada')
            ->assertViewHas('vencimiento', fn ($v) => $v['fecha_estimada'] === true && $v['calendario_cargado'] === false);
    }

    /** CP-REN-035: la migración carga el calendario DIAN 2026 completo (00–99) y 37–38 vence el 8/09/2026. */
    public function test_migracion_carga_calendario_renta_2026_oficial(): void
    {
        DB::table('calendario_tributario')->where('anio_vigencia', 2026)->where('tipo_obligacion', 'RENTA_PN')->delete();

        $migracion = require database_path('migrations/2026_11_23_000002_cargar_calendario_renta_pn_2026.php');
        $migracion->up();
        $migracion->up(); // idempotente: no duplica

        $filas = DB::table('calendario_tributario')->where('anio_vigencia', 2026)->where('tipo_obligacion', 'RENTA_PN')->get();
        $this->assertCount(51, $filas);

        $cubiertos = [];
        foreach ($filas as $fila) {
            foreach (range((int) $fila->digito_desde, (int) $fila->digito_hasta) as $d) {
                $cubiertos[$d] = ($cubiertos[$d] ?? 0) + 1;
            }
        }
        $this->assertCount(100, $cubiertos);
        $this->assertSame([1], array_values(array_unique($cubiertos)));

        $this->assertSame('2026-09-08', CalendarioVencimientoService::calcularFechaVencimiento('37', 2025)->toDateString());
        $this->assertSame('2026-08-12', CalendarioVencimientoService::calcularFechaVencimiento('01', 2025)->toDateString());
        $this->assertSame('2026-10-26', CalendarioVencimientoService::calcularFechaVencimiento('99', 2025)->toDateString());
        $this->assertSame('2026-10-26', CalendarioVencimientoService::calcularFechaVencimiento('00', 2025)->toDateString());
        $this->assertTrue(CalendarioVencimientoService::calendarioCargado('37', 2025));
    }

    /** El CSV para la importación del administrador coincide con la migración. */
    public function test_csv_de_calendario_cubre_todos_los_digitos(): void
    {
        $lineas = array_map('str_getcsv', file(database_path('data/calendario_renta_pn_2026.csv'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $this->assertSame(['digito_desde', 'digito_hasta', 'fecha'], array_shift($lineas));
        $this->assertCount(51, $lineas);

        $cubiertos = [];
        foreach ($lineas as [$desde, $hasta, $fecha]) {
            $this->assertLessThanOrEqual((int) $hasta, (int) $desde);
            foreach (range((int) $desde, (int) $hasta) as $d) {
                $cubiertos[$d] = true;
            }
            $this->assertStringStartsWith('2026-', $fecha);
        }
        $this->assertCount(100, $cubiertos);
    }

    /** CP-REN-034: el impuesto usa la base en pesos sin redondear a 2 decimales de UVT antes de la tarifa. */
    public function test_impuesto_art_241_no_redondea_la_base_en_uvt_antes_de_la_tarifa(): void
    {
        $uvt = 49799.0;

        // Base $54.330.910 = 1.090 UVT + $50.000 → impuesto exacto 19% × 50.000 = $9.500 → $10.000.
        $a = \App\Services\Renta\EstimacionRentaService::calcularImpuestoArt241(
            \App\Services\Renta\ConsolidadoRentaService::aUvt(54330910.0, $uvt), $uvt, 54330910.0
        );
        $this->assertEquals(9500.0, $a['impuesto_cop_exacto']);
        $this->assertEquals(10000.0, $a['impuesto_cop']);

        // Base $54.330.905 → exacto $9.499,05 → $9.000.
        $b = \App\Services\Renta\EstimacionRentaService::calcularImpuestoArt241(
            \App\Services\Renta\ConsolidadoRentaService::aUvt(54330905.0, $uvt), $uvt, 54330905.0
        );
        $this->assertEquals(9499.05, $b['impuesto_cop_exacto']);
        $this->assertEquals(9000.0, $b['impuesto_cop']);
    }

    /** CP-REN-034 de punta a punta: un ingreso laboral que deja la RLG en $54.330.910 paga $10.000. */
    public function test_estimacion_redondea_el_impuesto_exacto_de_9500_a_10000(): void
    {
        // Sin aportes ni otros beneficios: RLG = ingreso − 25% (exenta laboral) = 72.441.213 − 18.110.303.
        $this->ingreso('RENTAS_TRABAJO', '72441213');

        $estimacion = $this->get(route('renta.estimacion', ['anio' => 2025]))->assertOk()->viewData('estimacion');

        $this->assertEquals(54330910.0, (float) $estimacion['paso6']['rlg_cop']);
        $this->assertEquals(10000.0, (float) $estimacion['paso7']['impuesto_redondeado_cop']);
    }
}
