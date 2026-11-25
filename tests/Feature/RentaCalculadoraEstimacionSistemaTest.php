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
            'numero_documento' => '10'.random_int(10000000, 99999999).'43', 'rol' => 'USUARIO', 'estado' => 'ACTIVA',
        ]);
        $this->actingAs($usuario);
        $this->withoutMiddleware(\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class);
        $this->withoutMiddleware(\App\Http\Middleware\PerfilCompletoMiddleware::class);

        $tipo = DB::table('categorias')->where('tipo', 'INGRESO')->value('id');
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
}
