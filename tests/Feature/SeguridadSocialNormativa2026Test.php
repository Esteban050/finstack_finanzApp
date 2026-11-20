<?php

namespace Tests\Feature;

use App\Models\EsquemaCostoPresunto;
use App\Models\ParametroNormativo;
use App\Models\PerfilTributario;
use App\Models\Usuario;
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

        $this->usuario = Usuario::where('correo', 'estebansalazar1407@metabiblioteca.com')->first()
            ?? Usuario::where('correo', 'admin@scfp.com')->first()
            ?? Usuario::first();

        if (! $this->usuario) {
            $this->usuario = Usuario::create([
                'nombres' => 'Tester',
                'apellidos' => 'Normativa2026',
                'correo' => 'tester.normativa2026@finanzapp.local',
                'password_hash' => bcrypt('Secret123!'),
                'tipo_documento' => 'CC',
                'numero_documento' => '9988776655',
                'rol' => 'USUARIO',
                'estado' => 'ACTIVA',
            ]);
        }

        $this->perfil = PerfilTributario::where('usuario_id', $this->usuario->id)->orderByDesc('vigente_desde')->first()
            ?? PerfilTributario::create([
                'usuario_id' => $this->usuario->id,
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
     * RF-09: FSP del 2% cuando el IBC consolidado total supera 20 SMMLV ($35.018.100).
     */
    public function test_rf09_fsp_aplica_cuando_ibc_supera_20_smmlv(): void
    {
        $smmlv = (string) $this->parametro->valor_smmlv;
        $umbral20Smmlv = bcmul($smmlv, '20.00', 2);

        // Por debajo de 20 SMMLV (ej. $30.000.000)
        $this->assertEquals('0', $this->service->tarifaFsp('30000000.00', $smmlv, $this->parametro));

        // Exactamente en el umbral y por encima (ej. $35.018.100 y $40.000.000)
        $this->assertEquals('2.00', $this->service->tarifaFsp($umbral20Smmlv, $smmlv, $this->parametro));
        $this->assertEquals('2.00', $this->service->tarifaFsp('40000000.00', $smmlv, $this->parametro));
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
}
