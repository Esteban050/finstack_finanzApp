<?php

namespace Tests\Unit;

use App\Http\Controllers\PagoSeguridadSocialController;
use App\Models\EsquemaCostoPresunto;
use App\Models\FuenteIngreso;
use App\Models\Ingreso;
use App\Models\ParametroNormativo;
use App\Models\PerfilTributario;
use App\Models\Usuario;
use App\Services\SeguridadSocialService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeguridadSocialRentistaTest extends TestCase
{
    private ParametroNormativo $parametro;
    private PerfilTributario $perfil;
    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        Schema::create('usuarios', function ($table) {
            $table->uuid('id')->primary();
            $table->string('nombres')->default('Rentista');
            $table->string('apellidos')->default('Capital');
            $table->string('correo')->default('rentista@example.com');
            $table->string('password_hash')->default('secret');
            $table->string('tipo_documento', 10)->default('CC');
            $table->string('numero_documento', 20)->default('12345678');
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
            $table->timestamp('eliminado_en')->nullable();
        });

        Schema::create('perfiles_tributarios', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->json('ocupaciones');
            $table->integer('clase_riesgo_arl')->nullable();
            $table->boolean('es_salario_integral')->default(false);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
            $table->timestamp('eliminado_en')->nullable();
        });

        Schema::create('fuentes_ingreso', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->uuid('tipo_fuente_id')->nullable();
            $table->string('nombre');
            $table->string('clasificacion_tributaria', 50);
            $table->string('pagador_entidad')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
            $table->timestamp('eliminado_en')->nullable();
        });

        Schema::create('tipos_fuente', function ($table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->uuid('categoria_padre_id')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('categorias', function ($table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->uuid('categoria_padre_id')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('ingresos', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->uuid('fuente_id')->nullable();
            $table->uuid('plantilla_recurrencia_id')->nullable();
            $table->date('fecha_recepcion');
            $table->string('clasificacion_tributaria', 50);
            $table->string('moneda', 3)->default('COP');
            $table->decimal('monto_original', 18, 2);
            $table->decimal('tasa_cambio_usada', 10, 4)->default(1);
            $table->decimal('monto_bruto_cop', 18, 2);
            $table->decimal('retencion_fuente_cop', 18, 2)->default(0);
            $table->decimal('aportes_descontados_cop', 18, 2)->default(0);
            $table->string('origen', 20)->default('MANUAL');
            $table->string('estado', 20)->default('CONFIRMADO');
            $table->text('descripcion')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
            $table->timestamp('eliminado_en')->nullable();
        });

        Schema::create('liquidaciones_seguridad_social', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->char('periodo', 7);
            $table->string('origen_ingreso', 15)->default('REGISTRADO');
            $table->decimal('ingreso_base', 18, 2);
            $table->decimal('ibc', 18, 2);
            $table->decimal('salud', 18, 2);
            $table->decimal('pension', 18, 2);
            $table->decimal('fsp', 18, 2)->default(0);
            $table->decimal('arl', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->uuid('parametro_normativo_id')->nullable();
            $table->json('parametros_usados')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });

        Schema::create('parametros_normativos', function ($table) {
            $table->uuid('id')->primary();
            $table->integer('anio_vigencia');
            $table->integer('version')->default(1);
            $table->string('acto_administrativo')->default('Decreto 2026');
            $table->decimal('valor_smmlv', 18, 2);
            $table->decimal('porc_ibc_indep', 5, 2);
            $table->decimal('ibc_min_smmlv', 5, 2);
            $table->decimal('ibc_max_smmlv', 5, 2);
            $table->decimal('tarifa_salud_indep', 5, 2);
            $table->decimal('tarifa_pension_indep', 5, 2);
            $table->boolean('es_activa')->default(true);
            $table->timestamps();
        });

        Schema::create('tarifas_escalonadas', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('parametro_normativo_id');
            $table->string('tipo_tabla', 20);
            $table->decimal('rango_desde', 12, 2);
            $table->decimal('rango_hasta', 12, 2)->nullable();
            $table->decimal('tarifa_porc', 6, 3);
            $table->decimal('impuesto_base_uvt', 12, 2)->default(0);
        });

        Schema::create('esquemas_costos_presuntos', function ($table) {
            $table->uuid('id')->primary();
            $table->string('codigo_actividad', 50);
            $table->string('nombre_actividad', 150);
            $table->decimal('porcentaje_costo_presunto', 5, 2);
            $table->string('normativa_referencia', 100)->nullable();
            $table->boolean('admite_costos_reales')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });

        EsquemaCostoPresunto::create([
            'id' => (string) Str::uuid(),
            'codigo_actividad' => EsquemaCostoPresunto::ACTIVIDAD_CAPITAL,
            'nombre_actividad' => 'Rentas de capital',
            'porcentaje_costo_presunto' => 28.08,
            'normativa_referencia' => 'UGPP Res. 532 de 2024 / Dec. 0379 de 2026',
            'admite_costos_reales' => true,
            'activo' => true,
        ]);

        $this->usuario = new Usuario([
            'id' => (string) Str::uuid(),
            'nombres' => 'Rentista',
            'apellidos' => 'Capital',
            'correo' => 'rentista@example.com',
            'password_hash' => 'hash',
            'tipo_documento' => 'CC',
            'numero_documento' => '987654321',
        ]);
        $this->usuario->save();

        $this->parametro = new ParametroNormativo([
            'id' => (string) Str::uuid(),
            'anio_vigencia' => 2026,
            'version' => 1,
            'acto_administrativo' => 'Decreto Demo 2026',
            'valor_smmlv' => '1423500.00',
            'porc_ibc_indep' => '40.00',
            'ibc_min_smmlv' => '1.00',
            'ibc_max_smmlv' => '25.00',
            'tarifa_salud_indep' => '12.50',
            'tarifa_pension_indep' => '16.00',
        ]);
        $this->parametro->save();

        $this->perfil = new PerfilTributario([
            'id' => (string) Str::uuid(),
            'usuario_id' => $this->usuario->id,
            'vigente_desde' => '2026-01-01',
            'ocupaciones' => ['Rentista de capital'],
            'clase_riesgo_arl' => null,
        ]);
        $this->perfil->save();
    }

    public function test_calcular_rentista_capital_con_ejemplo_de_usuario(): void
    {
        // Ejemplo del usuario: 20M de ingresos brutos y 10M de gastos registrados
        $ingresosBrutos = '20000000.00';
        $gastos = '10000000.00';

        $resultado = SeguridadSocialService::calcularRentistaCapital(
            $ingresosBrutos,
            $gastos,
            $this->parametro,
            $this->perfil
        );

        $this->assertSame('RENTISTA_CAPITAL', $resultado['tipo_cotizante']);
        $this->assertSame('20000000.00', $resultado['ingresos_brutos']);
        $this->assertSame('10000000.00', $resultado['gastos_asociados']);
        $this->assertSame('10000000.00', $resultado['ingreso_neto']);
        $this->assertSame('10000000.00', $resultado['ingreso_base']);

        // IBC = 40% de 10M = 4,000,000.00
        $this->assertSame('4000000.00', $resultado['ibc']);

        // Salud = 12.5% de 4M = 500,000.00
        $this->assertSame('500000.00', $resultado['salud']);

        // Pensión = 16.0% de 4M = 640,000.00
        $this->assertSame('640000.00', $resultado['pension']);

        // Total = 500,000 + 640,000 = 1,140,000.00
        $this->assertSame('1140000.00', $resultado['total']);
    }

    public function test_calcular_rentista_capital_sin_gastos_equivale_a_independiente(): void
    {
        $ingreso = '10000000.00';

        $rentista = SeguridadSocialService::calcularRentistaCapital(
            $ingreso,
            '0',
            $this->parametro,
            $this->perfil
        );

        $independiente = SeguridadSocialService::calcularIndependiente(
            $ingreso,
            $this->parametro,
            $this->perfil
        );

        $this->assertSame($independiente['ibc'], $rentista['ibc']);
        $this->assertSame($independiente['salud'], $rentista['salud']);
        $this->assertSame($independiente['pension'], $rentista['pension']);
        $this->assertSame($independiente['total'], $rentista['total']);
    }

    public function test_calcular_rentista_capital_con_gastos_superiores_a_ingresos(): void
    {
        // Gastos superiores a ingresos: base neta = 0, pero IBC acotado a mínimo 1 SMMLV
        $resultado = SeguridadSocialService::calcularRentistaCapital(
            '5000000.00',
            '8000000.00',
            $this->parametro,
            $this->perfil
        );

        $this->assertSame('0.00', $resultado['ingreso_neto']);
        // IBC acotado a 1 SMMLV
        $this->assertSame('1423500.00', $resultado['ibc']);
        $this->assertNotEmpty($resultado['avisos']);
    }

    public function test_limpiar_monto_en_pago_seguridad_social_controller(): void
    {
        $this->assertSame('10000000.00', PagoSeguridadSocialController::limpiarMonto('10000000'));
        $this->assertSame('10000000.00', PagoSeguridadSocialController::limpiarMonto('10.000.000'));
        $this->assertSame('10000000.00', PagoSeguridadSocialController::limpiarMonto('$ 10.000.000'));
        $this->assertSame('10000000.50', PagoSeguridadSocialController::limpiarMonto('10.000.000,50'));
        $this->assertSame('0.00', PagoSeguridadSocialController::limpiarMonto(''));
        $this->assertSame('0.00', PagoSeguridadSocialController::limpiarMonto(null));
    }

    public function test_consultar_ingresos_periodo_cotizado_para_rentista_con_gastos(): void
    {
        $fuente = new FuenteIngreso([
            'id' => (string) Str::uuid(),
            'usuario_id' => $this->usuario->id,
            'nombre' => 'Arrendamiento Inmueble Calle 100',
            'clasificacion_tributaria' => 'RENTAS_CAPITAL',
            'activa' => true,
        ]);
        $fuente->save();

        $ingreso = new Ingreso([
            'id' => (string) Str::uuid(),
            'usuario_id' => $this->usuario->id,
            'fuente_id' => $fuente->id,
            'fecha_recepcion' => '2026-05-15',
            'clasificacion_tributaria' => 'RENTAS_CAPITAL',
            'moneda' => 'COP',
            'monto_original' => '20000000.00',
            'monto_bruto_cop' => '20000000.00',
            'estado' => 'CONFIRMADO',
        ]);
        $ingreso->save();

        $periodo = '2026-05';
        $gastos = '10000000.00';

        $resultado = SeguridadSocialService::consultarIngresosPeriodoCotizado(
            $this->usuario->id,
            $periodo,
            'RENTISTA_CAPITAL',
            $gastos
        );

        $this->assertTrue($resultado['tiene_ingresos']);
        $this->assertSame('RENTISTA_CAPITAL', $resultado['tipo_cotizante']);
        $this->assertSame('20000000.00', $resultado['total_ingresos_brutos']);
        $this->assertSame('10000000.00', $resultado['gastos_asociados']);
        $this->assertSame('10000000.00', $resultado['ingreso_neto']);
        $this->assertSame('4000000.00', $resultado['calculo']['ibc']);
        $this->assertSame('500000.00', $resultado['calculo']['salud']);
        $this->assertSame('640000.00', $resultado['calculo']['pension']);
        $this->assertSame('1140000.00', $resultado['calculo']['total']);
    }

    public function test_calcular_rentista_capital_con_presuncion_costos_28_08(): void
    {
        $ingreso = '10000000.00';

        $resultado = SeguridadSocialService::calcularRentistaCapital(
            $ingreso,
            '0',
            $this->parametro,
            $this->perfil,
            SeguridadSocialService::PORCENTAJE_PRESUNCION_COSTOS_RENTISTA
        );

        $this->assertSame('RENTISTA_CAPITAL', $resultado['tipo_cotizante']);
        $this->assertSame('10000000.00', $resultado['monto_contrato']);
        $this->assertSame('28.08', $resultado['porcentaje_presuncion_costos']);
        $this->assertSame('2808000.00', $resultado['presuncion_costos']);
        $this->assertSame('7192000.00', $resultado['ingreso_neto']);
        $this->assertSame('2876800.00', $resultado['ibc']);
        $this->assertSame('12.50', $resultado['porcentaje_salud']);
        $this->assertSame('359600.00', $resultado['salud']);
        $this->assertSame('16.00', $resultado['porcentaje_pension']);
        $this->assertSame('460300.00', $resultado['pension']);
        $this->assertSame('0.00', $resultado['fsp']);
        $this->assertSame('819900.00', $resultado['total']);
    }

    public function test_calcular_rentista_capital_ingreso_neto_menor_al_minimo_avisa_bajo_smmlv(): void
    {
        // Ingreso bruto de 1.500.000 con presunción de costos del 28.08%
        // Deducción = 421.200 -> Ingreso neto = 1.078.800 (menor a 1 SMMLV = 1.423.500)
        $resultado = SeguridadSocialService::calcularRentistaCapital(
            '1500000.00',
            '0',
            $this->parametro,
            $this->perfil,
            SeguridadSocialService::PORCENTAJE_PRESUNCION_COSTOS_RENTISTA
        );

        $this->assertSame('1078800.00', $resultado['ingreso_neto']);
        $this->assertContains(SeguridadSocialService::AVISO_BAJO_SMMLV, $resultado['avisos']);
    }

    public function test_calcular_rentista_capital_ingreso_neto_mayor_al_minimo_pero_ibc_menor_al_minimo_avisa_ibc_minimo(): void
    {
        // Ingreso bruto de 2.000.000 con presunción del 28.08%
        // Deducción = 561.600 -> Ingreso neto = 1.438.400 (mayor a 1 SMMLV = 1.423.500)
        // IBC preliminar = 40% de 1.438.400 = 575.360 (menor a 1 SMMLV)
        // Por ende el IBC se acota al SMMLV: 1.423.500
        $resultado = SeguridadSocialService::calcularRentistaCapital(
            '2000000.00',
            '0',
            $this->parametro,
            $this->perfil,
            SeguridadSocialService::PORCENTAJE_PRESUNCION_COSTOS_RENTISTA
        );

        $this->assertSame('1438400.00', $resultado['ingreso_neto']);
        $this->assertSame('1423500.00', $resultado['ibc']);
        $this->assertContains(SeguridadSocialService::AVISO_IBC_MINIMO, $resultado['avisos']);
    }

    public function test_calcular_independiente_redondea_aportes_al_multiplo_de_100_superior_segun_decreto_1990(): void
    {
        // 1 SMMLV en 2026 = 1.750.905,00
        $parametro2026 = new ParametroNormativo([
            'id' => (string) Str::uuid(),
            'anio_vigencia' => 2026,
            'version' => 2,
            'acto_administrativo' => 'Decreto 2026',
            'valor_smmlv' => '1750905.00',
            'porc_ibc_indep' => '40.00',
            'ibc_min_smmlv' => '1.00',
            'ibc_max_smmlv' => '25.00',
            'tarifa_salud_indep' => '12.50',
            'tarifa_pension_indep' => '16.00',
        ]);

        $resultado = SeguridadSocialService::calcularIndependiente(
            '1750905.00',
            $parametro2026,
            $this->perfil
        );

        $this->assertSame('1750905.00', $resultado['ibc']);
        // 1.750.905 * 12.5% = 218.863,125 -> redondeado a múltiplo de $ 100 superior = 218.900,00
        $this->assertSame('218900.00', $resultado['salud']);
        // 1.750.905 * 16.0% = 280.144,80 -> redondeado a múltiplo de $ 100 superior = 280.200,00
        $this->assertSame('280200.00', $resultado['pension']);
        $this->assertSame('499100.00', $resultado['total']);
    }

    public function test_porcentaje_presuncion_rentista_dinamico_por_periodo(): void
    {
        // Periodos <= 2026-04 retornan 27.50%
        $this->assertSame('27.50', SeguridadSocialService::porcentajePresuncionRentista('2026-04'));
        $this->assertSame('27.50', SeguridadSocialService::porcentajePresuncionRentista('2026-01'));
        $this->assertSame('27.50', SeguridadSocialService::porcentajePresuncionRentista('2025-12'));
        $this->assertSame('27.50', EsquemaCostoPresunto::porcentajePara(EsquemaCostoPresunto::ACTIVIDAD_CAPITAL, '2026-04'));

        // Periodos >= 2026-05 retornan 28.08%
        $this->assertSame('28.08', SeguridadSocialService::porcentajePresuncionRentista('2026-05'));
        $this->assertSame('28.08', SeguridadSocialService::porcentajePresuncionRentista('2026-06'));
        $this->assertSame('28.08', SeguridadSocialService::porcentajePresuncionRentista('2027-01'));
        $this->assertSame('28.08', EsquemaCostoPresunto::porcentajePara(EsquemaCostoPresunto::ACTIVIDAD_CAPITAL, '2026-05'));

        // Sin periodo especificado (null) retorna el valor base 28.08%
        $this->assertSame('28.08', SeguridadSocialService::porcentajePresuncionRentista(null));
        $this->assertSame('28.08', EsquemaCostoPresunto::porcentajePara(EsquemaCostoPresunto::ACTIVIDAD_CAPITAL, null));
    }

    public function test_calcular_rentista_capital_abril_2026_aplica_27_50(): void
    {
        $ingreso = '10000000.00';
        $porcentaje = SeguridadSocialService::porcentajePresuncionRentista('2026-04');

        $this->assertSame('27.50', $porcentaje);

        $resultado = SeguridadSocialService::calcularRentistaCapital(
            $ingreso,
            '0',
            $this->parametro,
            $this->perfil,
            $porcentaje
        );

        $this->assertSame('RENTISTA_CAPITAL', $resultado['tipo_cotizante']);
        $this->assertSame('10000000.00', $resultado['monto_contrato']);
        $this->assertSame('27.50', $resultado['porcentaje_presuncion_costos']);
        // 10.000.000 * 27.5% = 2.750.000
        $this->assertSame('2750000.00', $resultado['presuncion_costos']);
        // Ingreso neto: 10.000.000 - 2.750.000 = 7.250.000
        $this->assertSame('7250000.00', $resultado['ingreso_neto']);
        // IBC: 40% de 7.250.000 = 2.900.000
        $this->assertSame('2900000.00', $resultado['ibc']);
    }

    public function test_calcular_rentista_capital_mayo_2026_aplica_28_08(): void
    {
        $ingreso = '10000000.00';
        $porcentaje = SeguridadSocialService::porcentajePresuncionRentista('2026-05');

        $this->assertSame('28.08', $porcentaje);

        $resultado = SeguridadSocialService::calcularRentistaCapital(
            $ingreso,
            '0',
            $this->parametro,
            $this->perfil,
            $porcentaje
        );

        $this->assertSame('RENTISTA_CAPITAL', $resultado['tipo_cotizante']);
        $this->assertSame('10000000.00', $resultado['monto_contrato']);
        $this->assertSame('28.08', $resultado['porcentaje_presuncion_costos']);
        // 10.000.000 * 28.08% = 2.808.000
        $this->assertSame('2808000.00', $resultado['presuncion_costos']);
        // Ingreso neto: 10.000.000 - 2.808.000 = 7.192.000
        $this->assertSame('7192000.00', $resultado['ingreso_neto']);
        // IBC: 40% de 7.192.000 = 2.876.800
        $this->assertSame('2876800.00', $resultado['ibc']);
    }

    public function test_ingresos_para_seguridad_social_rentista_periodos_abril_vs_mayo_2026(): void
    {
        $fuente = new FuenteIngreso([
            'id' => (string) Str::uuid(),
            'usuario_id' => $this->usuario->id,
            'nombre' => 'Rendimientos Financieros',
            'clasificacion_tributaria' => 'RENTAS_CAPITAL',
            'activa' => true,
        ]);
        $fuente->save();

        // Ingreso en marzo de 2026 (respalda cotización de abril 2026 por mes vencido)
        $ingresoMarzo = new Ingreso([
            'id' => (string) Str::uuid(),
            'usuario_id' => $this->usuario->id,
            'fuente_id' => $fuente->id,
            'fecha_recepcion' => '2026-03-15',
            'clasificacion_tributaria' => 'RENTAS_CAPITAL',
            'moneda' => 'COP',
            'monto_original' => '5000000.00',
            'monto_bruto_cop' => '5000000.00',
            'estado' => 'CONFIRMADO',
        ]);
        $ingresoMarzo->save();

        $inicioAbril = CarbonImmutable::create(2026, 4, 1);
        $resAbril = SeguridadSocialService::ingresosParaSeguridadSocial(
            $this->usuario->id,
            $inicioAbril,
            'RENTISTA_CAPITAL',
            (string) $this->parametro->valor_smmlv
        );

        $this->assertSame('27.50', $resAbril['porcentaje_presuncion']);
        // 5.000.000 * 27.50% = 1.375.000
        $this->assertSame('1375000.00', $resAbril['deduccion_presuncion']);

        // Ingreso en abril de 2026 (respalda cotización de mayo 2026)
        $ingresoAbril = new Ingreso([
            'id' => (string) Str::uuid(),
            'usuario_id' => $this->usuario->id,
            'fuente_id' => $fuente->id,
            'fecha_recepcion' => '2026-04-15',
            'clasificacion_tributaria' => 'RENTAS_CAPITAL',
            'moneda' => 'COP',
            'monto_original' => '5000000.00',
            'monto_bruto_cop' => '5000000.00',
            'estado' => 'CONFIRMADO',
        ]);
        $ingresoAbril->save();

        $inicioMayo = CarbonImmutable::create(2026, 5, 1);
        $resMayo = SeguridadSocialService::ingresosParaSeguridadSocial(
            $this->usuario->id,
            $inicioMayo,
            'RENTISTA_CAPITAL',
            (string) $this->parametro->valor_smmlv
        );

        $this->assertSame('28.08', $resMayo['porcentaje_presuncion']);
        // 5.000.000 * 28.08% = 1.404.000
        $this->assertSame('1404000.00', $resMayo['deduccion_presuncion']);
    }
}
