<?php

namespace Tests\Feature;

use App\Models\DeclaracionRenta;
use App\Models\DeclaracionRentaAjuste;
use App\Models\DeclaracionRentaRecordatorio;
use App\Models\EntidadFinanciera;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\Usuario;
use App\Models\UsuarioPerfilTributario;
use App\Services\Renta\CalendarioVencimientoService;
use App\Services\Renta\ConsolidadoRentaService;
use App\Services\Renta\EstimacionRentaService;
use App\Services\Renta\EvaluacionObligacionService;
use App\Services\Renta\InformacionComplementariaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeclaracionRentaTest extends TestCase
{
    use DatabaseTransactions;

    protected Usuario $usuario;
    protected ConsolidadoRentaService $consolidadoService;
    protected InformacionComplementariaService $complementariaService;
    protected EvaluacionObligacionService $evaluacionService;
    protected EstimacionRentaService $estimacionService;
    protected CalendarioVencimientoService $vencimientoService;

    protected function setUp(): void
    {
        parent::setUp();

        $usuario = Usuario::where('correo', 'admin@scfp.co')->first() ?? Usuario::first();
        if (! $usuario) {
            $usuario = Usuario::create([
                'nombres' => 'Usuario',
                'apellidos' => 'Tributario',
                'correo' => 'test.tributario@finanzapp.local',
                'password_hash' => bcrypt('Secret123!'),
                'tipo_documento' => 'CC',
                'numero_documento' => '1020304037', // Termina en 37 para coincidir con mockups
                'rol' => 'USUARIO',
                'estado' => 'ACTIVA',
            ]);
        } else {
            // Asegurar que el documento termine en 37 para el caso de prueba del calendario
            $usuario->numero_documento = '1020304037';
            $usuario->save();
        }
        $this->usuario = $usuario;

        $this->actingAs($this->usuario);
        $this->withoutMiddleware(\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class);

        $this->consolidadoService = app(ConsolidadoRentaService::class);
        $this->complementariaService = app(InformacionComplementariaService::class);
        $this->evaluacionService = app(EvaluacionObligacionService::class);
        $this->estimacionService = app(EstimacionRentaService::class);
        $this->vencimientoService = app(CalendarioVencimientoService::class);
    }

    /**
     * CA-043.1 & CA-043.2: Consolidación anual y drill-down.
     */
    public function test_consolidacion_anual_calcula_conceptos_y_uvt_correctamente(): void
    {
        $consolidado = $this->consolidadoService->consolidar($this->usuario, 2025);

        $this->assertEquals(2025, $consolidado['anio_gravable']);
        $this->assertEquals(49799.0, $consolidado['parametros']['uvt']);
        $this->assertEquals(0.6540, $consolidado['parametros']['pct_componente_inflacionario']);

        $this->assertArrayHasKey('patrimonio_bruto_31_12', $consolidado);
        $this->assertArrayHasKey('ingresos_brutos', $consolidado);
        $this->assertArrayHasKey('incrngo', $consolidado);
        $this->assertArrayHasKey('rentas_exentas_inversiones', $consolidado);
        $this->assertArrayHasKey('retenciones_practicadas', $consolidado);
        $this->assertArrayHasKey('piso_inversiones_c5', $consolidado);

        // Verificación de conversión UVT con 2 decimales
        $uvtCalculado = ConsolidadoRentaService::aUvt(4979900.0, 49799.0);
        $this->assertEquals(100.0, $uvtCalculado);
    }

    /**
     * CA-044.1: Ajustes manuales y validación estricta de no negatividad.
     */
    public function test_ajuste_manual_valida_no_negatividad_estricta(): void
    {
        $declaracion = $this->complementariaService->obtenerDeclaracion($this->usuario, 2025);
        $declaracion->c1_patrimonio_bruto_cop = 10000000.0;
        $declaracion->c2_ingresos_brutos_cop = 5000000.0;
        $declaracion->save();

        // Un ajuste que deje el patrimonio neto en negativo debe lanzar ValidationException (RN-044.2)
        $this->expectException(ValidationException::class);

        $this->complementariaService->guardarInformacionComplementaria($this->usuario, 2025, [
            'c1_ajuste_manual_cop' => -15000000.0, // 10M - 15M = -5M (Inválido)
            'justificacion_ajuste' => 'Intento de ajuste que deja saldo negativo',
        ]);
    }

    /**
     * CA-044.2: Registro de auditoría de ajustes con nota de 10 a 500 caracteres.
     */
    public function test_ajuste_manual_guarda_registro_de_auditoria_con_justificacion(): void
    {
        $declaracion = $this->complementariaService->obtenerDeclaracion($this->usuario, 2025);
        $declaracion->c1_patrimonio_bruto_cop = 200000000.0;
        $declaracion->c2_ingresos_brutos_cop = 100000000.0;
        $declaracion->save();

        $nota = 'Ajuste patrimonial por avalúo catastral del inmueble principal';

        $this->complementariaService->guardarInformacionComplementaria($this->usuario, 2025, [
            'c1_ajuste_manual_cop' => 15000000.0,
            'justificacion_ajuste' => $nota,
            'c3_consumos_tarjeta_cop' => 60000000.0,
            'c4_compras_totales_cop' => 66500000.0,
            'c5_consignaciones_declaradas_cop' => 152000000.0,
            'c5_confirmado' => true,
        ]);

        $this->assertDatabaseHas('declaracion_renta_ajustes', [
            'declaracion_renta_id' => $declaracion->id,
            'concepto' => 'PATRIMONIO_BRUTO',
            'monto_ajuste_cop' => 15000000.0,
            'justificacion' => $nota,
        ]);
    }

    /**
     * CA-044.3: Pre-llenado de piso de inversiones en C5.
     */
    public function test_piso_inversiones_c5_se_precarga_automaticamente(): void
    {
        $declaracion = $this->complementariaService->obtenerDeclaracion($this->usuario, 2025);

        $this->assertNotNull($declaracion->c5_piso_inversiones_cop);
        $this->assertGreaterThanOrEqual(0, $declaracion->c5_piso_inversiones_cop);
    }

    /**
     * CA-045.1: Criterio C2 utiliza operador >= (1.400 UVT exactas obligan a declarar).
     */
    public function test_criterio_c2_con_1400_uvt_exactas_obliga_a_declarar(): void
    {
        $uvt2025 = 49799.0;
        $exactoC2 = 1400.0 * $uvt2025; // 69.718.600 COP

        $declaracion = $this->complementariaService->obtenerDeclaracion($this->usuario, 2025);
        $declaracion->c1_patrimonio_bruto_cop = 1000000.0;
        $declaracion->c2_ingresos_brutos_cop = $exactoC2;
        $declaracion->c1_ajuste_manual_cop = 0;
        $declaracion->c2_ajuste_manual_cop = 0;
        $declaracion->c3_consumos_tarjeta_cop = 0;
        $declaracion->c4_compras_totales_cop = 0;
        $declaracion->c5_piso_inversiones_cop = 0;
        $declaracion->c5_consignaciones_declaradas_cop = 0;
        $declaracion->c5_confirmado = true;
        $declaracion->c6_responsable_iva = false;
        $declaracion->save();

        $evaluacion = $this->evaluacionService->evaluar($this->usuario, 2025);

        $this->assertTrue($evaluacion['criterios']['c2']['cumple'], 'C2 debe cumplir con 1.400 UVT exactas (operador >=)');
        $this->assertEquals('OBLIGADO', $evaluacion['veredicto']);
    }

    /**
     * CA-045.2: Criterio C1 utiliza operador > (4.500 UVT exactas NO obligan, 4.500 UVT + $1 sí).
     */
    public function test_criterio_c1_con_4500_uvt_exactas_no_obliga_pero_con_un_peso_mas_si(): void
    {
        $uvt2025 = 49799.0;
        $exactoC1 = 4500.0 * $uvt2025; // 224.095.500 COP

        $declaracion = $this->complementariaService->obtenerDeclaracion($this->usuario, 2025);
        $declaracion->c1_patrimonio_bruto_cop = $exactoC1; // Exacto 4.500 UVT
        $declaracion->c2_ingresos_brutos_cop = 1000000.0;
        $declaracion->c1_ajuste_manual_cop = 0;
        $declaracion->c2_ajuste_manual_cop = 0;
        $declaracion->c3_consumos_tarjeta_cop = 0;
        $declaracion->c4_compras_totales_cop = 0;
        $declaracion->c5_piso_inversiones_cop = 0;
        $declaracion->c5_consignaciones_declaradas_cop = 0;
        $declaracion->c5_confirmado = true;
        $declaracion->c6_responsable_iva = false;
        $declaracion->save();

        $evalExacto = $this->evaluacionService->evaluar($this->usuario, 2025);
        $this->assertFalse($evalExacto['criterios']['c1']['cumple'], 'C1 con 4.500 UVT exactas NO debe cumplir (operador >)');

        // Con 1 peso más supera el tope
        $declaracion->c1_patrimonio_bruto_cop = $exactoC1 + 1.0;
        $declaracion->save();

        $evalSupera = $this->evaluacionService->evaluar($this->usuario, 2025);
        $this->assertTrue($evalSupera['criterios']['c1']['cumple'], 'C1 con 4.500 UVT + 1 COP debe cumplir');
        $this->assertEquals('OBLIGADO', $evalSupera['veredicto']);
    }

    /**
     * CA-045.4: Responsable de IVA (C6 = true) obliga a declarar independientemente de los otros criterios.
     */
    public function test_responsable_de_iva_obliga_a_declarar_sin_importar_otros_criterios(): void
    {
        $declaracion = $this->complementariaService->obtenerDeclaracion($this->usuario, 2025);
        $declaracion->c1_patrimonio_bruto_cop = 0;
        $declaracion->c2_ingresos_brutos_cop = 0;
        $declaracion->c1_ajuste_manual_cop = 0;
        $declaracion->c2_ajuste_manual_cop = 0;
        $declaracion->c3_consumos_tarjeta_cop = 0;
        $declaracion->c4_compras_totales_cop = 0;
        $declaracion->c5_piso_inversiones_cop = 0;
        $declaracion->c5_consignaciones_declaradas_cop = 0;
        $declaracion->c5_confirmado = true;
        $declaracion->c6_responsable_iva = true; // Activo en perfil
        $declaracion->save();

        $evaluacion = $this->evaluacionService->evaluar($this->usuario, 2025);

        $this->assertTrue($evaluacion['criterios']['c6']['cumple']);
        $this->assertEquals('OBLIGADO', $evaluacion['veredicto']);
    }

    /**
     * CA-045.5: Veredicto INCOMPLETO cuando el piso C5 no supera el tope y falta confirmación bancaria.
     */
    public function test_veredicto_incompleto_cuando_falta_confirmar_consignaciones_c5(): void
    {
        $declaracion = $this->complementariaService->obtenerDeclaracion($this->usuario, 2025);
        $declaracion->c1_patrimonio_bruto_cop = 10000000.0;
        $declaracion->c2_ingresos_brutos_cop = 10000000.0;
        $declaracion->c1_ajuste_manual_cop = 0;
        $declaracion->c2_ajuste_manual_cop = 0;
        $declaracion->c3_consumos_tarjeta_cop = 0;
        $declaracion->c4_compras_totales_cop = 0;
        $declaracion->c5_piso_inversiones_cop = 48500000.0; // 973 UVT < 1.400 UVT
        $declaracion->c5_consignaciones_declaradas_cop = null;
        $declaracion->c5_confirmado = false; // Aún sin confirmar
        $declaracion->c6_responsable_iva = false;
        $declaracion->save();

        $evaluacion = $this->evaluacionService->evaluar($this->usuario, 2025);

        $this->assertEquals('INCOMPLETO', $evaluacion['veredicto']);
    }

    /**
     * CA-045.6: Badge "No cumple · cerca" cuando el valor está entre el 90% y el 100% del tope.
     */
    public function test_criterio_cerca_del_tope_recibe_estado_cerca(): void
    {
        $uvt2025 = 49799.0;
        $topeC4 = 1400.0 * $uvt2025; // 69.718.600 COP
        $valor95Pct = $topeC4 * 0.95; // 66.232.670 COP

        $declaracion = $this->complementariaService->obtenerDeclaracion($this->usuario, 2025);
        $declaracion->c1_patrimonio_bruto_cop = 0;
        $declaracion->c2_ingresos_brutos_cop = 0;
        $declaracion->c1_ajuste_manual_cop = 0;
        $declaracion->c2_ajuste_manual_cop = 0;
        $declaracion->c3_consumos_tarjeta_cop = 0;
        $declaracion->c4_compras_totales_cop = $valor95Pct;
        $declaracion->c5_piso_inversiones_cop = 0;
        $declaracion->c5_consignaciones_declaradas_cop = 0;
        $declaracion->c5_confirmado = true;
        $declaracion->c6_responsable_iva = false;
        $declaracion->save();

        $evaluacion = $this->evaluacionService->evaluar($this->usuario, 2025);

        $this->assertFalse($evaluacion['criterios']['c4']['cumple']);
        $this->assertTrue($evaluacion['criterios']['c4']['cerca'], 'C4 al 95% del tope debe marcar cerca=true (>= 90%)');
    }

    /**
     * CA-046.1 & CA-046.2: Depuración en 8 pasos con límites legales (40% / 1.340 UVT y 790 UVT laboral).
     */
    public function test_estimacion_en_8_pasos_aplica_topes_de_rentas_exentas(): void
    {
        $declaracion = $this->complementariaService->obtenerDeclaracion($this->usuario, 2025);

        // Simulamos consolidado enriquecido
        $declaracion->datos_consolidado_cache = [
            'anio_gravable' => 2025,
            'parametros' => [
                'uvt' => 49799.0,
                'pct_componente_inflacionario' => 0.6540,
                'limite_global_exentas_pct' => 0.40,
                'limite_global_exentas_uvt' => 1340,
                'limite_exenta_laboral_25_uvt' => 790,
            ],
            'ingresos_brutos' => [
                'trabajo_cop' => 128000000.0,
                'cesantias_cop' => 0.0,
                'capital_inversiones_cop' => 12000000.0,
                'no_laborales_cop' => 0.0,
                'utilidad_acciones_menor_2a_cop' => 2500000.0,
                'retiros_fpv_sin_requisitos_cop' => 0.0,
                'total_cop' => 142500000.0,
            ],
            'incrngo' => [
                'salud_pension_cop' => 9800000.0,
                'componente_inflacionario_cop' => 7848000.0,
                'utilidad_exenta_bvc_cop' => 2500000.0,
                'total_cop' => 20148000.0,
            ],
            'rentas_exentas_inversiones' => [
                'fpv_aportes_cop' => 15000000.0,
                'cesantias_exentas_cop' => 8500000.0,
                'total_cop' => 23500000.0,
            ],
            'retenciones_practicadas' => [
                'laborales_cop' => 3120000.0,
                'inversiones_cop' => 680000.0,
                'total_cop' => 3800000.0,
            ],
        ];
        $declaracion->c2_ajuste_manual_cop = 0;
        $declaracion->save();

        $estimacion = $this->estimacionService->estimar($this->usuario, 2025);

        // Paso 1: Ingresos brutos = 142.500.000
        $this->assertEquals(142500000.0, $estimacion['paso1']['ingresos_brutos_cop']);

        // Paso 2: INCRNGO = 20.148.000
        $this->assertEquals(20148000.0, $estimacion['paso2']['incrngo_total_cop']);

        // Subtotal: Renta líquida = 122.352.000
        $this->assertEquals(122352000.0, $estimacion['subtotal_renta_liquida']['renta_liquida_cop']);

        // Paso 3: Rentas exentas inversiones = 23.500.000
        $this->assertEquals(23500000.0, $estimacion['paso3']['exentas_inversiones_cop']);

        // Paso 5: Total exentas aceptadas limitadas al 40% o 1.340 UVT
        $this->assertLessThanOrEqual($estimacion['paso5']['tope_maximo_global_cop'], $estimacion['paso5']['total_exentas_aceptadas_cop']);

        // Paso 7: Impuesto redondeado a $1.000 COP
        $this->assertEquals(0, (int) $estimacion['paso7']['impuesto_redondeado_cop'] % 1000);

        // Paso 8: Retenciones y saldo neto
        $this->assertArrayHasKey('saldo_neto_cop', $estimacion['paso8']);
        $this->assertArrayHasKey('tipo_resultado', $estimacion['paso8']);

        // Impacto de inversiones
        $this->assertArrayHasKey('ahorro_total_cop', $estimacion['impacto_inversiones']);
    }

    /**
     * CA-047.1: Cálculo de fecha de vencimiento según últimos dos dígitos y generación de 4 recordatorios.
     */
    public function test_calendario_genera_fecha_limite_y_cuatro_recordatorios(): void
    {
        // Con documento que termina en 37 para el año gravable 2025
        $vencimiento = $this->vencimientoService->obtenerVencimientoYRecordatorios($this->usuario, 2025);

        $this->assertEquals('37', $vencimiento['ultimos_digitos']);
        $this->assertEquals('2026-09-15', $vencimiento['fecha_limite']);

        $this->assertCount(4, $vencimiento['recordatorios']);
        $diasEsperados = [30, 15, 5, 1];
        foreach ($vencimiento['recordatorios'] as $idx => $rec) {
            $this->assertEquals($diasEsperados[$idx], $rec['dias_antes']);
            $this->assertNotNull($rec['fecha_programada']);
        }
    }

    /**
     * CA-047.2: Marcar declaración como presentada valida fecha no futura y cancela recordatorios.
     */
    public function test_marcar_como_presentada_cancela_recordatorios_y_rechaza_fechas_futuras(): void
    {
        // 1. Rechazo de fecha futura (RN-047.2)
        $fechaManana = CarbonImmutable::tomorrow()->toDateString();
        $this->expectException(ValidationException::class);

        $this->vencimientoService->marcarComoPresentada($this->usuario, 2025, $fechaManana);
    }

    /**
     * CA-047.2 bis: Presentación exitosa actualiza estado y cancela pendientes.
     */
    public function test_marcar_como_presentada_exitosamente(): void
    {
        $fechaHoy = CarbonImmutable::today()->toDateString();
        $declaracion = $this->vencimientoService->marcarComoPresentada(
            $this->usuario,
            2025,
            $fechaHoy,
            'RAD-DIAN-2026-987654'
        );

        $this->assertEquals('PRESENTADA', $declaracion->estado_declaracion);
        $this->assertEquals($fechaHoy, $declaracion->fecha_presentacion->toDateString());
        $this->assertEquals('RAD-DIAN-2026-987654', $declaracion->numero_radicado_dian);

        // Verificar que no queden recordatorios en estado PROGRAMADO
        $programados = DeclaracionRentaRecordatorio::where('declaracion_renta_id', $declaracion->id)
            ->where('estado', 'PROGRAMADO')
            ->count();

        $this->assertEquals(0, $programados);
    }

    /**
     * HTTP Endpoints: Comprueba que todas las rutas del módulo respondan 200 OK y rendericen.
     */
    public function test_rutas_http_del_modulo_renta_responden_correctamente(): void
    {
        // 1. Redirección /renta
        $respIndex = $this->get('/renta');
        $respIndex->assertRedirect('/renta/2025/consolidado');

        // 2. Consolidado
        $respConsolidado = $this->get('/renta/2025/consolidado');
        $respConsolidado->assertOk();
        $respConsolidado->assertSee('Consolidado del año gravable');

        // 3. Información complementaria
        $respComp = $this->get('/renta/2025/complementaria');
        $respComp->assertOk();
        $respComp->assertSee('Información complementaria');

        // 4. Guardar información complementaria POST
        $respPostComp = $this->post('/renta/2025/complementaria', [
            'c3_consumos_tarjeta_cop' => 50000000,
            'c4_compras_totales_cop' => 60000000,
            'c5_consignaciones_declaradas_cop' => 120000000,
            'c5_confirmado' => 1,
            'accion' => 'guardar',
        ]);
        $respPostComp->assertRedirect('/renta/2025/complementaria');

        // 5. Evaluación
        $respEval = $this->get('/renta/2025/evaluacion');
        $respEval->assertOk();
        $respEval->assertSee('¿Debo declarar renta?');

        // 6. Estimación
        $respEst = $this->get('/renta/2025/estimacion');
        $respEst->assertOk();
        $respEst->assertSee('Estimación del impuesto');

        // 7. Vencimiento
        $respVenc = $this->get('/renta/2025/vencimiento');
        $respVenc->assertOk();
        $respVenc->assertSee('Vencimiento de la declaración');

        // 8. Constancia PDF (HTML preview)
        $respPdf = $this->get('/renta/2025/pdf?preview=1');
        $respPdf->assertOk();
        $respPdf->assertSee('Constancia de evaluación y estimación de declaración de renta');

        // 9. Marcar como presentada POST
        $respPostPresentada = $this->post('/renta/2025/marcar-presentada', [
            'fecha_presentacion' => CarbonImmutable::today()->toDateString(),
            'numero_radicado_dian' => 'DIAN-999999',
        ]);
        $respPostPresentada->assertRedirect('/renta/2025/vencimiento');
    }
}

