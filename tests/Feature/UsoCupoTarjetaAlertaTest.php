<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Credito;
use App\Models\Notificacion;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\Usuario;
use App\Services\AlertaService;
use App\Services\NotificacionService;
use App\Services\ReglaAlertaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H06 (decisión: tipo de alerta nuevo): "Uso del cupo de la tarjeta" avisa cuando el saldo de capital
 * de una tarjeta llega al umbral (80 % por defecto) de su cupo, una vez por tarjeta y umbral.
 */
class UsoCupoTarjetaAlertaTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $this->alertasDentroDeLaTransaccion();
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function gastoTarjeta(string $monto, array $extra = [])
    {
        $padre = $this->categoria();
        $sub = $this->categoria($padre);

        return $this->postJson('/gastos', array_merge([
            'categoria_id' => $padre->id,
            'subcategoria_id' => $sub->id,
            'fecha' => '2026-11-20',
            'moneda' => 'COP',
            'monto_original' => $monto,
            'medio_pago' => 'TARJETA_CREDITO',
        ], $extra))->assertCreated();
    }

    private function categoria(?Categoria $padre = null): Categoria
    {
        $categoria = new Categoria;
        $categoria->forceFill([
            'usuario_id' => $this->usuario->id,
            'categoria_padre_id' => $padre?->id,
            'tipo' => 'GASTO',
            'nombre' => 'Prueba '.Str::random(8),
            'naturaleza_sugerida' => $padre === null ? null : 'VARIABLE',
            'necesidad_sugerida' => $padre === null ? null : 'DISCRECIONAL',
            'es_predeterminada' => false,
            'activa' => true,
            'creado_en' => now(),
        ])->save();

        return $categoria;
    }

    private function tarjeta(string $primeraCompra = '1000000'): string
    {
        return $this->gastoTarjeta($primeraCompra, [
            'credito_id' => 'NUEVA', 'banco_tarjeta' => 'Bancolombia', 'numero_cuotas' => 6,
            'fecha_corte' => '2026-11-25', 'fecha_pago' => '2026-12-05',
            'tasa_interes_anual' => '28', 'cupo_tarjeta' => '3000000',
        ])->json('data.credito_id');
    }

    private function notificaciones(): array
    {
        return Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $this->usuario->id)
            ->where('tipo', ReglaAlertaService::USO_CUPO_TARJETA)
            ->get()->all();
    }

    public function test_el_tipo_existe_con_umbral_80_por_defecto(): void
    {
        $this->assertContains('USO_CUPO_TARJETA', ReglaAlertaService::tipos());
        $regla = ReglaAlertaService::vigentes($this->usuario->id)['USO_CUPO_TARJETA'];
        $this->assertTrue($regla['activa']);
        $this->assertSame(['umbral' => 80], $regla['parametros']);

        $this->putJson('/perfil/alertas', ['reglas' => [['tipo' => 'USO_CUPO_TARJETA', 'parametros' => ['umbral' => 0]]]])
            ->assertStatus(422);
        $this->putJson('/perfil/alertas', ['reglas' => [['tipo' => 'USO_CUPO_TARJETA', 'parametros' => ['umbral' => 90]]]])
            ->assertOk();
    }

    public function test_avisa_al_llegar_al_umbral(): void
    {
        $id = $this->tarjeta('1000000');
        $this->assertSame([], $this->notificaciones(), '33 % del cupo: sin aviso');

        $this->gastoTarjeta('1500000', ['credito_id' => $id]);
        $this->assertCount(1, $this->notificaciones(), '2.500.000 / 3.000.000 = 83,33 %');
    }

    public function test_umbral_cruzado_genera_una_sola_notificacion_con_las_cifras(): void
    {
        $id = $this->tarjeta('1000000');
        $this->gastoTarjeta('1500000', ['credito_id' => $id]);       // 2.500.000 = 83,33 %
        $this->gastoTarjeta('100000', ['credito_id' => $id]);        // 2.600.000, mismo cruce

        $notificaciones = $this->notificaciones();
        $this->assertCount(1, $notificaciones);
        $n = $notificaciones[0];
        $this->assertSame('ADVERTENCIA', $n->prioridad);
        $this->assertSame($id, $n->referencia_id);
        $this->assertSame(80, $n->datos['umbral']);
        $this->assertSame('3000000', $n->datos['cupo']);
        $this->assertSame('Bancolombia', $n->datos['banco']);
    }

    public function test_respeta_el_umbral_y_la_activacion_del_usuario(): void
    {
        $this->putJson('/perfil/alertas', ['reglas' => [['tipo' => 'USO_CUPO_TARJETA', 'parametros' => ['umbral' => 90]]]])->assertOk();
        $id = $this->tarjeta('1000000');
        $this->gastoTarjeta('1500000', ['credito_id' => $id]);       // 83 % < 90 %
        $this->assertSame([], $this->notificaciones());

        $this->gastoTarjeta('300000', ['credito_id' => $id]);        // 93,3 %
        $this->assertCount(1, $this->notificaciones());
        $this->assertSame(90, $this->notificaciones()[0]->datos['umbral']);

        // Desactivada: no genera nada para otra tarjeta.
        $this->putJson('/perfil/alertas', ['reglas' => [['tipo' => 'USO_CUPO_TARJETA', 'activa' => false]]])->assertOk();
        Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)->where('usuario_id', $this->usuario->id)->delete();
        $this->gastoTarjeta('100000', ['credito_id' => $id]);
        $this->assertSame([], $this->notificaciones());
        $this->assertSame(1, Credito::where('usuario_id', $this->usuario->id)->count());
    }

    public function test_el_texto_del_correo_incluye_banco_porcentaje_cupo_y_disponible(): void
    {
        $id = $this->tarjeta('1000000');
        $this->gastoTarjeta('1500000', ['credito_id' => $id]);
        $notificacion = $this->notificaciones()[0];

        $metodo = new \ReflectionMethod(NotificacionService::class, 'textoCorreo');
        [$asunto, $cuerpo] = $metodo->invoke(null, $this->usuario, $notificacion);

        $this->assertStringContainsString('Bancolombia', $asunto);
        $this->assertStringContainsString('83', $asunto);
        $this->assertStringContainsString('3.000.000', $cuerpo);
        $this->assertStringContainsString('2.500.000', $cuerpo);
        $this->assertStringContainsString('500.000', $cuerpo);
        $this->assertStringContainsString('80 %', $cuerpo);
        $this->assertStringNotContainsString('Hola ,', $cuerpo);
    }

    public function test_el_job_diario_emite_el_aviso_si_aun_no_existe(): void
    {
        $id = $this->tarjeta('1000000');
        $this->gastoTarjeta('1500000', ['credito_id' => $id]);
        Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)->where('usuario_id', $this->usuario->id)->delete();
        $this->assertSame([], $this->notificaciones());

        $emitidas = AlertaService::evaluarUsuario($this->usuario->id, CarbonImmutable::parse('2026-11-24'), AlertaService::DIARIO);

        $this->assertGreaterThanOrEqual(1, $emitidas);
        $this->assertCount(1, $this->notificaciones());

        // Segunda corrida del job: sin duplicados.
        AlertaService::evaluarUsuario($this->usuario->id, CarbonImmutable::parse('2026-11-24'), AlertaService::DIARIO);
        $this->assertCount(1, $this->notificaciones());
    }

    /** Las alertas se evalúan con DB::afterCommit: ver PresupuestoBordesTest. */
    private function alertasDentroDeLaTransaccion(): void
    {
        $administrador = new DatabaseTransactionsManager(['pgsql']);
        $this->app->instance('db.transactions', $administrador);
        DB::connection('pgsql')->setTransactionManager($administrador);
    }
}
