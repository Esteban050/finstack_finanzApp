<?php

namespace Tests\Feature;

use App\Models\EntidadFinanciera;
use App\Models\FicCatalogo;
use App\Models\IngresoInversion;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\ParametroTributario;
use App\Models\TasaCambio;
use App\Models\Usuario;
use App\Models\UsuarioPerfilTributario;
use App\Models\ValoracionInversion;
use App\Models\ValorDeducibleRegistro;
use App\Services\CalculoImpuestoInversionService;
use App\Services\InversionService;
use App\Services\ParametroTributarioService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InversionesTributariasTest extends TestCase
{
    use DatabaseTransactions;

    protected Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $usuario = Usuario::where('correo', 'admin@scfp.co')->first() ?? Usuario::first();
        if (! $usuario) {
            $usuario = Usuario::create([
                'nombres' => 'Test',
                'apellidos' => 'Inversionista',
                'correo' => 'inversionista.test@finanzapp.local',
                'password_hash' => bcrypt('Password123!'),
                'tipo_documento' => 'CC',
                'numero_documento' => '1234567890',
                'rol' => 'USUARIO',
                'estado' => 'ACTIVA',
            ]);
        }
        $this->usuario = $usuario;

        $this->actingAs($this->usuario);
        $this->withoutMiddleware(\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class);
    }

    /**
     * Regla 1: CDT / CDAT retención en la fuente 4% (Decreto 572 de 2025 / Art. 1.2.4.2.83).
     */
    public function test_cdt_retencion_cuatro_por_ciento(): void
    {
        $capital = '10000000.00';
        $tasaEa = '12.0000';
        $plazoDias = 365;

        $calculo = CalculoImpuestoInversionService::calcularCdt(
            capital: $capital,
            tasaValor: $tasaEa,
            tipoTasa: 'EA',
            plazoDias: $plazoDias,
            baseDias: 365,
            fecha: '2026-06-01'
        );

        // Bruto = 10.000.000 * 0.12 = 1.200.000
        $this->assertEquals('1200000.00', $calculo['bruto']);

        // Retención 4% = 1.200.000 * 0.04 = 48.000
        $this->assertEquals('48000.00', $calculo['retencion_total']);

        // Neto = 1.200.000 - 48.000 = 1.152.000
        $this->assertEquals('1152000.00', $calculo['neto']);

        // Rentabilidad neta EA = 11.52%
        $this->assertEquals('11.5200', $calculo['rentabilidad_neta_ea']);

        // Verificar trazabilidad de normas en las líneas de desglose
        $this->assertCount(2, $calculo['detalle_lineas']);
        $this->assertStringContainsString('Decreto 572 de 2025', $calculo['detalle_lineas'][1]['norma']);
    }

    /**
     * Regla 2: Cuenta alto rendimiento con abono (ReteFuente 7%) y GMF con tope 350 UVT.
     */
    public function test_cuenta_alto_rendimiento_abono_siete_por_ciento_y_gmf_con_tope_350_uvt(): void
    {
        // 1. Abono de intereses con 7% de retención
        $saldo = '10000000.00';
        $tasaEa = '12.0000';
        $dias = 30;

        $abono = CalculoImpuestoInversionService::calcularAbonoCuenta(
            saldo: $saldo,
            tasaEa: $tasaEa,
            dias: $dias,
            baseDias: 365,
            fecha: '2026-06-01'
        );

        $this->assertGreaterThan(0, (float) $abono['bruto']);
        // Retención = Bruto * 0.07
        $retEsperada = bcmul((string) $abono['bruto'], '0.07', 2);
        $this->assertEquals($retEsperada, $abono['retencion_total']);
        $this->assertEquals(bcsub((string) $abono['bruto'], $retEsperada, 2), $abono['neto']);

        // 2. Retiro de cuenta gravada: 0,4% pleno
        $retiroGravado = CalculoImpuestoInversionService::calcularRetiroCuenta(
            montoRetiro: '5000000.00',
            esCuentaGmfExenta: false,
            fecha: '2026-06-01'
        );
        $this->assertEquals('20000.00', $retiroGravado['gmf_aplicado']); // 5M * 0.004 = 20.000
        $this->assertEquals('4980000.00', $retiroGravado['neto_desembolsado']);

        // 3. Retiro de cuenta exenta (Tope 350 UVT = 350 * 52.374 = 18.330.900 COP en 2026)
        // Primer retiro de 10.000.000 COP: dentro del tope mensual
        $retiroExento1 = CalculoImpuestoInversionService::calcularRetiroCuenta(
            montoRetiro: '10000000.00',
            esCuentaGmfExenta: true,
            retirosMesAcumuladosPrevios: '0.00',
            fecha: '2026-06-01'
        );
        $this->assertEquals('0.00', $retiroExento1['gmf_aplicado']);
        $this->assertEquals('10000000.00', $retiroExento1['neto_desembolsado']);

        // Segundo retiro de 10.000.000 COP en el mismo mes (Acumulado 20.000.000 > 18.330.900)
        // Base gravada = 20.000.000 - 18.330.900 = 1.669.100 COP
        // GMF 0,4% = 1.669.100 * 0.004 = 6.676,40 COP
        $retiroExento2 = CalculoImpuestoInversionService::calcularRetiroCuenta(
            montoRetiro: '10000000.00',
            esCuentaGmfExenta: true,
            retirosMesAcumuladosPrevios: '10000000.00',
            fecha: '2026-06-15'
        );
        $this->assertEquals('1669100.00', $retiroExento2['base_gravada_gmf']);
        $this->assertEquals('6676.40', $retiroExento2['gmf_aplicado']);
    }

    /**
     * Regla 3: Acciones / ETF con recálculo de costo promedio y clasificación fiscal (Art. 36-1, GO 15%, Renta Ordinaria).
     */
    public function test_acciones_costo_promedio_y_clasificacion_fiscal(): void
    {
        // 1. Recálculo de costo promedio ponderado tras compra adicional (Sección 9.6.1)
        // 100 acciones @ 50.000 + 100 acciones @ 70.000 + 20.000 comisión = 12.020.000 / 200 = 60.100
        $costoProm = CalculoImpuestoInversionService::recalcularCostoPromedio(
            unidadesPrevias: '100.00000000',
            costoPromedioPrevio: '50000.00000000',
            unidadesNuevas: '100.00000000',
            precioNuevo: '70000.00000000',
            comision: '20000.00'
        );
        $this->assertEquals('200.00000000', $costoProm['unidades_total']);
        $this->assertEquals('60100.00000000', $costoProm['costo_promedio_unitario']);

        // 2. Venta en BVC cumpliendo tope 3% (Art. 36-1 ET -> 0% no gravada)
        $ventaBvc = CalculoImpuestoInversionService::calcularVentaAcciones(
            unidadesVendidas: '50.00000000',
            precioVenta: '80000.00',
            costoPromedioUnitario: '60100.00000000',
            comisionVenta: '0.00',
            estaInscritoBvc: true,
            porcentajeCirculacionVendidoAnio: '1.50',
            fechaPrimeraCompra: '2026-01-01',
            fechaVenta: '2026-06-01'
        );
        $this->assertEquals('995000.00', $ventaBvc['utilidad_cop']); // 50 * (80.000 - 60.100) = 995.000
        $this->assertEquals('NO_GRAVADA', $ventaBvc['clase_fiscal']);
        $this->assertEquals('0.00', $ventaBvc['impuesto_renta_estimado']);

        // 3. Venta con tenencia >= 2 años (Ganancia Ocasional 15%)
        $ventaGo = CalculoImpuestoInversionService::calcularVentaAcciones(
            unidadesVendidas: '50.00000000',
            precioVenta: '80000.00',
            costoPromedioUnitario: '60000.00000000',
            estaInscritoBvc: false, // Exterior o sin beneficio
            fechaPrimeraCompra: '2024-01-01',
            fechaVenta: '2026-06-01' // 2 años y 5 meses
        );
        $this->assertEquals('1000000.00', $ventaGo['utilidad_cop']);
        $this->assertEquals('GANANCIA_OCASIONAL', $ventaGo['clase_fiscal']);
        $this->assertEquals('15.0000', $ventaGo['tarifa_impuesto']);
        $this->assertEquals('150000.00', $ventaGo['impuesto_renta_estimado']); // 1.000.000 * 0.15 = 150.000

        // 4. Venta con tenencia < 2 años (Renta ordinaria a tarifa marginal)
        $ventaRo = CalculoImpuestoInversionService::calcularVentaAcciones(
            unidadesVendidas: '50.00000000',
            precioVenta: '80000.00',
            costoPromedioUnitario: '60000.00000000',
            estaInscritoBvc: false,
            fechaPrimeraCompra: '2025-10-01',
            fechaVenta: '2026-06-01', // 8 meses
            tarifaMarginalUsuario: '28.00'
        );
        $this->assertEquals('RENTA_ORDINARIA', $ventaRo['clase_fiscal']);
        $this->assertEquals('280000.00', $ventaRo['impuesto_renta_estimado']); // 1.000.000 * 0.28 = 280.000

        // 5. Dividendos (30% distributivo / individual vs 15% acumulativo en ETF/Exterior)
        $divDistributivo = CalculoImpuestoInversionService::calcularDividendo(
            unidades: '100.00000000',
            valorDividendoPorUnidad: '1000.00',
            tipoReparto: 'DISTRIBUTIVO',
            fecha: '2026-06-01',
            mercado: 'EXTERIOR',
            subtipo: 'ETF'
        );
        $this->assertEquals('100000.00', $divDistributivo['bruto']);
        $this->assertEquals('30000.00', $divDistributivo['retencion_aplicada']); // 30%
        $this->assertEquals('70000.00', $divDistributivo['neto']);

        $divAcumulativo = CalculoImpuestoInversionService::calcularDividendo(
            unidades: '100.00000000',
            valorDividendoPorUnidad: '1000.00',
            tipoReparto: 'ACUMULATIVO',
            fecha: '2026-06-01',
            mercado: 'EXTERIOR',
            subtipo: 'ETF'
        );
        $this->assertEquals('15000.00', $divAcumulativo['retencion_aplicada']); // 15%
        $this->assertEquals('85000.00', $divAcumulativo['neto']);
    }

    /**
     * Regla 4: FIC con imputación prioritaria a utilidad acumulada y tarifa ponderada del fondo.
     */
    public function test_fic_imputacion_prioritaria_a_utilidad_y_tarifa_ponderada(): void
    {
        // Fondo con tarifa ponderada del 6.10% (ej. FIC Liquidez: 30% RF @ 4% + 70% RF @ 7%)
        $tarifaPonderada = '6.10';
        $totalAportes = '10000000.00';
        $valorActual = '11500000.00'; // Rendimiento acumulado = 1.500.000

        // Primera redención: 1.000.000 COP
        // Como M (1.000.000) <= U (1.500.000), se imputa 100% a utilidad
        $redencion1 = CalculoImpuestoInversionService::calcularRedencionFic(
            montoRedencion: '1000000.00',
            valorActualFondo: $valorActual,
            totalAportesNetos: $totalAportes,
            utilidadYaRedimida: '0.00',
            tarifaPonderada: $tarifaPonderada
        );

        $this->assertEquals('1000000.00', $redencion1['parte_utilidad']);
        $this->assertEquals('0.00', $redencion1['parte_capital']);
        $this->assertEquals('61000.00', $redencion1['retencion_aplicada']); // 1.000.000 * 0.061 = 61.000
        $this->assertEquals('939000.00', $redencion1['neto']);

        // Segunda redención: 1.000.000 COP (utilidad ya redimida = 1.000.000, pendiente U = 500.000)
        // Se imputa 500.000 a utilidad y 500.000 a capital
        $redencion2 = CalculoImpuestoInversionService::calcularRedencionFic(
            montoRedencion: '1000000.00',
            valorActualFondo: $valorActual,
            totalAportesNetos: $totalAportes,
            utilidadYaRedimida: '1000000.00',
            tarifaPonderada: $tarifaPonderada
        );

        $this->assertEquals('500000.00', $redencion2['parte_utilidad']);
        $this->assertEquals('500000.00', $redencion2['parte_capital']);
        $this->assertEquals('30500.00', $redencion2['retencion_aplicada']); // 500.000 * 0.061 = 30.500
        $this->assertEquals('969500.00', $redencion2['neto']);
    }

    /**
     * Regla 5: FPV con validación de topes y retención contingente 7% en retiro anticipado.
     */
    public function test_fpv_validacion_topes_y_retencion_retiro_anticipado(): void
    {
        // Ingreso anual: 100.000.000 COP
        // 30% = 30.000.000 COP; 3.800 UVT = 199.021.200 COP => Límite = 30.000.000 COP
        $tope1 = CalculoImpuestoInversionService::validarTopeAporteFpv(
            montoAporte: '20000000.00',
            ingresoAnual: '100000000.00',
            aportesAfcAnio: '0.00',
            aportesFpvAnio: '0.00',
            fecha: '2026-06-01'
        );
        $this->assertEquals('20000000.00', $tope1['parte_dentro_tope']);
        $this->assertFalse($tope1['excede_tope']);

        // Segundo aporte de 15.000.000 COP (ya aportó 20.000.000; cupo restante = 10.000.000)
        $tope2 = CalculoImpuestoInversionService::validarTopeAporteFpv(
            montoAporte: '15000000.00',
            ingresoAnual: '100000000.00',
            aportesAfcAnio: '0.00',
            aportesFpvAnio: '20000000.00',
            fecha: '2026-06-01'
        );
        $this->assertEquals('10000000.00', $tope2['parte_dentro_tope']);
        $this->assertEquals('5000000.00', $tope2['parte_excedente']);
        $this->assertTrue($tope2['excede_tope']);

        // Retiro anticipado sin permanencia (9.6.5: 7% sobre aportes + 7% sobre rendimientos)
        // Monto 10.000.000 COP (20% rendimientos = 2.000.000, 8.000.000 aportes)
        $retiroAnticipado = CalculoImpuestoInversionService::calcularRetiroFpv(
            montoRetiro: '10000000.00',
            cumplePermanenciaODestino: false,
            proporcionRendimiento: '0.2000',
            fecha: '2026-06-01'
        );
        $this->assertEquals('700000.00', $retiroAnticipado['retencion_aplicada']); // 7% * 10M = 700.000
        $this->assertEquals('0.00', $retiroAnticipado['gmf_aplicado']); // GMF = 0 en FPV (RF-IMP-03)
        $this->assertEquals('9300000.00', $retiroAnticipado['neto']);

        // Retiro cumpliendo permanencia >= 10 años
        $retiroCumple = CalculoImpuestoInversionService::calcularRetiroFpv(
            montoRetiro: '10000000.00',
            cumplePermanenciaODestino: true,
            proporcionRendimiento: '0.2000',
            fecha: '2026-06-01'
        );
        $this->assertEquals('0.00', $retiroCumple['retencion_aplicada']);
        $this->assertEquals('10000000.00', $retiroCumple['neto']);
    }

    /**
     * Regla 6: Cesantías con tabla de exención por UVT del Art. 206 Numeral 4 ET.
     */
    public function test_cesantias_tabla_exencion_art_206_num_4(): void
    {
        // 1. Salario 6m <= 350 UVT (<= 18.330.900 COP en 2026) -> 100% Exento
        $cesantias1 = CalculoImpuestoInversionService::calcularRetiroCesantias(
            montoRetirado: '5000000.00',
            ingresoLaboralPromedio6m: '10000000.00', // ~190.93 UVT
            fecha: '2026-06-01'
        );
        $this->assertEquals('5000000.00', $cesantias1['parte_exenta']);
        $this->assertEquals('0.00', $cesantias1['parte_gravada']);
        $this->assertEquals('100.00', $cesantias1['porcentaje_exento']);
        $this->assertEquals('0.00', $cesantias1['retencion_aplicada']);

        // 2. Salario 6m = 23.000.000 COP (~439.18 UVT -> Tramo 410 a 470 UVT -> 80% Exento)
        $cesantias2 = CalculoImpuestoInversionService::calcularRetiroCesantias(
            montoRetirado: '10000000.00',
            ingresoLaboralPromedio6m: '23000000.00',
            fecha: '2026-06-01'
        );
        $this->assertEquals('8000000.00', $cesantias2['parte_exenta']); // 80%
        $this->assertEquals('2000000.00', $cesantias2['parte_gravada']); // 20%
        $this->assertEquals('80.00', $cesantias2['porcentaje_exento']);
        $this->assertEquals('0.00', $cesantias2['retencion_aplicada']);
    }

    /**
     * Integración: Ciclo de vida completo por controlador y generación de puentes hacia Ingresos.
     */
    public function test_integracion_ciclo_de_vida_y_puentes_ingresos(): void
    {
        $ficCatalogo = FicCatalogo::first();
        $this->assertNotNull($ficCatalogo);

        // 1. Crear Inversión FPV
        $responseFpv = $this->post(route('inversiones.store'), [
            'nombre' => 'Mi Plan Pensional FPV',
            'tipo' => 'FPV',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-02-01',
            'monto_inicial' => 15000000.00,
            'ingreso_anual_estimado' => 120000000.00,
            'tipo_vinculacion' => 'DIRECTO',
        ]);
        $responseFpv->assertSessionHasNoErrors();
        $responseFpv->assertRedirect();

        $invFpv = Inversion::withoutGlobalScopes()->where('nombre', 'Mi Plan Pensional FPV')->first();
        $this->assertNotNull($invFpv, 'Inversión FPV no encontrada en la base de datos');
        $this->assertEquals($this->usuario->id, $invFpv->usuario_id);
        $this->assertNotNull($invFpv->detalleFpv);

        // Verificar que el aporte inicial generó registro en valor_deducible_registro
        $deducibleFpv = ValorDeducibleRegistro::where('inversion_id', $invFpv->id)
            ->where('concepto', 'APORTE_FPV')
            ->first();
        $this->assertNotNull($deducibleFpv);
        $this->assertEquals('15000000.00', $deducibleFpv->valor);

        // 2. Crear Inversión Acciones y registrar venta con ganancia
        $responseAccion = $this->post(route('inversiones.store'), [
            'nombre' => 'Acciones Ecopetrol Test',
            'tipo' => 'ACCION_ETF',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-01-10',
            'monto_inicial' => 2000000.00,
            'simbolo' => 'ECOPETROL',
            'unidades' => 1000.00000000,
            'valor_unidad' => 2000.00,
            'subtipo_renta_variable' => 'ACCION',
            'mercado' => 'BVC',
            'esta_inscrito_bvc' => 1,
        ]);
        $responseAccion->assertRedirect();

        $invAccion = Inversion::withoutGlobalScopes()->where('nombre', 'Acciones Ecopetrol Test')->first();
        $this->assertNotNull($invAccion);

        // Registrar venta de 500 acciones a $2.500 (Utilidad = 500 * 500 = $250.000)
        $responseVenta = $this->post(route('inversiones.movimientos.store', $invAccion->id), [
            'tipo' => 'VENTA',
            'fecha' => '2026-06-15',
            'monto' => 1250000.00,
            'unidades' => 500.00000000,
            'valor_unidad' => 2500.00,
        ]);
        $responseVenta->assertRedirect();

        // Verificar que la venta generó ingreso_inversion y valor_deducible_registro (no gravada BVC Art. 36-1)
        $ingresoVenta = IngresoInversion::where('inversion_id', $invAccion->id)
            ->where('tipo', 'UTILIDAD_VENTA')
            ->first();
        $this->assertNotNull($ingresoVenta);
        $this->assertEquals('250000.00', $ingresoVenta->valor_bruto);

        $deducibleBvc = ValorDeducibleRegistro::where('inversion_id', $invAccion->id)
            ->where('concepto', 'UTILIDAD_NO_GRAVADA_BVC')
            ->first();
        $this->assertNotNull($deducibleBvc);
        $this->assertEquals('250000.00', $deducibleBvc->valor);
    }

    /**
     * Prueba registro de un CDT exitoso con tasa_ea y cálculo de vencimiento.
     */
    public function test_registro_cdt_con_tasa_ea_exitosa(): void
    {
        $response = $this->post(route('inversiones.store'), [
            'nombre' => 'CDT Bancolombia 180 Días',
            'tipo' => 'CDT',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-06-01',
            'monto_inicial' => 10000000.00,
            'tasa_ea' => '11.5000',
            'tipo_tasa' => 'EA',
            'plazo_dias' => 180,
            'base_dias' => 365,
            'periodicidad_pago' => 'VENCIMIENTO',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $cdt = Inversion::withoutGlobalScopes()->where('nombre', 'CDT Bancolombia 180 Días')->first();
        $this->assertNotNull($cdt);
        $this->assertEquals('11.5000', (string) $cdt->tasa_ea);
        $this->assertEquals(180, $cdt->plazo_dias);
        $this->assertNotNull($cdt->detalleCdt);
        $this->assertEquals('11.5000', (string) $cdt->detalleCdt->tasa_valor);
    }

    /**
     * Prueba que valida la normalización de tasa_ea con coma decimal.
     */
    public function test_registro_cdt_con_tasa_ea_con_coma(): void
    {
        $response = $this->post(route('inversiones.store'), [
            'nombre' => 'CDT Davivienda Tasa Decimal Coma',
            'tipo' => 'CDT',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-06-01',
            'monto_inicial' => 5000000.00,
            'tasa_ea' => '12,75',
            'tipo_tasa' => 'EA',
            'plazo_dias' => 90,
            'base_dias' => 365,
            'periodicidad_pago' => 'VENCIMIENTO',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $cdt = Inversion::withoutGlobalScopes()->where('nombre', 'CDT Davivienda Tasa Decimal Coma')->first();
        $this->assertNotNull($cdt);
        $this->assertEquals('12.7500', (string) $cdt->tasa_ea);
    }

    /**
     * Prueba que tasa_ea es obligatoria para CDT y retorna el mensaje exacto.
     */
    public function test_registro_cdt_sin_tasa_ea_falla_validacion(): void
    {
        $response = $this->post(route('inversiones.store'), [
            'nombre' => 'CDT Sin Tasa',
            'tipo' => 'CDT',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-06-01',
            'monto_inicial' => 5000000.00,
            'tasa_ea' => '',
            'plazo_dias' => 90,
        ]);

        $response->assertSessionHasErrors([
            'tasa_ea' => 'La tasa de interés es obligatoria para este producto.',
        ]);
    }

    /**
     * Prueba que los fieldsets inactivos se renderizan deshabilitados en la vista.
     */
    public function test_formulario_inversiones_deshabilita_fieldsets_inactivos(): void
    {
        $response = $this->get(route('inversiones.index'));
        $response->assertOk();

        // En carga inicial sin tipo seleccionado, todos los fieldsets específicos deben estar deshabilitados
        $response->assertSee('id="section-cdt" disabled', false);
        $response->assertSee('id="section-cuenta" disabled', false);
        $response->assertSee('id="section-accion" disabled', false);
        $response->assertSee('id="section-cesantias" disabled', false);
    }

    /**
     * Prueba que el cálculo de CDT admite tasas con hasta 8 decimales (ej. 11.9000277%)
     * y cuadra exactamente con calculadoras bancarias al centavo ($1.190.002,77 bruto, $47.600,11 retención, $1.142.402,66 neto).
     */
    public function test_cdt_con_tasa_alta_precision_decimal_cuadra_con_calculadora_bancaria(): void
    {
        $capital = '10000000.00';
        $tasaEa = '11.9000277';
        $plazoDias = 360;
        $baseDias = 360;

        $calculo = CalculoImpuestoInversionService::calcularCdt(
            capital: $capital,
            tasaValor: $tasaEa,
            tipoTasa: 'EA',
            plazoDias: $plazoDias,
            baseDias: $baseDias,
            fecha: '2026-06-01'
        );

        // Bruto = 10.000.000 * 0.119000277 = 1.190.002,77
        $this->assertEquals('1190002.77', $calculo['bruto']);

        // Retención 4% = 1.190.002,77 * 0.04 = 47.600,11
        $this->assertEquals('47600.11', $calculo['retencion_total']);

        // Neto = 1.190.002,77 - 47.600,11 = 1.142.402,66
        $this->assertEquals('1142402.66', $calculo['neto']);

        // Capital + Neto = 11.142.402,66
        $totalAlVencimiento = bcadd($capital, $calculo['neto'], 2);
        $this->assertEquals('11142402.66', $totalAlVencimiento);

        // Registrar CDT en BD con tasa de 8 decimales y verificar persistencia íntegra
        $response = $this->post(route('inversiones.store'), [
            'nombre' => 'CDT Alta Precision Bancolombia',
            'tipo' => 'CDT',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-06-01',
            'monto_inicial' => 10000000.00,
            'tasa_ea' => '11.9000277',
            'tipo_tasa' => 'EA',
            'plazo_dias' => 360,
            'base_dias' => 360,
            'periodicidad_pago' => 'VENCIMIENTO',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inversiones', [
            'nombre' => 'CDT Alta Precision Bancolombia',
            'tasa_ea' => '11.90002770',
        ]);
        $this->assertDatabaseHas('detalle_cdt', [
            'tasa_valor' => '11.90002770',
        ]);
    }

    /**
     * Prueba que los dividendos en acciones BVC bajo el umbral de 1.090 UVT ($57.087.660 en 2026)
     * tienen retención en la fuente del 0% (Parágrafo Art. 242 E.T. / Ley 2277).
     */
    public function test_dividendos_bvc_bajo_umbral_1090_uvt_tienen_retencion_cero(): void
    {
        $unidades = '1000';
        $dividendoPorAccion = '10000.00'; // Total bruto: 10.000.000 COP (< 1.090 UVT = 57.087.660)

        $calculo = CalculoImpuestoInversionService::calcularDividendo(
            unidades: $unidades,
            valorDividendoPorUnidad: $dividendoPorAccion,
            tipoReparto: 'NO_APLICA',
            fecha: '2026-06-01',
            mercado: 'BVC',
            subtipo: 'ACCION'
        );

        $this->assertEquals('10000000.00', $calculo['bruto']);
        $this->assertEquals('0.00', $calculo['retencion_aplicada']);
        $this->assertEquals('10000000.00', $calculo['neto']);
        $this->assertStringContainsString('1.090 UVT', $calculo['detalle_lineas'][2]['norma']);
    }

    /**
     * Prueba que los dividendos en acciones BVC sobre 1.090 UVT ($57.087.660)
     * aplican el 15% de retención en la fuente únicamente sobre el exceso (Art. 242 E.T.).
     */
    public function test_dividendos_bvc_sobre_umbral_1090_uvt_aplican_quince_por_ciento_sobre_exceso(): void
    {
        $unidades = '7000';
        $dividendoPorAccion = '10000.00'; // Total bruto: 70.000.000 COP (> 1.090 UVT = 57.087.660)

        $calculo = CalculoImpuestoInversionService::calcularDividendo(
            unidades: $unidades,
            valorDividendoPorUnidad: $dividendoPorAccion,
            tipoReparto: 'NO_APLICA',
            fecha: '2026-06-01',
            mercado: 'BVC',
            subtipo: 'ACCION'
        );

        // Exceso = 70.000.000 - 57.087.660 = 12.912.340
        // Retención 15% = 12.912.340 * 0.15 = 1.936.851,00
        $this->assertEquals('70000000.00', $calculo['bruto']);
        $this->assertEquals('1936851.00', $calculo['retencion_aplicada']);
        $this->assertEquals('68063149.00', $calculo['neto']);
    }

    /**
     * Prueba que dividendos en ETF o mercado exterior aplican retención según tipo de reparto o impuesto en origen.
     */
    public function test_dividendos_exterior_aplican_retencion_segun_tipo_reparto(): void
    {
        $unidades = '100';
        $dividendoPorAccion = '100000.00'; // Total bruto: 10.000.000 COP

        $calculo = CalculoImpuestoInversionService::calcularDividendo(
            unidades: $unidades,
            valorDividendoPorUnidad: $dividendoPorAccion,
            tipoReparto: 'DISTRIBUTIVO',
            fecha: '2026-06-01',
            mercado: 'EXTERIOR',
            subtipo: 'ETF'
        );

        // 30% retención distributivo = 3.000.000
        $this->assertEquals('10000000.00', $calculo['bruto']);
        $this->assertEquals('3000000.00', $calculo['retencion_aplicada']);
        $this->assertEquals('7000000.00', $calculo['neto']);
    }

    /**
     * Prueba cálculo de dividendos en dólares (USD) con TRM manual ingresada.
     */
    public function test_dividendos_en_dolares_con_trm_manual(): void
    {
        $unidades = '100';
        $dividendoUsdPorUnidad = '1.50'; // 100 * 1.50 = 150.00 USD
        $trmManual = '4200.0000'; // 150 USD * 4.200 = 630.000 COP

        $calculo = CalculoImpuestoInversionService::calcularDividendo(
            unidades: $unidades,
            valorDividendoPorUnidad: $dividendoUsdPorUnidad,
            tipoReparto: 'DISTRIBUTIVO',
            impuestoOrigen: '0.00',
            fecha: '2026-06-01',
            mercado: 'EXTERIOR',
            subtipo: 'ETF',
            moneda: 'USD',
            trm: $trmManual
        );

        // Bruto en USD = 150.00
        $this->assertEquals('150.00', $calculo['bruto_original']);
        // Bruto en COP = 630.000,00
        $this->assertEquals('630000.00', $calculo['bruto_cop']);
        $this->assertEquals('630000.00', $calculo['bruto']);

        // Retención 30% en COP = 630.000 * 0.30 = 189.000 COP
        $this->assertEquals('189000.00', $calculo['retencion_aplicada']);
        // Retención equivalente en USD = 189.000 / 4.200 = 45.00 USD
        $this->assertEquals('45.00', $calculo['retencion_aplicada_original']);

        // Neto en COP = 630.000 - 189.000 = 441.000 COP
        $this->assertEquals('441000.00', $calculo['neto_cop']);
        $this->assertEquals('441000.00', $calculo['neto']);
        // Neto en USD = 150 - 45 = 105.00 USD
        $this->assertEquals('105.00', $calculo['neto_original']);
    }

    /**
     * Prueba que si no se envía TRM para un dividendo en USD, el sistema la toma automáticamente de la BD.
     */
    public function test_dividendos_en_dolares_toma_trm_automatica_de_la_bd(): void
    {
        // Asegurar que existe una TRM en la base de datos para la fecha
        TasaCambio::updateOrCreate(
            ['moneda' => 'USD', 'vigencia_desde' => '2026-07-01'],
            [
                'valor' => '4000.0000',
                'vigencia_hasta' => '2026-07-31',
                'fuente' => TasaCambio::FUENTE_OFICIAL,
                'consultado_en' => now(),
            ]
        );

        $unidades = '50';
        $dividendoUsdPorUnidad = '2.00'; // 50 * 2.00 = 100.00 USD

        $calculo = CalculoImpuestoInversionService::calcularDividendo(
            unidades: $unidades,
            valorDividendoPorUnidad: $dividendoUsdPorUnidad,
            tipoReparto: 'DISTRIBUTIVO',
            fecha: '2026-07-15',
            mercado: 'EXTERIOR',
            subtipo: 'ETF',
            moneda: 'USD',
            trm: null // Sin TRM, debe consultar la BD
        );

        $this->assertEquals('100.00', $calculo['bruto_original']);
        $this->assertEquals('4000.0000', $calculo['trm']);
        // 100 USD * 4.000 = 400.000 COP
        $this->assertEquals('400000.00', $calculo['bruto_cop']);
        // 30% retención = 120.000 COP
        $this->assertEquals('120000.00', $calculo['retencion_aplicada']);
        // Neto = 280.000 COP
        $this->assertEquals('280000.00', $calculo['neto_cop']);
    }

    /**
     * Prueba registro de inversión con dividendo en USD sin especificar TRM (persiste TRM de BD en detalle_renta_variable).
     */
    public function test_registro_inversion_con_dividendo_en_dolares_y_trm_automatica(): void
    {
        TasaCambio::updateOrCreate(
            ['moneda' => 'USD', 'vigencia_desde' => '2026-06-01'],
            [
                'valor' => '4100.0000',
                'vigencia_hasta' => '2026-06-30',
                'fuente' => TasaCambio::FUENTE_OFICIAL,
                'consultado_en' => now(),
            ]
        );

        $response = $this->post(route('inversiones.store'), [
            'nombre' => 'Vanguard S&P 500 VOO',
            'tipo' => 'ACCION_ETF',
            'subtipo_renta_variable' => 'ETF',
            'mercado' => 'EXTERIOR',
            'tipo_reparto' => 'DISTRIBUTIVO',
            'simbolo' => 'VOO',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-06-10',
            'monto_inicial' => 20000000.00,
            'unidades' => 10.00000000,
            'valor_unidad' => 2000000.00,
            'moneda_dividendo' => 'USD',
            'valor_dividendo_por_unidad' => '1.5500',
            'trm_dividendo' => '', // Se deja vacío intencionalmente para tomar de BD
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $inv = Inversion::withoutGlobalScopes()->where('nombre', 'Vanguard S&P 500 VOO')->first();
        $this->assertNotNull($inv);
        $this->assertNotNull($inv->detalleRentaVariable);
        $this->assertEquals('USD', $inv->detalleRentaVariable->moneda_dividendo);
        $this->assertEquals('1.5500', (string) $inv->detalleRentaVariable->valor_dividendo_por_unidad);
        // Debe haber tomado 4100.0000 automáticamente de la BD
        $this->assertEquals('4100.0000', (string) $inv->detalleRentaVariable->trm_dividendo);
    }

    /**
     * Prueba que el registro de una transacción de DIVIDENDO en USD convierte a COP con la TRM de BD.
     */
    public function test_transaccion_dividendo_en_dolares_aplica_trm_bd_y_retencion(): void
    {
        TasaCambio::updateOrCreate(
            ['moneda' => 'USD', 'vigencia_desde' => '2026-08-01'],
            [
                'valor' => '4200.0000',
                'vigencia_hasta' => '2026-08-31',
                'fuente' => TasaCambio::FUENTE_OFICIAL,
                'consultado_en' => now(),
            ]
        );

        $inv = Inversion::create([
            'usuario_id' => $this->usuario->id,
            'nombre' => 'Apple Inc AAPL',
            'tipo' => 'ACCION_ETF',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-08-05',
            'monto_inicial' => 10000000.00,
            'unidades_iniciales' => '100.00000000',
            'precio_unitario_compra' => '100000.00000000',
            'estado' => 'ACTIVA',
        ]);

        MovimientoInversion::create([
            'usuario_id' => $this->usuario->id,
            'inversion_id' => $inv->id,
            'tipo' => 'APORTE',
            'fecha' => '2026-08-05',
            'monto' => '10000000.00',
            'tasa_cambio_usada' => '1.0000',
            'monto_cop' => '10000000.00',
            'monto_bruto' => '10000000.00',
            'valor_unidad' => '100000.00000000',
            'unidades' => '100.00000000',
            'es_inicial' => true,
        ]);

        $inv->detalleRentaVariable()->create([
            'simbolo' => 'AAPL',
            'nombre_instrumento' => 'Apple Inc.',
            'tipo' => 'ACCION',
            'mercado' => 'EXTERIOR',
            'esta_inscrito_bvc' => false,
            'unidades_total' => '100.00000000',
            'costo_promedio_unitario' => '100000.00000000',
            'fecha_primera_compra' => '2026-08-05',
            'tipo_reparto' => 'DISTRIBUTIVO',
            'valor_dividendo_por_unidad' => '0.5000',
            'moneda_dividendo' => 'USD',
            'trm_dividendo' => '4200.0000',
        ]);

        // Registrar dividendo: 100 acciones * 0.50 USD = 50.00 USD.
        // Con TRM 4.200 = 210.000 COP bruto. Retención 30% = 63.000 COP. Neto = 147.000 COP.
        $response = $this->post(route('inversiones.movimientos.store', $inv->id), [
            'tipo' => 'DIVIDENDO',
            'fecha' => '2026-08-15',
            'monto' => '50.00',
            'unidades' => '100.00000000',
            'valor_unidad' => '0.5000',
            'moneda' => 'USD',
            // Sin tasa_cambio_usada, toma TRM automática de la BD
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $mov = MovimientoInversion::where('inversion_id', $inv->id)
            ->where('tipo', 'DIVIDENDO')
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals('210000.00', (string) $mov->monto_bruto);
        $this->assertEquals('63000.00', (string) $mov->retencion_aplicada);
        $this->assertEquals('147000.00', (string) $mov->monto);
        $this->assertEquals('147000.00', (string) $mov->monto_cop);
    }

    /**
     * Prueba endpoint de simulación por producto retorna 200 OK (resuelve error 500 previo).
     */
    public function test_simulacion_por_producto_endpoint_retorna_200_ok(): void
    {
        $response = $this->postJson(route('inversiones.simulaciones.producto'), [
            'tipo' => 'CDT',
            'capital' => '10000000.00',
            'tasa_ea' => '11.90',
            'plazo_dias' => 360,
            'base_dias' => 360,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.tipo', 'CDT');
        $response->assertJsonPath('data.resultado.bruto', '1190000.00');
        $response->assertJsonPath('data.resultado.retencion_total', '47600.00');
        $response->assertJsonPath('data.resultado.neto', '1142400.00');

        $responseAccion = $this->postJson(route('inversiones.simulaciones.producto'), [
            'tipo' => 'ACCION_ETF',
            'operacion' => 'DIVIDENDO',
            'unidades' => '100',
            'valor_dividendo_por_unidad' => '1.50',
            'moneda_dividendo' => 'USD',
            'trm_dividendo' => '4200.0000',
            'tipo_reparto' => 'DISTRIBUTIVO',
            'mercado' => 'EXTERIOR',
            'subtipo_renta_variable' => 'ETF',
        ]);

        $responseAccion->assertOk();
        $responseAccion->assertJsonPath('data.resultado.bruto_cop', '630000.00');
        $responseAccion->assertJsonPath('data.resultado.retencion_aplicada', '189000.00');
        $responseAccion->assertJsonPath('data.resultado.neto_cop', '441000.00');
    }

    /**
     * Prueba que los 6 productos restringen sus movimientos estrictamente a los permitidos.
     */
    public function test_movimientos_permitidos_estrictos_por_los_6_productos(): void
    {
        // 1. CDT al vencimiento vs periódico
        $cdtVencimiento = Inversion::create([
            'usuario_id' => $this->usuario->id,
            'nombre' => 'CDT Vencimiento Test',
            'tipo' => 'CDT',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-01-01',
            'monto_inicial' => 5000000.00,
            'estado' => 'ACTIVA',
        ]);
        $cdtVencimiento->detalleCdt()->create([
            'capital' => '5000000.00',
            'tasa_valor' => '12.0000',
            'tipo_tasa' => 'EA',
            'plazo_dias' => 180,
            'fecha_vencimiento' => '2026-07-01',
            'periodicidad_pago' => 'VENCIMIENTO',
        ]);
        $this->assertEquals(['REDENCION'], $cdtVencimiento->movimientosPermitidos());

        $cdtPeriodico = Inversion::create([
            'usuario_id' => $this->usuario->id,
            'nombre' => 'CDT Mensual Test',
            'tipo' => 'CDT',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-01-01',
            'monto_inicial' => 10000000.00,
            'estado' => 'ACTIVA',
        ]);
        $cdtPeriodico->detalleCdt()->create([
            'capital' => '10000000.00',
            'tasa_valor' => '12.0000',
            'tipo_tasa' => 'EA',
            'plazo_dias' => 360,
            'fecha_vencimiento' => '2027-01-01',
            'periodicidad_pago' => 'MENSUAL',
        ]);
        $this->assertEquals(['ABONO_INTERES', 'REDENCION'], $cdtPeriodico->movimientosPermitidos());

        // 2. Acciones y ETFs (solo COMPRA, VENTA, DIVIDENDO)
        $this->assertEquals(['COMPRA', 'VENTA', 'DIVIDENDO'], Inversion::MOVIMIENTOS_PERMITIDOS['ACCION_ETF']);

        // 3. FIC (solo APORTE, REDENCION)
        $this->assertEquals(['APORTE', 'REDENCION'], Inversion::MOVIMIENTOS_PERMITIDOS['FIC']);

        // 4. Cuenta Alto Rendimiento (APORTE, RETIRO, ABONO_INTERES)
        $this->assertEquals(['APORTE', 'RETIRO', 'ABONO_INTERES'], Inversion::MOVIMIENTOS_PERMITIDOS['CUENTA_ALTO_RENDIMIENTO']);

        // 5. FPV (APORTE, RETIRO)
        $this->assertEquals(['APORTE', 'RETIRO'], Inversion::MOVIMIENTOS_PERMITIDOS['FPV']);

        // 6. Cesantías (APORTE, RETIRO)
        $this->assertEquals(['APORTE', 'RETIRO'], Inversion::MOVIMIENTOS_PERMITIDOS['CESANTIAS']);
    }

    /**
     * Prueba que el cobro de intereses periódicos (ABONO_INTERES) en un CDT se resta de la ganancia proyectada pendiente.
     */
    public function test_cdt_abono_interes_resta_de_ganancia_proyectada(): void
    {
        $cdt = Inversion::create([
            'usuario_id' => $this->usuario->id,
            'nombre' => 'CDT Con Abono Interes',
            'tipo' => 'CDT',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-01-01',
            'monto_inicial' => 10000000.00,
            'estado' => 'ACTIVA',
        ]);
        $cdt->detalleCdt()->create([
            'capital' => '10000000.00',
            'tasa_valor' => '12.0000',
            'tipo_tasa' => 'EA',
            'plazo_dias' => 360,
            'base_dias' => 360,
            'fecha_vencimiento' => '2027-01-01',
            'periodicidad_pago' => 'MENSUAL',
        ]);

        // Rendimiento proyectado inicial sin abonos: 10.000.000 * 0.12 = 1.200.000 COP
        $presInicial = InversionService::presentar($cdt, CarbonImmutable::parse('2026-01-02'));
        $this->assertEquals('1200000.00', $presInicial['rendimiento_estimado']);

        // Registrar un cobro de intereses periódicos de $100.000 COP
        $response = $this->post(route('inversiones.movimientos.store', $cdt->id), [
            'tipo' => 'ABONO_INTERES',
            'fecha' => '2026-02-01',
            'monto' => '100000.00',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Tras el cobro de 100.000, la ganancia proyectada pendiente debe ser 1.200.000 - 100.000 = 1.100.000 COP
        $presDespues = InversionService::presentar($cdt->fresh(), CarbonImmutable::parse('2026-02-02'));
        $this->assertEquals('1100000.00', $presDespues['rendimiento_estimado']);
        $this->assertEquals('100000.00', $presDespues['intereses_cobrados_cdt']);
    }

    /**
     * Prueba que la vista show se renderiza con éxito (HTTP 200) para acciones y CDT.
     */
    public function test_vista_show_renderiza_correctamente(): void
    {
        $this->actingAs($this->usuario);

        $accion = Inversion::create([
            'usuario_id' => $this->usuario->id,
            'nombre' => 'Ecopetrol Accion Test',
            'tipo' => 'ACCION_ETF',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-01-01',
            'monto_inicial' => 5000000.00,
            'unidades_iniciales' => '2000.00000000',
            'precio_unitario_compra' => '2500.00000000',
            'simbolo' => 'ECOPETROL',
            'estado' => 'ACTIVA',
        ]);
        $accion->detalleRentaVariable()->create([
            'simbolo' => 'ECOPETROL',
            'nombre_instrumento' => 'Ecopetrol S.A.',
            'tipo' => 'ACCION',
            'mercado' => 'BVC',
            'unidades_total' => '2000.00000000',
            'costo_promedio_unitario' => '2500.00000000',
            'fecha_primera_compra' => '2026-01-01',
            'tipo_reparto' => 'NO_APLICA',
            'valor_dividendo_por_unidad' => '250.00',
            'moneda_dividendo' => 'COP',
        ]);

        $responseAccion = $this->get(route('inversiones.show', $accion->id));
        $responseAccion->assertOk();
        $responseAccion->assertSee('Ecopetrol Accion Test');
        $responseAccion->assertSee('Registrar Transacción / Movimiento Oficial');

        $cdt = Inversion::create([
            'usuario_id' => $this->usuario->id,
            'nombre' => 'CDT Test Vista',
            'tipo' => 'CDT',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-01-01',
            'monto_inicial' => 10000000.00,
            'estado' => 'ACTIVA',
        ]);
        $cdt->detalleCdt()->create([
            'capital' => '10000000.00',
            'tasa_valor' => '12.0000',
            'tipo_tasa' => 'EA',
            'plazo_dias' => 360,
            'base_dias' => 360,
            'fecha_vencimiento' => '2027-01-01',
            'periodicidad_pago' => 'MENSUAL',
        ]);

        $responseCdt = $this->get(route('inversiones.show', $cdt->id));
        $responseCdt->assertOk();
        $responseCdt->assertSee('CDT Test Vista');
    }

    public function test_reportes_retenciones_y_utilidades(): void
    {
        $this->actingAs($this->usuario);
        $res1 = $this->get(route('inversiones.reportes.retenciones'));
        $res1->assertOk();
        $res1->assertViewHas('aniosDisponibles');

        $res2 = $this->get(route('inversiones.reportes.utilidades'));
        $res2->assertOk();
        $res2->assertViewHas('aniosDisponibles');

        // Consultar con año explícito
        $res3 = $this->get(route('inversiones.reportes.retenciones', ['anio' => 2027]));
        $res3->assertOk();
        $res4 = $this->get(route('inversiones.reportes.utilidades', ['anio' => 2027]));
        $res4->assertOk();
    }

    /**
     * Prueba que al registrar un dividendo se crea el registro de ingreso neto en ingresos,
     * se guarda la retención del 30% en valor_deducible_registro y se sincroniza ingreso_id.
     */
    public function test_dividendo_se_registra_como_ingreso_neto_y_deduccion_en_bd(): void
    {
        $this->actingAs($this->usuario);

        $accionExt = Inversion::create([
            'usuario_id' => $this->usuario->id,
            'nombre' => 'Apple Inc Div Test',
            'tipo' => 'ACCION_ETF',
            'moneda' => 'USD',
            'fecha_apertura' => '2026-01-01',
            'monto_inicial' => 1000.00,
            'estado' => 'ACTIVA',
        ]);
        $accionExt->detalleRentaVariable()->create([
            'simbolo' => 'AAPL',
            'nombre_instrumento' => 'Apple Inc',
            'tipo' => 'ACCION',
            'mercado' => 'EXTERIOR',
            'unidades_total' => '100.00000000',
            'costo_promedio_unitario' => '10.00000000',
            'fecha_primera_compra' => '2026-01-01',
            'tipo_reparto' => 'DISTRIBUTIVO',
            'valor_dividendo_por_unidad' => '0.50',
            'moneda_dividendo' => 'USD',
        ]);

        // Registrar dividendo: 100 acciones * 0.50 USD = 50 USD bruto. ReteFuente 30% = 15 USD. Neto = 35 USD.
        $response = $this->post(route('inversiones.movimientos.store', $accionExt->id), [
            'tipo' => 'DIVIDENDO',
            'fecha' => '2026-06-15',
            'monto' => '35.00',
            'unidades' => '100',
            'valor_unidad' => '0.50',
            'moneda' => 'USD',
            'tasa_cambio_usada' => '4000.0000',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // 1. Verificar movimiento
        $mov = MovimientoInversion::where('inversion_id', $accionExt->id)->where('tipo', 'DIVIDENDO')->first();
        $this->assertNotNull($mov);
        $this->assertEquals('35.00', $mov->monto);
        $this->assertEquals('50.00', $mov->monto_bruto);
        $this->assertEquals('15.00', $mov->retencion_aplicada);
        $this->assertNotNull($mov->ingreso_id);

        // 2. Verificar ingreso oficial en la tabla ingresos
        $this->assertDatabaseHas('ingresos', [
            'id' => $mov->ingreso_id,
            'usuario_id' => $this->usuario->id,
            'clasificacion_tributaria' => 'DIVIDENDOS',
            'moneda' => 'USD',
            'monto_original' => '35.00',
            'monto_neto_cop' => '140000.00',
        ]);

        // 3. Verificar deducción tributaria (30% de retención convertida a COP) en valor_deducible_registro
        $this->assertDatabaseHas('valor_deducible_registro', [
            'transaccion_id' => $mov->id,
            'concepto' => 'RETENCION_DIVIDENDOS',
            'valor' => '60000.00',
        ]);

        // 4. Al eliminar la transacción, el ingreso se marca como eliminado y la deducción se remueve
        $this->delete(route('inversiones.movimientos.destroy', [$accionExt->id, $mov->id]));
        $this->assertNotNull(\App\Models\Ingreso::withTrashed()->find($mov->ingreso_id)?->eliminado_en);
        $this->assertDatabaseMissing('valor_deducible_registro', ['transaccion_id' => $mov->id]);
    }

    /**
     * Prueba que el registro de una valoración manual de mercado actualiza el precio actual
     * y genera la ganancia o pérdida no realizada en la presentación de la inversión.
     */
    public function test_valoracion_manual_mercado_actualiza_precio_y_ganancia_no_realizada(): void
    {
        $this->actingAs($this->usuario);

        $accion = Inversion::create([
            'usuario_id' => $this->usuario->id,
            'nombre' => 'Ecopetrol Valoracion Test',
            'tipo' => 'ACCION_ETF',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-01-01',
            'monto_inicial' => 5000000.00,
            'unidades_iniciales' => '2000.00000000',
            'precio_unitario_compra' => '2500.00000000',
            'estado' => 'ACTIVA',
        ]);
        $accion->detalleRentaVariable()->create([
            'simbolo' => 'ECOPETROL',
            'nombre_instrumento' => 'Ecopetrol S.A.',
            'tipo' => 'ACCION',
            'mercado' => 'BVC',
            'unidades_total' => '2000.00000000',
            'costo_promedio_unitario' => '2500.00000000',
            'fecha_primera_compra' => '2026-01-01',
            'tipo_reparto' => 'NO_APLICA',
        ]);

        // El usuario registra una cotización de $2.800 COP por acción (subió de 2.500 a 2.800)
        $response = $this->post(route('inversiones.valoraciones.store', $accion->id), [
            'fecha' => '2026-06-01',
            'precio_unitario' => '2800.00000000',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Verificar que el detalle de renta variable actualizó su precio actual
        $this->assertDatabaseHas('detalle_renta_variable', [
            'inversion_id' => $accion->id,
            'valor_actual_unidad' => '2800.00000000',
        ]);

        // Verificar la presentación: ganancia no realizada = (2800 - 2500) * 2000 = 600.000 COP (+12%)
        $pres = InversionService::presentar($accion->fresh(), CarbonImmutable::parse('2026-06-02'));
        $vnr = $pres['valoracion_no_realizada'];
        $this->assertNotNull($vnr);
        $this->assertEquals('600000.00', $vnr['valor']);
        $this->assertEquals('12.00', $vnr['porcentaje']);

        // Verificar vista show de ACCION_ETF: debe renderizar la gráfica de valoración continua
        $showRes = $this->get(route('inversiones.show', $accion->id));
        $showRes->assertOk();
        $showRes->assertSee('accionValoracionCanvas');
        $showRes->assertSee('Evolución y Valoración de Mercado');
        $showRes->assertSee('renderAccionChart');

        // Registrar una segunda valoración en pérdida (ej. $2.300 COP)
        $this->post(route('inversiones.valoraciones.store', $accion->id), [
            'fecha' => '2026-08-01',
            'precio_unitario' => '2300.00000000',
        ])->assertSessionHasNoErrors();

        $showRes2 = $this->get(route('inversiones.show', $accion->id));
        $showRes2->assertOk();
        $showRes2->assertSee('2300');
    }

    /**
     * Prueba que al vender acciones o ETFs:
     * 1. La utilidad se registra como ingreso oficial en el módulo de Ingresos (RENTAS_CAPITAL si tenencia < 2 años).
     * 2. Si la inversión es en USD, los montos del movimiento se conservan en USD y monto_cop en COP.
     * 3. Al vender la totalidad de las unidades, la inversión pasa automáticamente a estado CERRADA.
     * 4. RentabilidadService refleja una ganancia positiva (venta + dividendos - aportes) y no una pérdida.
     */
    public function test_venta_accion_o_etf_se_integra_en_modulo_ingresos_segun_tenencia_y_cierra_inversion(): void
    {
        $usuario = $this->usuario;
        $this->actingAs($usuario);

        $accion = Inversion::create([
            'usuario_id' => $usuario->id,
            'nombre' => 'Apple Inc.',
            'tipo' => 'ACCION_ETF',
            'entidad' => 'Broker Internacional',
            'moneda' => 'USD',
            'fecha_apertura' => '2026-01-15',
            'monto_inicial' => '1000.00',
            'nivel_riesgo' => 'ALTO',
            'estado' => 'ACTIVA',
        ]);
        $accion->movimientos()->create([
            'usuario_id' => $usuario->id,
            'tipo' => 'APORTE',
            'fecha' => '2026-01-15',
            'monto' => '1000.00',
            'monto_bruto' => '1000.00',
            'tasa_cambio_usada' => '4000.0000',
            'monto_cop' => '4000000.00',
            'unidades' => '10.00000000',
            'valor_unidad' => '100.00000000',
            'es_inicial' => true,
        ]);
        $accion->detalleRentaVariable()->create([
            'simbolo' => 'AAPL',
            'nombre_instrumento' => 'Apple Inc.',
            'tipo' => 'ACCION',
            'mercado' => 'EXTERIOR',
            'unidades_total' => '10.00000000',
            'costo_promedio_unitario' => '100.00000000',
            'fecha_primera_compra' => '2026-01-15',
            'tipo_reparto' => 'DISTRIBUTIVO',
            'trm_compra' => '4000.0000',
        ]);

        // Vender las 10 unidades a $120 USD cada una (ingreso total 1.200 USD, utilidad 200 USD)
        // Tenencia: de 2026-01-15 a 2026-06-15 = 5 meses (< 2 años -> RENTAS_CAPITAL)
        $response = $this->post(route('inversiones.movimientos.store', $accion->id), [
            'tipo' => 'VENTA',
            'fecha' => '2026-06-15',
            'unidades' => '10.00000000',
            'valor_unidad' => '120.00000000',
            'comision' => '0.00',
            'tasa_cambio_usada' => '4100.0000',
        ]);
        $response->assertSessionHasNoErrors();

        // 1. Verificar movimiento de venta: monto en USD (1.200 USD) y monto_cop en COP (1.200 * 4.100 = 4.920.000 COP)
        $movVenta = MovimientoInversion::where('inversion_id', $accion->id)->where('tipo', 'VENTA')->first();
        $this->assertNotNull($movVenta);
        $this->assertEquals('1200.00', $movVenta->monto_bruto);
        $this->assertEquals('1200.00', $movVenta->monto);
        $this->assertEquals('4920000.00', $movVenta->monto_cop);

        // 2. Verificar que la inversión pasó a CERRADA y unidades a 0
        $this->assertEquals('CERRADA', $accion->fresh()->estado);
        $this->assertEquals('0.00000000', $accion->fresh()->detalleRentaVariable->unidades_total);

        // 3. Verificar que se creó el registro en el módulo de Ingresos (RENTAS_CAPITAL, 200 USD / 820.000 COP)
        $this->assertNotNull($movVenta->ingreso_id);
        $ingreso = \App\Models\Ingreso::find($movVenta->ingreso_id);
        $this->assertNotNull($ingreso);
        $this->assertEquals('RENTAS_CAPITAL', $ingreso->clasificacion_tributaria);
        $this->assertEquals('USD', $ingreso->moneda);
        $this->assertEquals('200.00', $ingreso->monto_original);
        $this->assertEquals('920000.00', $ingreso->monto_bruto_cop);

        // 4. RentabilidadService: refleja ganancia neta positiva de +200 USD (+20.00% ROI) y no una pérdida
        $indicadores = \App\Services\RentabilidadService::indicadores($accion->fresh(), CarbonImmutable::parse('2026-06-16'));
        $this->assertEquals('200.00', $indicadores['ganancia']);
        $this->assertEquals('20.00', $indicadores['roi']);
        $this->assertEquals('GANANCIA', $indicadores['resultado']);
    }

    /**
     * Prueba que al vender acciones poseídas por 2 o más años, la utilidad se clasifica
     * como GANANCIA_OCASIONAL en el módulo de Ingresos.
     */
    public function test_venta_accion_poseida_mas_de_dos_anios_se_integra_como_ganancia_ocasional(): void
    {
        $usuario = $this->usuario;
        $this->actingAs($usuario);

        $accion = Inversion::create([
            'usuario_id' => $usuario->id,
            'nombre' => 'Microsoft Corp',
            'tipo' => 'ACCION_ETF',
            'entidad' => 'Broker Internacional',
            'moneda' => 'COP',
            'fecha_apertura' => '2023-01-10',
            'monto_inicial' => '5000000.00',
            'nivel_riesgo' => 'MEDIO',
            'estado' => 'ACTIVA',
        ]);
        $accion->movimientos()->create([
            'usuario_id' => $usuario->id,
            'tipo' => 'APORTE',
            'fecha' => '2023-01-10',
            'monto' => '5000000.00',
            'monto_bruto' => '5000000.00',
            'tasa_cambio_usada' => '1.0000',
            'monto_cop' => '5000000.00',
            'unidades' => '50.00000000',
            'valor_unidad' => '100000.00000000',
            'es_inicial' => true,
        ]);
        $accion->detalleRentaVariable()->create([
            'simbolo' => 'MSFT',
            'nombre_instrumento' => 'Microsoft Corp',
            'tipo' => 'ACCION',
            'mercado' => 'EXTERIOR',
            'unidades_total' => '50.00000000',
            'costo_promedio_unitario' => '100000.00000000',
            'fecha_primera_compra' => '2023-01-10', // Más de 2 años respecto a 2026
            'tipo_reparto' => 'NO_APLICA',
        ]);

        // Venta en 2026 (tenencia 3 años > 2 años)
        $response = $this->post(route('inversiones.movimientos.store', $accion->id), [
            'tipo' => 'VENTA',
            'fecha' => '2026-03-01',
            'unidades' => '50.00000000',
            'valor_unidad' => '150000.00000000', // 7.500.000 venta, 2.500.000 utilidad
            'comision' => '0.00',
        ]);
        $response->assertSessionHasNoErrors();

        $movVenta = MovimientoInversion::where('inversion_id', $accion->id)->where('tipo', 'VENTA')->first();
        $this->assertNotNull($movVenta->ingreso_id);

        $ingreso = \App\Models\Ingreso::find($movVenta->ingreso_id);
        $this->assertNotNull($ingreso);
        $this->assertEquals('GANANCIA_OCASIONAL', $ingreso->clasificacion_tributaria);
        $this->assertEquals('2500000.00', $ingreso->monto_bruto_cop);
    }

    /**
     * Prueba que para CDT se oculta la sección de valoración de mercado, se muestra la gráfica
     * de proyección de ganancia con retención del 4% y se permite simular fecha de redención futura.
     */
    public function test_cdt_oculta_valoracion_muestra_proyeccion_y_permite_simular_redencion_futura(): void
    {
        $this->actingAs($this->usuario);

        $cdt = Inversion::create([
            'usuario_id' => $this->usuario->id,
            'nombre' => 'CDT Simulación Aula',
            'tipo' => 'CDT',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-11-09',
            'monto_inicial' => 1000000.00,
            'tasa_ea' => '11.0000',
            'plazo_dias' => 180,
            'fecha_vencimiento' => '2027-05-08',
            'estado' => 'ACTIVA',
        ]);

        $cdt->movimientos()->create([
            'usuario_id' => $this->usuario->id,
            'tipo' => 'APORTE',
            'fecha' => '2026-11-09',
            'monto' => 1000000.00,
            'monto_cop' => 1000000.00,
            'monto_bruto' => 1000000.00,
            'es_inicial' => true,
        ]);

        $cdt->detalleCdt()->create([
            'capital' => '1000000.00',
            'tasa_valor' => '11.0000',
            'tipo_tasa' => 'EA',
            'plazo_dias' => 180,
            'base_dias' => 365,
            'fecha_vencimiento' => '2027-05-08',
            'periodicidad_pago' => 'VENCIMIENTO',
        ]);

        // 1. Verificar vista show: sección de valoración oculta y gráfica de proyección visible
        $responseShow = $this->get(route('inversiones.show', $cdt->id));
        $responseShow->assertOk();
        $responseShow->assertDontSee('Actualizar Valoración de Mercado (Cotización Actual)');
        $responseShow->assertSee('Proyección de Ganancia y Rendimiento del CDT');
        $responseShow->assertSee('cdtProjectionCanvas');

        // 2. Simular redención en fecha futura (2027-05-08)
        $responseRedencion = $this->post(route('inversiones.movimientos.store', $cdt->id), [
            'tipo' => 'REDENCION',
            'fecha' => '2027-05-08',
            'monto' => 1000000.00,
            'nota' => 'Simulación clase redención CDT con retención 4%',
        ]);

        $responseRedencion->assertSessionHasNoErrors();

        // 3. Verificar estado LIQUIDADA y retención del 4% calculada
        $this->assertEquals('LIQUIDADA', $cdt->fresh()->estado);

        $movRedencion = MovimientoInversion::where('inversion_id', $cdt->id)
            ->where('tipo', 'REDENCION')
            ->first();

        $this->assertNotNull($movRedencion);
        $this->assertEquals('2027-05-08', $movRedencion->fecha->toDateString());
        // Retención 4% sobre los intereses causados (~52.812,56 * 0.04 = ~2.112,50)
        $this->assertTrue(bccomp((string) $movRedencion->retencion_aplicada, '2000.00', 2) === 1);
        $this->assertTrue(bccomp((string) $movRedencion->monto_cop, '1000000.00', 2) === 1);

        // 4. Verificar puentes tributarios (IngresoInversion y ValorDeducibleRegistro)
        $this->assertDatabaseHas('ingreso_inversion', [
            'inversion_id' => $cdt->id,
            'transaccion_id' => $movRedencion->id,
            'tipo' => 'RENDIMIENTO',
        ]);

        $this->assertDatabaseHas('valor_deducible_registro', [
            'inversion_id' => $cdt->id,
            'transaccion_id' => $movRedencion->id,
            'concepto' => 'RETEFUENTE_RENTA_FIJA',
        ]);
    }

    /**
     * Prueba que las transacciones y formularios acepten montos formateados
     * con puntos de miles y comas decimales (formato latino / colombiano).
     */
    public function test_transaccion_con_montos_formateados_con_puntos_y_comas(): void
    {
        $this->actingAs($this->usuario);

        $cuenta = Inversion::create([
            'usuario_id' => $this->usuario->id,
            'nombre' => 'Cuenta Lulo Formateada',
            'tipo' => 'CUENTA_ALTO_RENDIMIENTO',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-11-01',
            'monto_inicial' => 5000000.00,
            'saldo_actual' => 5000000.00,
            'tasa_ea' => '12.0000',
            'estado' => 'ACTIVA',
        ]);

        $cuenta->detalleCuenta()->create([
            'saldo_inicial' => '5000000.00',
            'tasa_ea' => '12.0000',
            'es_cuenta_gmf_exenta' => true,
        ]);

        // Registrar aporte con separadores de miles: $ 1.050.700,50
        $response = $this->post(route('inversiones.movimientos.store', $cuenta->id), [
            'tipo' => 'APORTE',
            'fecha' => '2026-11-05',
            'monto' => '1.050.700,50',
            'nota' => 'Consignación con formato de miles y coma decimal',
        ]);

        $response->assertSessionHasNoErrors();

        $mov = MovimientoInversion::where('inversion_id', $cuenta->id)
            ->where('tipo', 'APORTE')
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals('1050700.50', (string) $mov->monto);

        // Registrar otro movimiento con múltiples puntos de miles: 2.000.000
        $response2 = $this->post(route('inversiones.movimientos.store', $cuenta->id), [
            'tipo' => 'RETIRO',
            'fecha' => '2026-11-06',
            'monto' => '2.000.000',
            'nota' => 'Retiro con puntos de miles',
        ]);

        $response2->assertSessionHasNoErrors();

        $mov2 = MovimientoInversion::where('inversion_id', $cuenta->id)
            ->where('tipo', 'RETIRO')
            ->first();

        $this->assertNotNull($mov2);
        $this->assertEquals('2000000.00', (string) $mov2->monto);
    }

    /**
     * Prueba el flujo del wizard en 3 pasos para productos administrados (FIC, FPV, Cesantías):
     * Monto inicial, selección de riesgo (Bajo/Medio/Alto), rentabilidad esperada y plazo libre.
     */
    public function test_wizard_creacion_y_valoracion_porcentual_fondos_administrados(): void
    {
        $response = $this->post(route('inversiones.store'), [
            'nombre' => 'Fondo Renta Conservador Test',
            'tipo' => 'FIC',
            'entidad' => 'Fiduciaria de Prueba',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-05-01',
            'monto_inicial' => 10000000.00,
            'nivel_riesgo' => 'BAJO',
            'rentabilidad_esperada_porc' => 5.5,
            'plazo_meses_esperado' => 12,
        ]);

        $response->assertSessionHasNoErrors();

        $fic = Inversion::where('nombre', 'Fondo Renta Conservador Test')->first();
        $this->assertNotNull($fic);
        $this->assertEquals('FIC', $fic->tipo);
        $this->assertEquals('BAJO', $fic->nivel_riesgo);
        $this->assertEquals('5.5000', (string) $fic->rentabilidad_esperada_porc);
        $this->assertEquals(12, $fic->plazo_meses_esperado);

        // Valoración inicial registrada automáticamente con el monto inicial
        $valInicial = ValoracionInversion::where('inversion_id', $fic->id)->first();
        $this->assertNotNull($valInicial);
        $this->assertEquals('10000000.00', (string) $valInicial->valor);

        // Registrar nueva valoración de mercado en porcentaje (% E.A. observado en extracto)
        // Ejemplo: El fondo rentó al 4.0% E.A. al 01/11/2026 (184 días después)
        $responseVal = $this->post(route('inversiones.valoraciones.store', $fic->id), [
            'fecha' => '2026-11-01',
            'rentabilidad_real_ea' => '4.00',
        ]);

        $responseVal->assertSessionHasNoErrors();

        $valCorte = ValoracionInversion::where('inversion_id', $fic->id)
            ->where('fecha', '2026-11-01')
            ->first();

        $this->assertNotNull($valCorte);
        $this->assertEquals(4.00, (float) $valCorte->precio_unitario);
        // Saldo calculado con interés compuesto exacto: 10M * (1 + 0.04)^(184/365) = ~10.199.387 COP
        $this->assertGreaterThan(10000000.00, (float) $valCorte->valor);

        // Consultar la vista show para verificar que renderiza la gráfica comparativa y métricas
        $responseShow = $this->get(route('inversiones.show', $fic->id));
        $responseShow->assertOk();
        $responseShow->assertSee('administradosValoracionCanvas');
        $responseShow->assertSee('Evolución y Rendimiento de Cartera');
        $responseShow->assertSee('Rentabilidad observada (% E.A.)');

        // Probar también creación de FPV con wizard (Riesgo Alto, rentabilidad esperada 15%)
        $responseFpv = $this->post(route('inversiones.store'), [
            'nombre' => 'FPV Crecimiento Global Test',
            'tipo' => 'FPV',
            'entidad' => 'Skandia Pensiones',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-06-01',
            'monto_inicial' => 5000000.00,
            'nivel_riesgo' => 'ALTO',
            'rentabilidad_esperada_porc' => 15.0,
            'plazo_meses_esperado' => 24,
        ]);
        $responseFpv->assertSessionHasNoErrors();
        $fpv = Inversion::where('nombre', 'FPV Crecimiento Global Test')->first();
        $this->assertNotNull($fpv);
        $this->assertEquals('FPV', $fpv->tipo);
        $this->assertEquals('ALTO', $fpv->nivel_riesgo);

        // Probar también creación de Cesantías con wizard (Riesgo Medio, rentabilidad esperada 8.5%)
        $responseCes = $this->post(route('inversiones.store'), [
            'nombre' => 'Cesantías Porvenir Largo Plazo Test',
            'tipo' => 'CESANTIAS',
            'entidad' => 'Porvenir Cesantías',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-07-01',
            'monto_inicial' => 8000000.00,
            'nivel_riesgo' => 'MEDIO',
            'rentabilidad_esperada_porc' => 8.5,
            'plazo_meses_esperado' => 36,
        ]);
        $responseCes->assertSessionHasNoErrors();
        $ces = Inversion::where('nombre', 'Cesantías Porvenir Largo Plazo Test')->first();
        $this->assertNotNull($ces);
        $this->assertEquals('CESANTIAS', $ces->tipo);
        $this->assertEquals('MEDIO', $ces->nivel_riesgo);

        // Verificación específica del caso reportado: Registro de FIC con $1.000.000 COP y 5.5% EA
        // No debe mutar a $5.50 COP ni mostrar unidades acumuladas
        $responseFic1M = $this->post(route('inversiones.store'), [
            'nombre' => 'Fondo Fiduciaria 1M Directo',
            'tipo' => 'FIC',
            'entidad' => 'Fiduciaria de Prueba',
            'moneda' => 'COP',
            'fecha_apertura' => '2026-05-01',
            'monto_inicial' => 1000000.00,
            'nivel_riesgo' => 'BAJO',
            'rentabilidad_esperada_porc' => 5.5,
            'plazo_meses_esperado' => 12,
        ]);
        $responseFic1M->assertSessionHasNoErrors();
        $fic1M = Inversion::where('nombre', 'Fondo Fiduciaria 1M Directo')->first();
        $this->assertNotNull($fic1M);

        $show1M = $this->get(route('inversiones.show', $fic1M->id));
        $show1M->assertOk();
        $show1M->assertDontSee('Unidades acumuladas');
        $show1M->assertDontSee('$ 5.50 COP');
        $show1M->assertDontSee('-$ 999,994.50');
        $show1M->assertSee('1,000,000.00');

        $presentacion1M = InversionService::presentar($fic1M, CarbonImmutable::parse('2026-05-01'));
        $this->assertEquals('1000000.00', (string) $presentacion1M['valor_actual']);
        $this->assertNull($presentacion1M['unidades_actuales']);
        $this->assertNull($presentacion1M['valoracion_no_realizada']);

        // Verificación de retiro parcial en FPV sin unidades:
        // No debe cerrarse la inversión si aún queda saldo disponible
        $responseRetiroFpv = $this->post(route('inversiones.movimientos.store', $fpv->id), [
            'tipo' => 'RETIRO',
            'fecha' => '2026-06-15',
            'monto' => 2000000.00,
            'cumple_permanencia' => false,
        ]);
        $responseRetiroFpv->assertSessionHasNoErrors();
        $fpv->refresh();
        $this->assertEquals('ACTIVA', $fpv->estado);
        $this->assertEquals('3000000.00', (string) $fpv->detalleFpv->saldo_actual);
        $this->assertNull($fpv->detalleFpv->unidades);
    }
}
