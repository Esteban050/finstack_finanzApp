<?php

namespace Tests\Feature;

use App\Models\AceptacionPolitica;
use App\Models\Ingreso;
use App\Models\PlantillaRecurrencia;
use App\Models\Usuario;
use App\Services\RecurrenciaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CP-ING-048: una recurrencia de ingreso no genera ingresos con su fuente
 * inactiva. Al inactivar la fuente se pausa y se avisa; no se puede reanudar
 * hasta reactivar la fuente.
 */
class RecurrenteFuenteInactivaTest extends TestCase
{
    private Usuario $usuario;
    private string $fuenteId;
    private string $plantillaId;
    private string $clave = 'ClaveSegura#2026';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();

        Carbon::setTestNow(Carbon::parse('2026-11-22 09:00:00', 'America/Bogota'));

        $this->usuario = Usuario::create([
            'nombres' => 'Prueba', 'apellidos' => 'Recurrente',
            'tipo_documento' => 'CC', 'numero_documento' => (string) random_int(10000000, 99999999),
            'correo' => 'recurrente.' . Str::lower(Str::random(8)) . '@scfp.test',
            'password_hash' => bcrypt($this->clave), 'rol' => 'USUARIO', 'estado' => 'ACTIVA', 'mfa_activo' => false,
        ]);
        AceptacionPolitica::create([
            'usuario_id' => $this->usuario->id, 'version_politica' => config('app.version_politica_datos', 'v1.0'),
            'fecha_aceptacion' => now(), 'ip_origen' => '127.0.0.1', 'medio' => 'WEB_REGISTRO',
        ]);
        $tipo = DB::table('categorias')->where('tipo', 'INGRESO')->where('nombre', 'ilike', 'honorarios%')->value('id');
        $this->fuenteId = (string) Str::uuid();
        DB::table('fuentes_ingreso')->insert([
            'id' => $this->fuenteId, 'usuario_id' => $this->usuario->id, 'tipo_fuente_id' => $tipo,
            'nombre' => 'Honorarios', 'clasificacion_tributaria' => 'RENTAS_TRABAJO',
        ]);
        // Un ingreso manual para que la fuente solo pueda inactivarse (no borrarse).
        DB::table('ingresos')->insert([
            'id' => (string) Str::uuid(), 'usuario_id' => $this->usuario->id, 'fuente_id' => $this->fuenteId,
            'fecha_recepcion' => '2026-11-01', 'clasificacion_tributaria' => 'RENTAS_TRABAJO',
            'monto_original' => 100000, 'monto_bruto_cop' => 100000, 'estado' => 'CONFIRMADO',
        ]);

        $this->post(route('login'), ['correo' => $this->usuario->correo, 'password' => $this->clave]);
        $this->assertAuthenticated();

        $this->plantillaId = $this->postJson(route('ingresos.recurrentes.store'), [
            'periodicidad' => 'MENSUAL', 'fecha_inicio' => '2026-12-01', 'modo_confirmacion' => 'AUTOMATICO',
            'fuente_id' => $this->fuenteId, 'monto_original' => '800000.00', 'moneda' => 'COP',
        ])->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::connection('pgsql')->rollBack();
        parent::tearDown();
    }

    private function plantilla(): PlantillaRecurrencia
    {
        return PlantillaRecurrencia::withoutGlobalScopes()->findOrFail($this->plantillaId);
    }

    public function test_inactivar_la_fuente_pausa_sus_recurrentes_y_avisa(): void
    {
        $this->patchJson(route('ingresos.fuentes.toggle', $this->fuenteId))
            ->assertOk()
            ->assertJsonPath('mensaje', 'Fuente inactivada. No aparecerá al registrar ingresos nuevos. Se pausó 1 ingreso recurrente que usaba esta fuente.');

        $this->assertFalse($this->plantilla()->activa);
        $this->assertDatabaseHas('bitacora_auditoria', ['entidad_id' => $this->plantillaId, 'accion' => 'PAUSAR_INGRESO_RECURRENTE']);

        // El job del 01/12 no genera nada.
        $this->assertSame(0, RecurrenciaService::generarIngresosPendientes(CarbonImmutable::parse('2026-12-01')));
        $this->assertFalse(Ingreso::withoutGlobalScopes()->where('plantilla_recurrencia_id', $this->plantillaId)->exists());
    }

    public function test_no_se_puede_reanudar_con_la_fuente_inactiva(): void
    {
        $this->patchJson(route('ingresos.fuentes.toggle', $this->fuenteId))->assertOk();

        $this->patchJson(route('ingresos.recurrentes.toggle', $this->plantillaId))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fuente_id']);
        $this->assertFalse($this->plantilla()->activa);

        // Al reactivar la fuente, la recurrente sigue pausada hasta que el usuario la reanude.
        $this->patchJson(route('ingresos.fuentes.toggle', $this->fuenteId))->assertOk();
        $this->assertFalse($this->plantilla()->activa);
        $this->patchJson(route('ingresos.recurrentes.toggle', $this->plantillaId))->assertOk();
        $this->assertTrue($this->plantilla()->activa);
    }

    public function test_red_de_seguridad_del_job_si_la_fuente_quedo_inactiva_sin_pausar(): void
    {
        // Datos anteriores a la corrección: fuente inactiva y recurrente activa.
        DB::table('fuentes_ingreso')->where('id', $this->fuenteId)->update(['activa' => false]);
        $this->assertTrue($this->plantilla()->activa);

        $this->assertSame(0, RecurrenciaService::generarIngresosPendientes(CarbonImmutable::parse('2026-12-01')));
        $this->assertFalse(Ingreso::withoutGlobalScopes()->where('plantilla_recurrencia_id', $this->plantillaId)->exists());
        $this->assertFalse($this->plantilla()->activa);
    }

    public function test_con_la_fuente_activa_sigue_generando(): void
    {
        // Puede haber otras plantillas en la base local; lo que importa es la de esta prueba.
        $this->assertGreaterThanOrEqual(1, RecurrenciaService::generarIngresosPendientes(CarbonImmutable::parse('2026-12-01')));
        $this->assertTrue(Ingreso::withoutGlobalScopes()->where('plantilla_recurrencia_id', $this->plantillaId)
            ->whereDate('fecha_recepcion', '2026-12-01')->exists());
    }
}
