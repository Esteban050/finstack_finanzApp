<?php

namespace Tests\Feature;

use App\Models\AceptacionPolitica;
use App\Models\ParametroNormativo;
use App\Models\PerfilTributario;
use App\Models\Usuario;
use App\Services\SeguridadSocialService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CP-SS-016: cotizar enero de 2027 busca los ingresos de diciembre de 2026
 * (mes vencido, Decreto 1273 de 2018) aunque cambie el año.
 */
class SeguridadSocialCambioDeAnioTest extends TestCase
{
    private Usuario $usuario;
    private string $clave = 'ClaveSegura#2026';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();

        // Se cotiza en enero de 2027.
        Carbon::setTestNow(Carbon::parse('2027-01-15 09:00:00', 'America/Bogota'));

        // Parámetros 2027 temporales (copia de 2026) solo para esta prueba.
        if (!ParametroNormativo::where('anio_vigencia', 2027)->exists()) {
            $p = ParametroNormativo::where('anio_vigencia', 2026)->where('es_activa', true)->firstOrFail()->replicate();
            $p->anio_vigencia = 2027;
            $p->save();
        }

        $this->usuario = Usuario::create([
            'nombres' => 'Prueba', 'apellidos' => 'Enero',
            'tipo_documento' => 'CC', 'numero_documento' => (string) random_int(10000000, 99999999),
            'correo' => 'enero.' . Str::lower(Str::random(8)) . '@scfp.test',
            'password_hash' => bcrypt($this->clave), 'rol' => 'USUARIO', 'estado' => 'ACTIVA', 'mfa_activo' => false,
        ]);
        AceptacionPolitica::create([
            'usuario_id' => $this->usuario->id, 'version_politica' => config('app.version_politica_datos', 'v1.0'),
            'fecha_aceptacion' => now(), 'ip_origen' => '127.0.0.1', 'medio' => 'WEB_REGISTRO',
        ]);
        PerfilTributario::create([
            'usuario_id' => $this->usuario->id, 'fecha_nacimiento' => '1990-01-01', 'ciudad_residencia' => 'Medellín',
            'vigente_desde' => '2026-01-01', 'ocupaciones' => ['Contratista por prestación de servicios'],
        ]);

        // Fuente de honorarios (no salario) y cuatro ingresos alrededor del borde.
        $tipo = DB::table('categorias')->where('tipo', 'INGRESO')->where('nombre', 'ilike', 'honorarios%')->value('id');
        $fuente = (string) Str::uuid();
        DB::table('fuentes_ingreso')->insert([
            'id' => $fuente, 'usuario_id' => $this->usuario->id, 'tipo_fuente_id' => $tipo,
            'nombre' => 'Honorarios', 'clasificacion_tributaria' => 'RENTAS_TRABAJO',
        ]);
        foreach (['2026-11-30' => '1000000', '2026-12-01' => '3000000', '2026-12-31' => '5000000', '2027-01-01' => '7000000'] as $fecha => $monto) {
            DB::table('ingresos')->insert([
                'id' => (string) Str::uuid(), 'usuario_id' => $this->usuario->id, 'fuente_id' => $fuente,
                'fecha_recepcion' => $fecha, 'clasificacion_tributaria' => 'RENTAS_TRABAJO',
                'monto_original' => $monto, 'monto_bruto_cop' => $monto, 'estado' => 'CONFIRMADO',
            ]);
        }

        $this->post(route('login'), ['correo' => $this->usuario->correo, 'password' => $this->clave]);
        $this->assertAuthenticated();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::connection('pgsql')->rollBack();
        parent::tearDown();
    }

    public function test_el_mes_de_ingresos_de_enero_es_diciembre_del_ano_anterior(): void
    {
        $this->assertSame('2026-12-01', SeguridadSocialService::mesDeIngresos(CarbonImmutable::parse('2027-01-01'))->toDateString());
        // Vecinos: febrero → enero del mismo año; marzo → febrero sin desbordarse.
        $this->assertSame('2027-01-01', SeguridadSocialService::mesDeIngresos(CarbonImmutable::parse('2027-02-01'))->toDateString());
        $this->assertSame('2027-02-01', SeguridadSocialService::mesDeIngresos(CarbonImmutable::parse('2027-03-01'))->toDateString());
    }

    public function test_cotizar_2027_01_suma_solo_diciembre_de_2026(): void
    {
        $respuesta = $this->getJson(route('seguridad-social.ingresos-mes', [
            'tipo_cotizante' => 'INDEPENDIENTE', 'periodo' => '2027-01',
        ]))->assertOk();

        // Cuenta 01/12 y 31/12 ($ 3.000.000 + $ 5.000.000); no el 30/11 ni el 01/01.
        $this->assertSame(2, $respuesta->json('data.cantidad'));
        $this->assertSame(0, bccomp((string) $respuesta->json('data.total'), '8000000', 2));
    }

    public function test_el_calculo_de_enero_usa_los_ingresos_de_diciembre(): void
    {
        $calculo = $this->postJson(route('seguridad-social.calcular'), [
            'tipo_cotizante' => 'INDEPENDIENTE', 'periodo' => '2027-01', 'origen_monto' => 'INGRESOS',
        ])->assertOk()->json('data');

        // IBC = 40 % de $ 8.000.000 = $ 3.200.000.
        $this->assertSame(0, bccomp((string) ($calculo['ibc'] ?? $calculo['ibc_total'] ?? '0'), '3200000', 2), json_encode($calculo));
    }
}
