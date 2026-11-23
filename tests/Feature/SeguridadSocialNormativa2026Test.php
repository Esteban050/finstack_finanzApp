<?php

namespace Tests\Feature;

use App\Models\EsquemaCostoPresunto;
use App\Models\ParametroNormativo;
use App\Models\PerfilTributario;
use App\Models\Usuario;
use Illuminate\Support\Str;
use App\Models\AceptacionPolitica;
use App\Services\SeguridadSocialService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SeguridadSocialNormativa2026Test extends TestCase
{
    use DatabaseTransactions;

    protected Usuario $usuario;
    protected ParametroNormativo $parametro;
    protected PerfilTributario $perfil;
    protected SeguridadSocialService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        config(['database.connections.pgsql.database' => 'bd_finanzApp']);

        $this->service = app(SeguridadSocialService::class);

        // Usuario propio de la prueba: antes se tomaba el primer usuario real de la base,
        // y el resultado dependía de sus datos y de si había aceptado la política.
        $this->usuario = Usuario::create([
            'nombres' => 'Tester',
            'apellidos' => 'Normativa2026',
            'correo' => 'ss.' . Str::lower(Str::random(8)) . '@scfp.test',
            'password_hash' => bcrypt('Secret123!'),
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(10000000, 99999999),
            'rol' => 'USUARIO',
            'estado' => 'ACTIVA',
        ]);
        AceptacionPolitica::create(['usuario_id' => $this->usuario->id, 'version_politica' => config('app.version_politica_datos', 'v1.0'),
            'fecha_aceptacion' => now(), 'ip_origen' => '127.0.0.1', 'medio' => 'WEB_REGISTRO']);

        $this->perfil = PerfilTributario::where('usuario_id', $this->usuario->id)->whereNull('vigente_hasta')->orderByDesc('vigente_desde')->first()
            ?? PerfilTributario::create([
                'usuario_id' => $this->usuario->id,
                // Obligatorios en la tabla: sin ellos la preparación fallaba (not null).
                'fecha_nacimiento' => '1990-01-01',
                'ciudad_residencia' => 'Medellín',
                'vigente_desde' => '2026-01-01',
                'ocupaciones' => ['Empleado dependiente', 'Contratista por prestación de servicios', 'Rentista de capital'],
                'clase_riesgo_arl' => 1,
            ]);

        $this->parametro = ParametroNormativo::where('anio_vigencia', 2026)->where('es_activa', true)->firstOrFail();
    }

    /**
     * RF-01: Verificación de Parámetros Normativos de la Vigencia 2026.
     */
    public function test_rf01_parametros_normativos_vigencia_2026(): void
    {
        $this->assertEquals(2026, $this->parametro->anio_vigencia);
        $this->assertEquals('1750905.00', (string) $this->parametro->valor_smmlv);
        $this->assertEquals('1.00', (string) $this->parametro->ibc_min_smmlv);
        $this->assertEquals('25.00', (string) $this->parametro->ibc_max_smmlv);

        // Tarifas legales
        $this->assertEquals('4.00', (string) $this->parametro->tarifa_salud_emp);
        $this->assertEquals('8.50', (string) $this->parametro->tarifa_salud_patronal);
        $this->assertEquals('12.50', (string) $this->parametro->tarifa_salud_indep);

        $this->assertEquals('4.00', (string) $this->parametro->tarifa_pension_emp);
        $this->assertEquals('12.00', (string) $this->parametro->tarifa_pension_patronal);
        $this->assertEquals('16.00', (string) $this->parametro->tarifa_pension_indep);

        $this->assertEquals('2.00', (string) $this->parametro->tarifa_fsp);

        // Parafiscales
        $this->assertEquals('4.00', (string) $this->parametro->tarifa_parafiscales_ccf);
        $this->assertEquals('2.00', (string) $this->parametro->tarifa_parafiscales_sena);
        $this->assertEquals('3.00', (string) $this->parametro->tarifa_parafiscales_icbf);

        // Límites calculados
        $this->assertEquals(1750905.0, $this->parametro->ibcMinimo());
        $this->assertEquals(43772625.0, $this->parametro->ibcMaximo());
    }

    /**
     * RF-02: Coeficientes de presunción de costos UGPP (Resolución 532 de 2024 y Decreto 0379 de 2026).
     */
    public function test_rf02_coeficientes_presuncion_costos_ugpp(): void
    {
        $catalogo = EsquemaCostoPresunto::catalogoActivo();

        $this->assertArrayHasKey('PRESTACION_SERVICIOS', $catalogo);
        $this->assertArrayHasKey('RENTAS_CAPITAL', $catalogo);
        $this->assertArrayHasKey('ACTIVIDADES_INMOBILIARIAS', $catalogo);

        $this->assertEquals(0.00, EsquemaCostoPresunto::porcentajePara('PRESTACION_SERVICIOS'));
        $this->assertEquals(28.08, EsquemaCostoPresunto::porcentajePara('RENTAS_CAPITAL'));
        $this->assertEquals(65.70, EsquemaCostoPresunto::porcentajePara('ACTIVIDADES_INMOBILIARIAS'));
    }

    /**
     * RF-03: Cálculo de IBC Asalariado con truncamiento estricto a 25 SMMLV ($43.772.625)
     * y reporte de excedente exonerado por ley.
     */
    public function test_rf03_asalariado_truncamiento_a_25_smmlv_y_exoneracion_excedente(): void
    {
        // Salario ordinario de $60.000.000 supera los 25 SMMLV ($43.772.625)
        $resultado = $this->service->calcularAsalariado(
            salario: '60000000.00',
            esIntegral: false,
            parametro: $this->parametro,
            retencion: '5000000.00',
            exoneradoParafiscales: false,
            claseRiesgoArl: 1
        );

        $tope25Smmlv = 43772625.0;
        $excedenteEsperado = 60000000.0 - $tope25Smmlv; // 16.227.375

        $this->assertEquals('43772625.00', $resultado['ibc']);
        $this->assertEquals(number_format($excedenteEsperado, 2, '.', ''), $resultado['excedente_desafectado_tope']);
        $this->assertTrue($resultado['supero_tope_25_smmlv']);

        // Aportes de ley sobre IBC topado ($43.772.625)
        // Salud empleado: 4% = 1.750.905,00
        $this->assertEquals('1750905.00', $resultado['salud']);
        // Pensión empleado: 4% = 1.750.905,00
        $this->assertEquals('1750905.00', $resultado['pension']);
        // FSP: 2% (supera 20 SMMLV = $35.018.100) = 875.452,50
        $this->assertEquals('875452.50', $resultado['fsp']);

        // Desglose patronal sobre IBC topado
        $this->assertEquals('3720673.13', $resultado['desglose_patronal']['salud_patronal']); // 8.5% de 43.772.625
        $this->assertEquals('5252715.00', $resultado['desglose_patronal']['pension_patronal']); // 12% de 43.772.625

        // Aviso legal emitido
        $this->assertNotEmpty($resultado['avisos']);
        $this->assertStringContainsString('43.772.625', $resultado['avisos'][0]);
    }

    /**
     * RF-04: Cálculo de IBC independiente por actividad (UGPP presunto vs costos reales soportados).
     */
    public function test_rf04_calculo_ibc_actividad_independiente_presunto_vs_reales(): void
    {
        // Caso A: Rentas de capital con presunción UGPP (28.08%) sobre $20.000.000
        $actividadPresunta = $this->service->calcularActividadIndependiente(
            ingresoBruto: '20000000.00',
            codigoActividad: 'RENTAS_CAPITAL',
            costosReales: '0.00',
            usarCostosReales: false,
            parametro: $this->parametro
        );

        // Costo presunto: 20.000.000 * 28.08% = 5.616.000. Ingreso neto = 14.384.000.
        // IBC preliminar: 14.384.000 * 40% = 5.753.600.
        $this->assertEquals('5616000.00', $actividadPresunta['costos_aplicados']);
        $this->assertEquals('14384000.00', $actividadPresunta['ingreso_neto']);
        $this->assertEquals('5753600.00', $actividadPresunta['ibc_preliminar']);
        $this->assertFalse($actividadPresunta['es_servicios']);

        // Caso B: Actividad inmobiliaria con costos reales soportados mayores a la presunción
        // Ingreso bruto: $20.000.000. Costos presuntos serían 65.7% ($13.140.000).
        // Usuario ingresa costos reales soportados de $15.000.000.
        $actividadReales = $this->service->calcularActividadIndependiente(
            ingresoBruto: '20000000.00',
            codigoActividad: 'ACTIVIDADES_INMOBILIARIAS',
            costosReales: '15000000.00',
            usarCostosReales: true,
            parametro: $this->parametro
        );

        $this->assertEquals('15000000.00', $actividadReales['costos_aplicados']);
        $this->assertEquals('5000000.00', $actividadReales['ingreso_neto']);
        $this->assertEquals('2000000.00', $actividadReales['ibc_preliminar']); // 40% de 5.000.000
    }

    /**
     * RF-05, RF-06 y RF-07: Consolidación multi-ingreso, prelación del contrato laboral
     * y cálculo de cupo disponible para independientes respetando el tope de 25 SMMLV.
     */
    public function test_rf05_rf06_rf07_consolidacion_prelacion_laboral_y_cupo_disponible(): void
    {
        // Escenario: Persona con salario ordinario de $30.000.000 y contrato de servicios de $40.000.000.
        // IBC Asalariado = $30.000.000 (primer consumo).
        // Cupo disponible para independiente = 43.772.625 - 30.000.000 = 13.772.625.
        // IBC Servicios preliminar = 40.000.000 * 40% = 16.000.000.
        // Supera el cupo restante de $13.772.625 -> Se ajusta el IBC independiente a $13.772.625.
        // Excedente desafectado = 16.000.000 - 13.772.625 = 2.227.375.
        // IBC Consolidado Total = exactamente $43.772.625 (25 SMMLV).

        $resultado = $this->service->consolidarMultiIngreso(
            datos: [
                'salario' => '30000000.00',
                'es_salario_integral' => false,
                'actividades' => [
                    [
                        'codigo_actividad' => 'PRESTACION_SERVICIOS',
                        'ingreso_bruto' => '40000000.00',
                        'costos_reales' => '0.00',
                    ],
                ],
                'clase_riesgo_arl_laboral' => 1,
                'clase_riesgo_arl_independiente' => 2,
                'exonerado_parafiscales' => false,
            ],
            parametro: $this->parametro,
            perfil: $this->perfil
        );

        $this->assertEquals('30000000.00', $resultado['ibc_laboral']);
        $this->assertEquals('13772625.00', $resultado['cupo_disponible_independiente']);
        $this->assertEquals('16000000.00', $resultado['ibc_independiente_preliminar']);
        $this->assertEquals('13772625.00', $resultado['ibc_independiente_aprobado']);
        $this->assertEquals('43772625.00', $resultado['ibc_consolidado_total']);
        $this->assertEquals('2227375.00', $resultado['excedente_desafectado_tope']);
        $this->assertTrue($resultado['supero_tope_25_smmlv']);

        // Avisos legales sobre el tope y consumo de cupo
        $avisosTexto = implode(' ', $resultado['avisos']);
        $this->assertStringContainsString('43.772.625', $avisosTexto);
        $this->assertStringContainsString('cupo disponible', $avisosTexto);
    }

    /**
     * RF-07 (Borde): Salario que ya copa los 25 SMMLV deja en CERO el cupo para independientes.
     */
    public function test_rf07_salario_que_agota_el_tope_exonera_totalmente_los_ingresos_independientes(): void
    {
        // Salario de $50.000.000 agota por sí solo los 25 SMMLV ($43.772.625).
        // Contrato de servicios de $20.000.000 y rentas de $15.000.000.
        $resultado = $this->service->consolidarMultiIngreso(
            datos: [
                'salario' => '50000000.00',
                'es_salario_integral' => false,
                'actividades' => [
                    ['codigo_actividad' => 'PRESTACION_SERVICIOS', 'ingreso_bruto' => '20000000.00'],
                    ['codigo_actividad' => 'RENTAS_CAPITAL', 'ingreso_bruto' => '15000000.00'],
                ],
                'clase_riesgo_arl_laboral' => 1,
                'clase_riesgo_arl_independiente' => 1,
            ],
            parametro: $this->parametro,
            perfil: $this->perfil
        );

        $this->assertEquals('43772625.00', $resultado['ibc_laboral']);
        $this->assertEquals('0.00', $resultado['cupo_disponible_independiente']);
        $this->assertEquals('0.00', $resultado['ibc_independiente_aprobado']);
        $this->assertEquals('43772625.00', $resultado['ibc_consolidado_total']);

        // Aportes independientes en Tipo I deben ser 0.00
        $this->assertEquals('0.00', $resultado['liquidacion_independiente_tipo_i']['total_independiente']);
    }

    /**
     * RF-09: FSP según escala progresiva (Ley 797 de 2003 / RN-014.2) y 2% cuando supera 20 SMMLV.
     */
    public function test_rf09_fsp_aplica_cuando_ibc_supera_20_smmlv(): void
    {
        $smmlv = (string) $this->parametro->valor_smmlv;
        $umbral20Smmlv = bcmul($smmlv, '20.00', 2);

        // Por debajo de 4 SMMLV (ej. $5.000.000 = 2.85 SMMLV) -> 0%
        $this->assertEquals('0', $this->service->tarifaFsp('5000000.00', $smmlv, $this->parametro));

        // Entre 4 y 16 SMMLV (ej. $8.000.000 = 4.57 SMMLV) -> 1.0%
        $this->assertEquals('1.000', $this->service->tarifaFsp('8000000.00', $smmlv, $this->parametro));

        // Entre 17 y 18 SMMLV (ej. $30.000.000 = 17.13 SMMLV) -> 1.4%
        $this->assertEquals('1.400', $this->service->tarifaFsp('30000000.00', $smmlv, $this->parametro));

        // Exactamente 20 SMMLV ($35.018.100) aún es el tramo "de 19 a 20" -> 1.8% (la ley dice "superiores a 20")
        $this->assertEquals('1.800', $this->service->tarifaFsp($umbral20Smmlv, $smmlv, $this->parametro));

        // Un peso por encima de 20 SMMLV y valores mayores -> 2.0%
        $this->assertEquals('2.00', $this->service->tarifaFsp(bcadd($umbral20Smmlv, '1.00', 2), $smmlv, $this->parametro));
        $this->assertEquals('2.00', $this->service->tarifaFsp('40000000.00', $smmlv, $this->parametro));
    }

    /**
     * Verifica que para un trabajador independiente con contrato de $20.000.000 (IBC $8.000.000 >= 4 SMMLV),
     * se liquide correctamente el 1% de FSP ($80.000) además de salud y pensión.
     */
    public function test_independiente_aplica_fsp_1_porciento_cuando_ibc_supera_4_smmlv(): void
    {
        // Sin ARL (idéntico al caso de la imagen: Contrato $20.000.000, IBC $8.000.000)
        $perfilSinArl = clone $this->perfil;
        $perfilSinArl->clase_riesgo_arl = null;
        $resultadoSinArl = $this->service->calcularIndependiente(
            ingresoBase: '20000000.00',
            parametro: $this->parametro,
            perfil: $perfilSinArl
        );

        $this->assertEquals('8000000.00', $resultadoSinArl['ibc']);
        $this->assertEquals('1000000.00', $resultadoSinArl['salud']); // 12.5%
        $this->assertEquals('1280000.00', $resultadoSinArl['pension']); // 16%
        $this->assertEquals('80000.00', $resultadoSinArl['fsp']); // 1%
        $this->assertEquals('0.00', $resultadoSinArl['arl']);
        $this->assertTrue($resultadoSinArl['aplica_fsp']);
        $this->assertEquals('1.000', (string) $resultadoSinArl['porcentaje_fsp']);
        // Total = 1.000.000 + 1.280.000 + 80.000 = 2.360.000
        $this->assertEquals('2360000.00', $resultadoSinArl['total']);

        // Con ARL Clase 1 (0.522% = 41.800)
        $perfilConArl = clone $this->perfil;
        $perfilConArl->clase_riesgo_arl = 1;
        $resultadoConArl = $this->service->calcularIndependiente(
            ingresoBase: '20000000.00',
            parametro: $this->parametro,
            perfil: $perfilConArl
        );
        $this->assertEquals('80000.00', $resultadoConArl['fsp']);
        $this->assertEquals('41800.00', $resultadoConArl['arl']);
        // Total = 1.000.000 + 1.280.000 + 80.000 + 41.800 = 2.401.800
        $this->assertEquals('2401800.00', $resultadoConArl['total']);
    }

    /**
     * RF-10: Cálculo diferenciado de ARL.
     * En Tipo I, la ARL solo se liquida sobre prestación de servicios, NUNCA sobre rentas de capital ni alquileres.
     */
    public function test_rf10_arl_independiente_solo_grava_servicios_excluye_rentas_e_inmuebles(): void
    {
        // Independiente con $10.000.000 en servicios y $20.000.000 en rentas de capital.
        // Servicios: IBC = $4.000.000 (aplica ARL clase 1 = 0.522%).
        // Rentas de capital: IBC = $5.753.600 (NO aplica ARL).
        $resultado = $this->service->consolidarMultiIngreso(
            datos: [
                'salario' => '0.00',
                'actividades' => [
                    ['codigo_actividad' => 'PRESTACION_SERVICIOS', 'ingreso_bruto' => '10000000.00'],
                    ['codigo_actividad' => 'RENTAS_CAPITAL', 'ingreso_bruto' => '20000000.00'],
                ],
                'clase_riesgo_arl_laboral' => 0,
                'clase_riesgo_arl_independiente' => 1, // 0.522%
            ],
            parametro: $this->parametro,
            perfil: $this->perfil
        );

        $tipoI = $resultado['liquidacion_independiente_tipo_i'];
        $this->assertEquals('4000000.00', $tipoI['ibc_arl_servicios']);

        // ARL = 4.000.000 * 0.522% = 20.880,00
        $this->assertEquals('20880.00', $tipoI['arl']);
    }

    /**
     * RF-11: Excepción de tope para aportes parafiscales y toggle de exoneración Art. 114-1 E.T.
     */
    public function test_rf11_parafiscales_sin_tope_de_25_smmlv_con_toggle_exoneracion(): void
    {
        // Salario de $60.000.000 (superior al tope de $43.772.625)
        // Caso A: Empleador NO exonerado (aplica CCF 4%, SENA 2%, ICBF 3% = 9% sobre $60.000.000)
        $resultadoNoExonerado = $this->service->calcularAsalariado(
            salario: '60000000.00',
            esIntegral: false,
            parametro: $this->parametro,
            retencion: '0.00',
            exoneradoParafiscales: false
        );

        $desgloseNoEx = $resultadoNoExonerado['desglose_patronal'];
        $this->assertEquals('60000000.00', $desgloseNoEx['base_parafiscales']);
        $this->assertEquals('2400000.00', $desgloseNoEx['parafiscales_ccf']); // 4% de 60M
        $this->assertEquals('1200000.00', $desgloseNoEx['parafiscales_sena']); // 2% de 60M
        $this->assertEquals('1800000.00', $desgloseNoEx['parafiscales_icbf']); // 3% de 60M
        $this->assertEquals('5400000.00', $desgloseNoEx['total_parafiscales']); // 9% de 60M

        // Caso B: Empleador EXONERADO Art. 114-1 E.T. (solo paga CCF 4%, SENA 0%, ICBF 0%)
        $resultadoExonerado = $this->service->calcularAsalariado(
            salario: '60000000.00',
            esIntegral: false,
            parametro: $this->parametro,
            retencion: '0.00',
            exoneradoParafiscales: true
        );

        $desgloseEx = $resultadoExonerado['desglose_patronal'];
        $this->assertEquals('60000000.00', $desgloseEx['base_parafiscales']);
        $this->assertEquals('2400000.00', $desgloseEx['parafiscales_ccf']); // 4% de 60M
        $this->assertEquals('0.00', $desgloseEx['parafiscales_sena']);
        $this->assertEquals('0.00', $desgloseEx['parafiscales_icbf']);
        $this->assertEquals('2400000.00', $desgloseEx['total_parafiscales']);
    }

    /**
     * Endpoint API: POST /seguridad-social/calcular con MULTI_INGRESO responde con el desglose completo y avisos de ley.
     */
    public function test_api_calcular_multi_ingreso_responde_correctamente(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('seguridad-social.calcular'), [
                'tipo_cotizante' => 'MULTI_INGRESO',
                'periodo' => '2026-05',
                'salario' => '30000000',
                'es_salario_integral' => false,
                'exonerado_parafiscales' => true,
                'clase_riesgo_arl' => 1,
                'actividades_independientes' => [
                    [
                        'tipo' => 'PRESTACION_SERVICIOS',
                        'monto_bruto' => 40000000,
                        'costos_reales' => 0,
                    ],
                    [
                        'tipo' => 'RENTAS_CAPITAL',
                        'monto_bruto' => 15000000,
                        'costos_reales' => 0,
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'mensaje' => 'Aportes de seguridad social calculados con éxito.',
        ]);

        $data = $response->json('data');
        $this->assertEquals('MULTI_INGRESO', $data['tipo_cotizante']);
        $this->assertEquals('30000000.00', $data['ibc_laboral']);
        $this->assertEquals('43772625.00', $data['ibc_consolidado_total']);
        $this->assertTrue($data['supero_tope_25_smmlv']);
        $this->assertNotEmpty($data['avisos']);
        $this->assertArrayHasKey('liquidacion_laboral_tipo_e', $data);
        $this->assertArrayHasKey('liquidacion_independiente_tipo_i', $data);
    }

    /**
     * Decreto 0379 de 2026 / UGPP Resolución 532 de 2024:
     * Regla dinámica de presunción de costos para Rentistas de Capital:
     * Periodos hasta abril 2026 (<= 2026-04): 27.50%
     * Periodos desde mayo 2026 (>= 2026-05): 28.08%
     * Periodo null o no especificado: 28.08%
     */
    public function test_presuncion_costos_rentista_dinamica_decreto_0379_de_2026(): void
    {
        // 1. Verificación directa en el Modelo EsquemaCostoPresunto
        $this->assertEquals('27.50', EsquemaCostoPresunto::porcentajePara('RENTAS_CAPITAL', '2026-04'));
        $this->assertEquals('27.50', EsquemaCostoPresunto::porcentajePara('RENTAS_CAPITAL', '2026-01'));
        $this->assertEquals('27.50', EsquemaCostoPresunto::porcentajePara('RENTAS_CAPITAL', '2025-10'));
        $this->assertEquals('28.08', EsquemaCostoPresunto::porcentajePara('RENTAS_CAPITAL', '2026-05'));
        $this->assertEquals('28.08', EsquemaCostoPresunto::porcentajePara('RENTAS_CAPITAL', '2026-08'));
        $this->assertEquals('28.08', EsquemaCostoPresunto::porcentajePara('RENTAS_CAPITAL', null));

        // 2. Verificación de catálogo activo por periodo
        $catAbril = EsquemaCostoPresunto::catalogoActivo('2026-04');
        $this->assertEquals('27.50', (string) $catAbril['RENTAS_CAPITAL']['porcentaje_costo_presunto']);

        $catMayo = EsquemaCostoPresunto::catalogoActivo('2026-05');
        $this->assertEquals('28.08', (string) $catMayo['RENTAS_CAPITAL']['porcentaje_costo_presunto']);

        // 3. Verificación en el servicio SeguridadSocialService
        $this->assertEquals('27.50', SeguridadSocialService::porcentajePresuncionRentista('2026-04'));
        $this->assertEquals('28.08', SeguridadSocialService::porcentajePresuncionRentista('2026-05'));
        $this->assertEquals('28.08', SeguridadSocialService::porcentajePresuncionRentista(null));

        // 4. Verificación de cálculo individual de actividad independiente con periodo
        // Sobre $20.000.000:
        // Abril 2026 (27.5%): costo = $5.500.000, neto = $14.500.000, IBC = $5.800.000
        $actividadAbril = $this->service->calcularActividadIndependiente(
            ingresoBruto: '20000000.00',
            codigoActividad: 'RENTAS_CAPITAL',
            costosReales: '0.00',
            usarCostosReales: false,
            parametro: $this->parametro,
            periodo: '2026-04'
        );
        $this->assertEquals('27.50', $actividadAbril['porcentaje_presuncion']);
        $this->assertEquals('5500000.00', $actividadAbril['costos_aplicados']);
        $this->assertEquals('14500000.00', $actividadAbril['ingreso_neto']);
        $this->assertEquals('5800000.00', $actividadAbril['ibc_preliminar']);

        // Mayo 2026 (28.08%): costo = $5.616.000, neto = $14.384.000, IBC = $5.753.600
        $actividadMayo = $this->service->calcularActividadIndependiente(
            ingresoBruto: '20000000.00',
            codigoActividad: 'RENTAS_CAPITAL',
            costosReales: '0.00',
            usarCostosReales: false,
            parametro: $this->parametro,
            periodo: '2026-05'
        );
        $this->assertEquals('28.08', $actividadMayo['porcentaje_presuncion']);
        $this->assertEquals('5616000.00', $actividadMayo['costos_aplicados']);
        $this->assertEquals('14384000.00', $actividadMayo['ingreso_neto']);
        $this->assertEquals('5753600.00', $actividadMayo['ibc_preliminar']);
    }

    /**
     * Verificación del Endpoint API POST /seguridad-social/calcular para rentista de capital
     * en periodo abril 2026 (27.50%) vs mayo 2026 (28.08%).
     */
    public function test_api_calcular_rentista_capital_aplica_presuncion_segun_periodo(): void
    {
        // 1. Periodo 2026-04 aplica 27.50%
        $respAbril = $this->actingAs($this->usuario)
            ->postJson(route('seguridad-social.calcular'), [
                'tipo_cotizante' => 'RENTISTA_CAPITAL',
                'periodo' => '2026-04',
                'monto_contrato' => '20000000',
            ]);

        $respAbril->assertStatus(200);
        $dataAbril = $respAbril->json('data');
        $this->assertEquals('27.50', $dataAbril['porcentaje_presuncion_costos']);
        $this->assertEquals('5500000.00', $dataAbril['presuncion_costos']);
        $this->assertEquals('14500000.00', $dataAbril['ingreso_neto']);
        $this->assertEquals('5800000.00', $dataAbril['ibc']);

        // 2. Periodo 2026-05 aplica 28.08%
        $respMayo = $this->actingAs($this->usuario)
            ->postJson(route('seguridad-social.calcular'), [
                'tipo_cotizante' => 'RENTISTA_CAPITAL',
                'periodo' => '2026-05',
                'monto_contrato' => '20000000',
            ]);

        $respMayo->assertStatus(200);
        $dataMayo = $respMayo->json('data');
        $this->assertEquals('28.08', $dataMayo['porcentaje_presuncion_costos']);
        $this->assertEquals('5616000.00', $dataMayo['presuncion_costos']);
        $this->assertEquals('14384000.00', $dataMayo['ingreso_neto']);
        $this->assertEquals('5753600.00', $dataMayo['ibc']);
    }

    /**
     * Verificación del tope legal de 25 SMMLV para cotizante independiente:
     * IBC limitado a 25 SMMLV ($43.772.625), cálculo del excedente exento y aviso redactado.
     */
    public function test_independiente_tope_25_smmlv_aviso_y_excedente_exento(): void
    {
        // Contrato de $120.000.000 -> 40% = $48.000.000 > 25 SMMLV ($43.772.625)
        $resultado = $this->service->calcularIndependiente(
            ingresoBase: '120000000.00',
            parametro: $this->parametro,
            perfil: $this->perfil
        );

        $this->assertEquals('43772625.00', $resultado['ibc']);
        $this->assertEquals('43772625.00', $resultado['ibc_max_smmlv']);
        $this->assertTrue($resultado['supero_tope_25_smmlv']);
        $this->assertTrue($resultado['tope_25_smmlv_alcanzado']);
        // Excedente = 48.000.000 - 43.772.625 = 4.227.375
        $this->assertEquals('4227375.00', $resultado['excedente_desafectado_tope']);

        $this->assertNotEmpty($resultado['avisos']);
        $aviso = $resultado['avisos'][0];
        $this->assertStringContainsString('43.772.625', $aviso);
        $this->assertStringContainsString('4.227.375', $aviso);
        $this->assertStringContainsString('exento', $aviso);
    }

    /**
     * Verificación del tope legal de 25 SMMLV para rentista de capital:
     * IBC limitado a 25 SMMLV tras deducir presunción de costos y reporte de excedente.
     */
    public function test_rentista_capital_tope_25_smmlv_aviso_y_excedente_exento(): void
    {
        // Ingresos brutos de $160.000.000 en 2026-05 (28.08% costos)
        // Costos = $44.928.000 -> Base neta = $115.072.000
        // 40% IBC = $46.028.800 > 25 SMMLV ($43.772.625)
        $resultado = $this->service->calcularRentistaCapital(
            ingresosBrutos: '160000000.00',
            gastos: '0.00',
            parametro: $this->parametro,
            perfil: $this->perfil,
            porcentajePresuncion: '28.08'
        );

        $this->assertEquals('43772625.00', $resultado['ibc']);
        $this->assertTrue($resultado['supero_tope_25_smmlv']);
        // Excedente = 46.028.800 - 43.772.625 = 2.256.175
        $this->assertEquals('2256175.00', $resultado['excedente_desafectado_tope']);

        $avisosTexto = implode(' ', $resultado['avisos']);
        $this->assertStringContainsString('43.772.625', $avisosTexto);
        $this->assertStringContainsString('2.256.175', $avisosTexto);
        $this->assertStringContainsString('exento', $avisosTexto);
    }

    /**
     * Verificación del endpoint /seguridad-social/calcular para independiente que supera el tope.
     */
    public function test_api_calcular_unificado_independiente_tope_25_smmlv(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('seguridad-social.calcular'), [
                'tipo_cotizante' => 'INDEPENDIENTE',
                'periodo' => '2026-05',
                'monto_contrato' => '120000000',
                'origen_monto' => 'MANUAL',
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('43772625.00', $data['ibc']);
        $this->assertTrue($data['supero_tope_25_smmlv']);
        $this->assertEquals('4227375.00', $data['excedente_desafectado_tope']);
        $this->assertNotEmpty($data['avisos']);
        $this->assertStringContainsString('43.772.625', $data['avisos'][0]);
        $this->assertStringContainsString('4.227.375', $data['avisos'][0]);
    }

    /**
     * Ley 797/2003 art. 8: límite inferior inclusivo en 4 y 16 SMMLV; un IBC de exactamente
     * 17, 18, 19 o 20 SMMLV pertenece al tramo inferior (CP-SS-047/048/049).
     */
    public function test_fsp_limites_de_tramo_en_pesos(): void
    {
        $smmlv = (string) $this->parametro->valor_smmlv;

        $casos = [
            ['7003619', '0'], ['7003620', '1.0'], ['7003621', '1.0'],
            ['28014479', '1.0'], ['28014480', '1.2'], ['28014481', '1.2'],
            ['29765384', '1.2'], ['29765385', '1.2'], ['29765386', '1.4'],
            ['31516289', '1.4'], ['31516290', '1.4'], ['31516291', '1.6'],
            ['33267194', '1.6'], ['33267195', '1.6'], ['33267196', '1.8'],
            ['35018099', '1.8'], ['35018100', '1.8'], ['35018101', '2.0'],
        ];

        foreach ($casos as [$ibc, $esperada]) {
            $tarifa = $this->service->tarifaFsp($ibc.'.00', $smmlv, $this->parametro);
            $this->assertSame(0, bccomp($tarifa, $esperada, 3), "IBC {$ibc}: se esperaba {$esperada} y fue {$tarifa}");
        }
    }

    /**
     * RN-033.6: un Pensionado asalariado no aporta pensión ni FSP; la salud no cambia.
     */
    public function test_asalariado_pensionado_no_paga_pension_ni_fsp(): void
    {
        $normal = $this->service->calcularAsalariado(
            salario: '8000000.00',
            esIntegral: false,
            parametro: $this->parametro
        );
        $this->assertEquals('320000.00', $normal['pension']);
        $this->assertEquals('80000.00', $normal['fsp']);

        $pensionado = $this->service->calcularAsalariado(
            salario: '8000000.00',
            esIntegral: false,
            parametro: $this->parametro,
            pensionado: true
        );

        $this->assertTrue($pensionado['pensionado']);
        $this->assertEquals('0.00', $pensionado['pension']);
        $this->assertEquals('0.00', $pensionado['fsp']);
        $this->assertEquals('0.00', $pensionado['porcentaje_pension']);
        $this->assertFalse($pensionado['aplica_fsp']);
        $this->assertEquals('320000.00', $pensionado['salud']);
        $this->assertEquals('320000.00', $pensionado['total_aportes']);
        $this->assertEquals('7680000.00', $pensionado['salario_neto_sin_retencion']);
        $this->assertEquals('0.00', $pensionado['desglose_patronal']['pension_patronal']);
        $this->assertEquals('0.00', $pensionado['empleador']['pension']);
        $this->assertTrue($pensionado['parametros_usados']['pensionado']);
    }

    /**
     * RN-033.6 en el consolidado multi-ingreso: laboral con perfil Pensionado.
     */
    public function test_multi_ingreso_pensionado_no_paga_pension_ni_fsp(): void
    {
        $perfilPensionado = clone $this->perfil;
        $perfilPensionado->ocupaciones = ['Pensionado'];

        $resultado = $this->service->consolidarMultiIngreso(
            datos: ['salario' => '8000000.00', 'es_salario_integral' => false],
            parametro: $this->parametro,
            perfil: $perfilPensionado
        );

        $laboral = $resultado['laboral'];
        $this->assertEquals('0.00', $laboral['trabajador']['pension']);
        $this->assertEquals('0.00', $laboral['trabajador']['fsp']);
        $this->assertEquals('0.00', $laboral['empleador']['pension']);
        $this->assertEquals('320000.00', $laboral['trabajador']['salud']);
        $this->assertEquals('320000.00', $laboral['trabajador']['total_descuentos']);

        $perfilDependiente = clone $this->perfil;
        $perfilDependiente->ocupaciones = ['Empleado dependiente'];

        $normal = $this->service->consolidarMultiIngreso(
            datos: ['salario' => '8000000.00', 'es_salario_integral' => false],
            parametro: $this->parametro,
            perfil: $perfilDependiente
        );
        $this->assertNotEquals('0.00', $normal['laboral']['trabajador']['pension']);
    }
}
