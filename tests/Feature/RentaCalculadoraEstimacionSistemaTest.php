<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Renta\EstimacionRentaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * N03: la "Estimación del sistema" de Renta › Calculadora es la misma de Renta › Estimación
 * (con las retenciones del usuario), no la liquidación de los cinco campos simulados.
 */
class RentaCalculadoraEstimacionSistemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_estimacion_del_sistema_coincide_con_la_de_estimacion(): void
    {
        $usuario = Usuario::create([
            'nombres' => 'Prueba', 'apellidos' => 'Calculadora', 'correo' => 'calc.'.Str::random(8).'@example.test',
            'password_hash' => bcrypt('Secret123!'), 'tipo_documento' => 'CC',
            'numero_documento' => '9'.str_pad((string) (hrtime(true) % 1000000000), 9, '0', STR_PAD_LEFT), 'rol' => 'USUARIO', 'estado' => 'ACTIVA',
        ]);
        $this->actingAs($usuario);
        $this->withoutMiddleware(\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class);
        $this->withoutMiddleware(\App\Http\Middleware\PerfilCompletoMiddleware::class);

        $tipo = (string) Str::uuid();
        DB::table('categorias')->insert(['id' => $tipo, 'usuario_id' => null, 'tipo' => 'INGRESO', 'nombre' => 'Sueldo '.Str::random(6), 'es_predeterminada' => true]);
        $fuente = (string) Str::uuid();
        DB::table('fuentes_ingreso')->insert([
            'id' => $fuente, 'usuario_id' => $usuario->id, 'tipo_fuente_id' => $tipo,
            'nombre' => 'Fuente', 'clasificacion_tributaria' => 'RENTAS_TRABAJO',
        ]);
        DB::table('ingresos')->insert([
            'id' => (string) Str::uuid(), 'usuario_id' => $usuario->id, 'fuente_id' => $fuente,
            'fecha_recepcion' => '2025-06-15', 'clasificacion_tributaria' => 'RENTAS_TRABAJO',
            'monto_original' => '10000000', 'monto_bruto_cop' => '10000000',
            'retencion_fuente_cop' => '1211797', 'aportes_descontados_cop' => '0', 'descripcion' => 'Ingreso',
        ]);

        $estimacion = app(EstimacionRentaService::class)->estimar($usuario, 2025);
        $this->assertSame(1212000.0, (float) $estimacion['paso8']['saldo_valor_mostrado_cop']);

        $respuesta = $this->postJson(route('renta.calculadora.calcular', ['anio' => 2025]), ['ingresos_brutos_cop' => 0])
            ->assertOk()->json('estimacion_sistema');

        $this->assertEquals(1212000.0, (float) $respuesta['saldo_valor']);
        $this->assertEquals((float) $estimacion['paso7']['impuesto_redondeado_cop'], (float) $respuesta['impuesto_cop']);
        $this->assertSame($estimacion['paso8']['saldo_label'], $respuesta['saldo_label']);
    }

    /** T7c: si la estimación falla, la simulación sigue respondiendo y la tarjeta muestra "No disponible". */
    public function test_si_la_estimacion_falla_la_simulacion_sigue_funcionando(): void
    {
        $usuario = Usuario::create([
            'nombres' => 'Prueba', 'apellidos' => 'Calculadora', 'correo' => 'calc.'.Str::random(8).'@example.test',
            'password_hash' => bcrypt('Secret123!'), 'tipo_documento' => 'CC',
            'numero_documento' => '8'.str_pad((string) (hrtime(true) % 1000000000), 9, '0', STR_PAD_LEFT), 'rol' => 'USUARIO', 'estado' => 'ACTIVA',
        ]);
        $this->actingAs($usuario);
        $this->withoutMiddleware(\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class);
        $this->withoutMiddleware(\App\Http\Middleware\PerfilCompletoMiddleware::class);
        $this->mock(EstimacionRentaService::class)->shouldReceive('estimar')->andThrow(new \RuntimeException('falla'));
        \Illuminate\Support\Facades\Log::spy();

        $respuesta = $this->postJson(route('renta.calculadora.calcular', ['anio' => 2025]), ['ingresos_brutos_cop' => 1000000])
            ->assertOk();

        $respuesta->assertJsonPath('estimacion_sistema', null);
        $this->assertArrayHasKey('liquidacion', $respuesta->json());
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once();
        $this->get(route('renta.calculadora', ['anio' => 2025]))->assertOk()->assertSee('No disponible');
    }
}
