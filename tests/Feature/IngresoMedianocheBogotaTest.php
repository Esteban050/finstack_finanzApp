<?php

namespace Tests\Feature;

use App\Models\AceptacionPolitica;
use App\Models\Ingreso;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CP-ING-027 · RNG-03: el "hoy" de la regla de fecha futura y el mes del ingreso se
 * toman en America/Bogota (UTC−5), no en UTC. El borde es la medianoche de Bogotá
 * del 31/10 al 01/11/2026, que en UTC son las 05:00 del 01/11.
 */
class IngresoMedianocheBogotaTest extends TestCase
{
    private Usuario $usuario;
    private string $fuenteId;
    private string $clave = 'ClaveSegura#2026';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();

        // Inicio de sesión a las 23:50 de Bogotá: dentro de los 15 min de inactividad (RN-006.1).
        $this->enUtc('2026-11-01 04:50:00');

        $this->usuario = Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Medianoche',
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(10000000, 99999999),
            'correo' => 'medianoche.' . Str::lower(Str::random(8)) . '@scfp.test',
            'password_hash' => bcrypt($this->clave),
            'rol' => 'USUARIO',
            'estado' => 'ACTIVA',
            'mfa_activo' => false,
        ]);
        AceptacionPolitica::create([
            'usuario_id' => $this->usuario->id,
            'version_politica' => config('app.version_politica_datos', 'v1.0'),
            'fecha_aceptacion' => now(),
            'ip_origen' => '127.0.0.1',
            'medio' => 'WEB_REGISTRO',
        ]);
        $tipo = DB::table('categorias')->where('tipo', 'INGRESO')->value('id');
        $this->fuenteId = (string) Str::uuid();
        DB::table('fuentes_ingreso')->insert([
            'id' => $this->fuenteId, 'usuario_id' => $this->usuario->id, 'tipo_fuente_id' => $tipo,
            'nombre' => 'Honorarios', 'clasificacion_tributaria' => 'RENTAS_TRABAJO',
        ]);

        $this->post(route('login'), ['correo' => $this->usuario->correo, 'password' => $this->clave]);
        $this->assertAuthenticated();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::connection('pgsql')->rollBack();
        parent::tearDown();
    }

    /** Fija el reloj del servidor en un instante UTC (la app trabaja en America/Bogota). */
    private function enUtc(string $instante): void
    {
        Carbon::setTestNow(Carbon::parse($instante, 'UTC')->setTimezone('America/Bogota'));
    }

    private function registrar(string $fecha, string $descripcion)
    {
        return $this->from(route('ingresos.create'))->post(route('ingresos.store'), [
            'fuente_id' => $this->fuenteId,
            'fecha_recepcion' => $fecha,
            'monto_original' => '1000000.00',
            'moneda' => 'COP',
            'descripcion' => $descripcion,
        ]);
    }

    public function test_2359_del_31_10_en_bogota_queda_en_octubre(): void
    {
        // 23:59:00 del 31/10 en Bogotá = 04:59:00 del 01/11 en UTC.
        $this->enUtc('2026-11-01 04:59:00');
        $this->assertSame('2026-10-31', today()->toDateString());

        $this->registrar('2026-10-31', 'CP-ING-027 23:59')->assertSessionHasNoErrors();
        $ingreso = Ingreso::where('usuario_id', $this->usuario->id)->where('descripcion', 'CP-ING-027 23:59')->firstOrFail();
        $this->assertSame('2026-10-31', $ingreso->fecha_recepcion->toDateString());

        // En UTC ya es 01/11, pero en Bogotá todavía no: esa fecha es futura.
        $this->registrar('2026-11-01', 'CP-ING-027 futura')
            ->assertSessionHasErrors('fecha_recepcion');

        // Aparece en el listado de octubre y no en el de noviembre.
        $ids = fn (string $desde, string $hasta) => collect(
            $this->getJson(route('ingresos.index', ['fecha_desde' => $desde, 'fecha_hasta' => $hasta]))
                ->assertOk()->json('data.ingresos')
        )->pluck('id')->all();
        $this->assertContains($ingreso->id, $ids('2026-10-01', '2026-10-31'));
        $this->assertNotContains($ingreso->id, $ids('2026-11-01', '2026-11-30'));
    }

    public function test_ultimo_segundo_del_31_10_sigue_siendo_octubre(): void
    {
        // 23:59:59 del 31/10 en Bogotá = 04:59:59 UTC del 01/11.
        $this->enUtc('2026-11-01 04:59:59');
        $this->assertSame('2026-10-31', today()->toDateString());
        $this->registrar('2026-11-01', 'CP-ING-027 23:59:59 futura')->assertSessionHasErrors('fecha_recepcion');
        $this->registrar('2026-10-31', 'CP-ING-027 23:59:59')->assertSessionHasNoErrors();
    }

    public function test_medianoche_exacta_de_bogota_ya_es_noviembre(): void
    {
        // 00:00:00 del 01/11 en Bogotá = 05:00:00 UTC.
        $this->enUtc('2026-11-01 05:00:00');
        $this->assertSame('2026-11-01', today()->toDateString());
        $this->registrar('2026-11-01', 'CP-ING-027 00:00')->assertSessionHasNoErrors();
        $this->registrar('2026-11-02', 'CP-ING-027 00:00 futura')->assertSessionHasErrors('fecha_recepcion');
    }
}
