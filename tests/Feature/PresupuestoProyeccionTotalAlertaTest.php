<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Notificacion;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\Usuario;
use App\Services\AlertaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H10 (decision: agregar alerta del total): "Vas a pasarte" tambien avisa por el presupuesto TOTAL cuando su
 * proyeccion al cierre supera el umbral y el total aun no llego al 100 %. La alerta por linea sigue igual.
 */
class PresupuestoProyeccionTotalAlertaTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    private Categoria $padre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $this->padre = $this->categoria(null);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function categoria(?Categoria $padre): Categoria
    {
        $c = new Categoria;
        $c->forceFill([
            'usuario_id' => $this->usuario->id, 'categoria_padre_id' => $padre?->id, 'tipo' => 'GASTO',
            'nombre' => 'Prueba '.Str::random(8), 'naturaleza_sugerida' => $padre === null ? null : 'VARIABLE',
            'necesidad_sugerida' => $padre === null ? null : 'DISCRECIONAL', 'es_predeterminada' => false,
            'activa' => true, 'creado_en' => now(),
        ])->save();

        return $c;
    }

    private function gasto(Categoria $sub, string $fecha, string $monto): void
    {
        (new Gasto)->forceFill([
            'usuario_id' => $this->usuario->id, 'categoria_id' => $this->padre->id, 'subcategoria_id' => $sub->id,
            'fecha' => $fecha, 'moneda' => 'COP', 'monto_original' => $monto, 'tasa_cambio_usada' => '1.0000',
            'monto_cop' => $monto, 'naturaleza' => 'VARIABLE', 'necesidad' => 'DISCRECIONAL', 'medio_pago' => 'EFECTIVO',
            'relevancia_tributaria' => 'NO_DEDUCIBLE', 'etiquetas' => [], 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
    }

    /** Dos lineas de 1.000.000. @return array{0: Categoria, 1: Categoria} */
    private function presupuestoDeDosLineas(): array
    {
        $a = $this->categoria($this->padre);
        $b = $this->categoria($this->padre);
        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false, 'trasladar_saldo' => false,
            'lineas' => [
                ['categoria_id' => $a->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '1000000'],
                ['categoria_id' => $b->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '1000000'],
            ],
        ])->assertSuccessful();

        return [$a, $b];
    }

    private function evaluar(string $fecha = '2026-11-24'): void
    {
        AlertaService::evaluarUsuario($this->usuario->id, CarbonImmutable::parse($fecha), AlertaService::DIARIO);
    }

    private function alertas()
    {
        return Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $this->usuario->id)->where('tipo', 'PROYECCION_LINEA')->get();
    }

    public function test_el_total_proyectado_sobre_el_umbral_alerta_una_sola_vez_aunque_ninguna_linea_lo_haga(): void
    {
        [$a, $b] = $this->presupuestoDeDosLineas();
        $this->gasto($a, '2026-11-05', '1000000');   // linea A ya al 100 %: la alerta de linea no aplica
        $this->gasto($b, '2026-11-05', '800000');    // linea B proyecta 100 %: tampoco

        $this->evaluar();

        $alertas = $this->alertas();
        $this->assertCount(1, $alertas);
        $n = $alertas[0];
        $this->assertSame('PROYECCION_TOTAL:2026-11', $n->clave);
        $this->assertTrue($n->datos['total']);
        $this->assertSame('112.50', $n->datos['proyeccion_porc']);
        $this->assertSame('2250000', $n->datos['proyeccion']);
        $this->assertSame('2000000', $n->datos['asignado']);
        $this->assertSame('ADVERTENCIA', $n->prioridad);

        $this->evaluar();
        $this->assertCount(1, $this->alertas(), 'sin duplicados en el mismo periodo');
    }

    public function test_no_alerta_si_el_total_ya_paso_el_100_por_ciento(): void
    {
        [$a, $b] = $this->presupuestoDeDosLineas();
        $this->gasto($a, '2026-11-05', '1200000');
        $this->gasto($b, '2026-11-05', '900000');    // 105 % del total

        $this->evaluar();

        $this->assertSame([], $this->alertas()->pluck('clave')->filter(fn ($c) => str_starts_with($c, 'PROYECCION_TOTAL'))->all());
    }

    public function test_respeta_el_umbral_la_activacion_y_la_mitad_del_mes(): void
    {
        [$a, $b] = $this->presupuestoDeDosLineas();
        $this->gasto($a, '2026-11-05', '1000000');
        $this->gasto($b, '2026-11-05', '800000');    // proyeccion 112,5 %

        $this->putJson('/perfil/alertas', ['reglas' => [['tipo' => 'PROYECCION_LINEA', 'parametros' => ['umbral' => 120]]]])->assertOk();
        $this->evaluar();
        $this->assertCount(0, $this->alertas(), 'umbral 120 %: 112,5 % no alerta');

        $this->putJson('/perfil/alertas', ['reglas' => [['tipo' => 'PROYECCION_LINEA', 'parametros' => ['umbral' => 110], 'activa' => false]]])->assertOk();
        $this->evaluar();
        $this->assertCount(0, $this->alertas(), 'regla desactivada');

        $this->putJson('/perfil/alertas', ['reglas' => [['tipo' => 'PROYECCION_LINEA', 'activa' => true]]])->assertOk();
        $this->evaluar('2026-11-14');
        $this->assertCount(0, $this->alertas(), 'antes de la mitad del mes no alerta');
    }

    public function test_un_presupuesto_de_una_sola_linea_no_duplica_la_alerta_de_la_linea(): void
    {
        $a = $this->categoria($this->padre);
        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false, 'trasladar_saldo' => false,
            'lineas' => [['categoria_id' => $a->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '1000000']],
        ])->assertSuccessful();
        $this->gasto($a, '2026-11-20', '900000');    // proyeccion 112,5 %

        $this->evaluar();

        $this->assertSame(["PROYECCION_LINEA:{$a->id}:2026-11"], $this->alertas()->pluck('clave')->all());
    }
}
