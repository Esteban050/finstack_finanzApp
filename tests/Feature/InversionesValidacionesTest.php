<?php

namespace Tests\Feature;

use App\Models\AceptacionPolitica;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CP-INV-004, 005 y 009: unidades > 0 con hasta 8 decimales (RN-037.3) y fecha
 * de valoración no futura (RN-039.1).
 */
class InversionesValidacionesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();
        Carbon::setTestNow(Carbon::parse('2026-11-22 09:00:00', 'America/Bogota'));

        $u = Usuario::create([
            'nombres' => 'Prueba', 'apellidos' => 'Inversiones', 'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(10000000, 99999999),
            'correo' => 'inv.' . Str::lower(Str::random(8)) . '@scfp.test',
            'password_hash' => bcrypt('ClaveSegura#2026'), 'rol' => 'USUARIO', 'estado' => 'ACTIVA', 'mfa_activo' => false,
        ]);
        AceptacionPolitica::create(['usuario_id' => $u->id, 'version_politica' => config('app.version_politica_datos', 'v1.0'),
            'fecha_aceptacion' => now(), 'ip_origen' => '127.0.0.1', 'medio' => 'WEB_REGISTRO']);
        $this->post(route('login'), ['correo' => $u->correo, 'password' => 'ClaveSegura#2026']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::connection('pgsql')->rollBack();
        parent::tearDown();
    }

    private function accion(array $extra)
    {
        return $this->postJson(route('inversiones.store'), array_merge([
            'nombre' => 'Acción prueba', 'tipo' => 'ACCION_ETF', 'moneda' => 'COP', 'fecha_apertura' => '2026-01-15',
            'simbolo' => 'ECO', 'valor_unidad' => '100000000', 'valor_dividendo_por_unidad' => '0',
        ], $extra));
    }

    public function test_unidades_hasta_8_decimales(): void
    {
        $this->accion(['unidades' => '0.12345678', 'monto_inicial' => '12345678'])->assertCreated();
        $this->accion(['unidades' => '0.123456789', 'monto_inicial' => '12345678.90'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unidades' => 'Las unidades admiten hasta 8 decimales.']);
    }

    public function test_unidades_en_cero_se_rechazan_y_vacias_se_autocalculan(): void
    {
        $this->accion(['unidades' => '0', 'monto_inicial' => '1000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unidades' => 'Las unidades deben ser mayores que cero.']);

        // Vecino: sin unidades, se calculan con monto ÷ valor de la unidad (RF-INV-06).
        $id = $this->accion(['monto_inicial' => '1000000'])->assertCreated()->json('data.id');
        $this->assertSame('0.01000000', (string) DB::table('detalle_renta_variable')->where('inversion_id', $id)->value('unidades_total'));
    }

    public function test_valoracion_no_puede_tener_fecha_futura(): void
    {
        $id = $this->postJson(route('inversiones.store'), ['nombre' => 'FIC prueba', 'tipo' => 'FIC', 'moneda' => 'COP',
            'fecha_apertura' => '2026-03-01', 'monto_inicial' => '2000000'])->assertCreated()->json('data.id');

        $this->postJson(route('inversiones.valoraciones.store', $id), ['fecha' => '2026-11-23', 'valor' => '2100000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha' => 'La fecha de valoración no puede ser posterior a hoy (RN-039.1).']);
        $this->postJson(route('inversiones.valoraciones.store', $id), ['fecha' => '2026-11-22', 'valor' => '2100000'])->assertCreated();
    }
}
