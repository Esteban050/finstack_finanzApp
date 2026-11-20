<?php

namespace Tests\Feature;

use App\Models\AceptacionPolitica;
use App\Models\ParametroNormativo;
use App\Models\PerfilTributario;
use App\Models\Usuario;
use App\Services\SeguridadSocialService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Independiente y rentista de capital: el usuario decide si calcula con los
 * ingresos registrados del mes o con un monto manual; por debajo de 1 SMMLV
 * no hay obligación, y si tras los descuentos la base queda por debajo del
 * mínimo se cotiza sobre 1 SMMLV.
 */
class SeguridadSocialOrigenMontoTest extends TestCase
{
    private Usuario $usuario;
    private string $smmlv;
    private string $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        // Igual que las demás pruebas de seguridad social: la base local de PostgreSQL,
        // dentro de una transacción que se revierte al terminar cada prueba.
        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();

        $this->usuario = Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Origen Monto',
            'correo' => 'origen.monto.' . Str::lower(Str::random(8)) . '@scfp.test',
            'password_hash' => bcrypt('Secret123!'),
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(1000000000, 1999999999),
            'rol' => 'USUARIO',
            'estado' => 'ACTIVA',
        ]);

        AceptacionPolitica::create([
            'usuario_id' => $this->usuario->id,
            'version_politica' => config('app.version_politica_datos', 'v1.0'),
            'fecha_aceptacion' => now(),
            'ip_origen' => '127.0.0.1',
            'medio' => 'WEB_REGISTRO',
        ]);

        PerfilTributario::create([
            'usuario_id' => $this->usuario->id,
            'fecha_nacimiento' => '1990-01-01',
            'ciudad_residencia' => 'Medellín',
            'vigente_desde' => '2026-01-01',
            'ocupaciones' => ['Contratista por prestación de servicios', 'Rentista de capital'],
        ]);

        // Un mes cerrado del año en curso, para usar los parámetros de esa vigencia.
        $mes = CarbonImmutable::today()->subMonthNoOverflow()->startOfMonth();
        $this->periodo = $mes->format('Y-m');
        $this->smmlv = bcadd((string) ParametroNormativo::where('anio_vigencia', $mes->year)->where('es_activa', true)->value('valor_smmlv'), '0', 2);
    }

    protected function tearDown(): void
    {
        DB::connection('pgsql')->rollBack();
        parent::tearDown();
    }

    /**
     * Por defecto el ingreso se registra en el mes anterior al periodo (mes vencido).
     */
    private function registrarIngreso(string $monto, string $clasificacion = 'RENTAS_CAPITAL', int $dia = 10, bool $mesDelPeriodo = false): void
    {
        $inicio = CarbonImmutable::parse($this->periodo . '-01');
        $inicio = $mesDelPeriodo ? $inicio : $inicio->subMonthNoOverflow();

        // Una fuente que no sea de tipo "Salario" (esas cotizan por nómina y se excluyen).
        $tipoFuente = DB::table('categorias')->where('tipo', 'INGRESO')->where('nombre', 'ilike', 'arrendamiento%')->value('id')
            ?? DB::table('categorias')->where('tipo', 'INGRESO')->where('nombre', 'not ilike', '%salario%')->value('id');

        $fuenteId = (string) Str::uuid();
        DB::table('fuentes_ingreso')->insert([
            'id' => $fuenteId,
            'usuario_id' => $this->usuario->id,
            'tipo_fuente_id' => $tipoFuente,
            'nombre' => 'Arriendo ' . Str::random(4),
            'clasificacion_tributaria' => $clasificacion,
        ]);

        DB::table('ingresos')->insert([
            'id' => (string) Str::uuid(),
            'usuario_id' => $this->usuario->id,
            'fuente_id' => $fuenteId,
            'fecha_recepcion' => $inicio->addDays($dia - 1)->toDateString(),
            'clasificacion_tributaria' => $clasificacion,
            'monto_original' => $monto,
            'monto_bruto_cop' => $monto,
            'estado' => 'CONFIRMADO',
        ]);
    }

    private function calcular(array $datos)
    {
        return $this->actingAs($this->usuario)->postJson(route('seguridad-social.calcular'), $datos + ['periodo' => $this->periodo]);
    }

    public function test_consulta_los_ingresos_de_capital_del_mes_para_el_rentista(): void
    {
        $this->registrarIngreso('4000000.00');
        $this->registrarIngreso('2000000.00', dia: 20);
        $this->registrarIngreso('9000000.00', 'RENTAS_TRABAJO');
        // Recibido en el mismo mes del periodo: se cotizará el mes siguiente, no ahora.
        $this->registrarIngreso('5000000.00', mesDelPeriodo: true);

        $respuesta = $this->actingAs($this->usuario)->getJson(route('seguridad-social.ingresos-mes', [
            'periodo' => $this->periodo,
            'tipo_cotizante' => 'RENTISTA_CAPITAL',
        ]));

        $respuesta->assertOk()
            ->assertJsonPath('data.mes_ingresos', CarbonImmutable::parse($this->periodo . '-01')->subMonthNoOverflow()->format('Y-m'))
            ->assertJsonPath('data.cantidad', 2)
            ->assertJsonPath('data.total', '6000000.00')
            ->assertJsonPath('data.obligado', true);
    }

    public function test_el_rentista_calcula_con_los_ingresos_registrados_si_lo_elige(): void
    {
        $this->registrarIngreso('4000000.00');
        $this->registrarIngreso('2000000.00', dia: 20);

        $this->calcular(['tipo_cotizante' => 'RENTISTA_CAPITAL', 'origen_monto' => 'INGRESOS', 'monto_contrato' => '6000000.00'])
            ->assertOk()
            ->assertJsonPath('data.origen_monto', 'INGRESOS')
            ->assertJsonPath('data.ingresos_brutos', '6000000.00')
            ->assertJsonPath('data.obligado', true);
    }

    public function test_el_monto_manual_no_se_reemplaza_por_los_ingresos_registrados(): void
    {
        $this->registrarIngreso('6000000.00');

        $this->calcular(['tipo_cotizante' => 'RENTISTA_CAPITAL', 'origen_monto' => 'MANUAL', 'monto_contrato' => '10000000'])
            ->assertOk()
            ->assertJsonPath('data.origen_monto', 'MANUAL')
            ->assertJsonPath('data.ingresos_brutos', '10000000.00');
    }

    public function test_ingresos_menores_al_minimo_no_obligan_a_pagar(): void
    {
        $menosDelMinimo = bcsub($this->smmlv, '1000.00', 2);
        $this->registrarIngreso($menosDelMinimo);

        foreach (['RENTISTA_CAPITAL', 'INDEPENDIENTE'] as $tipo) {
            $respuesta = $this->calcular(['tipo_cotizante' => $tipo, 'origen_monto' => 'MANUAL', 'monto_contrato' => $menosDelMinimo]);

            $respuesta->assertOk()
                ->assertJsonPath('data.obligado', false)
                ->assertJsonPath('data.total', '0.00')
                ->assertJsonPath('data.ibc', '0.00')
                ->assertJsonPath('data.avisos.0', SeguridadSocialService::AVISO_NO_OBLIGADO);
        }

        $this->calcular(['tipo_cotizante' => 'RENTISTA_CAPITAL', 'origen_monto' => 'INGRESOS'])
            ->assertOk()
            ->assertJsonPath('data.obligado', false)
            ->assertJsonPath('data.total', '0.00');
    }

    public function test_si_tras_los_descuentos_la_base_queda_bajo_el_minimo_se_cotiza_sobre_el_minimo(): void
    {
        // Ingresos de 2 SMMLV: el 40 % (o el 40 % de la base neta del rentista) queda por debajo de 1 SMMLV.
        $dosMinimos = bcmul($this->smmlv, '2', 2);
        // El IBC se lleva al peso superior: el SMMLV sin centavos se conserva igual.
        $minimo = bccomp(bcmod($this->smmlv, '1', 2), '0', 2) === 0
            ? $this->smmlv
            : bcadd(bcadd($this->smmlv, '0', 0), '1', 2);

        foreach (['RENTISTA_CAPITAL', 'INDEPENDIENTE'] as $tipo) {
            $this->calcular(['tipo_cotizante' => $tipo, 'origen_monto' => 'MANUAL', 'monto_contrato' => $dosMinimos])
                ->assertOk()
                ->assertJsonPath('data.obligado', true)
                ->assertJsonPath('data.ibc', $minimo)
                ->assertJsonPath('data.avisos.0', SeguridadSocialService::AVISO_IBC_MINIMO);
        }
    }
}
