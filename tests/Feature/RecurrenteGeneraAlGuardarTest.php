<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Usuario;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H15: al guardar un ingreso/gasto recurrente cuya fecha de inicio ya llego, la ocurrencia de esa fecha se
 * genera de inmediato (sin esperar al job de las 00:05) y la tarjeta de gastos titula con el comercio.
 */
class RecurrenteGeneraAlGuardarTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    private Categoria $padre;

    private Categoria $sub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $this->padre = $this->categoria(null);
        $this->sub = $this->categoria($this->padre);
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
            'nombre' => 'Prueba '.Str::random(8), 'naturaleza_sugerida' => $padre === null ? null : 'FIJO',
            'necesidad_sugerida' => $padre === null ? null : 'ESENCIAL', 'es_predeterminada' => false,
            'activa' => true, 'creado_en' => now(),
        ])->save();

        return $c;
    }

    private function gastoRecurrente(string $inicio, string $modo = 'AUTOMATICO', array $extra = [])
    {
        return $this->postJson(route('gastos.recurrentes.store'), array_merge([
            'periodicidad' => 'MENSUAL', 'fecha_inicio' => $inicio, 'modo_confirmacion' => $modo,
            'monto_original' => '110000', 'moneda' => 'COP', 'categoria_id' => $this->padre->id,
            'subcategoria_id' => $this->sub->id, 'medio_pago' => 'EFECTIVO', 'comercio_beneficiario' => 'Claro Hogar',
        ], $extra))->assertCreated();
    }

    public function test_gasto_recurrente_con_inicio_hoy_genera_la_ocurrencia_al_guardar(): void
    {
        $r = $this->gastoRecurrente('2026-11-24');

        $this->assertSame(1, $r->json('data.ocurrencias_generadas'));
        $this->assertSame('2026-12-24', substr((string) $r->json('data.proxima_ejecucion'), 0, 10));
        $gasto = Gasto::where('usuario_id', $this->usuario->id)->get();
        $this->assertCount(1, $gasto);
        $this->assertSame('2026-11-24', $gasto[0]->fecha->toDateString());
        $this->assertSame('CONFIRMADO', $gasto[0]->estado);
        $this->assertStringContainsString('2026', (string) $r->json('mensaje'));
    }

    public function test_gasto_recurrente_que_requiere_confirmacion_nace_pendiente(): void
    {
        $this->gastoRecurrente('2026-11-24', 'REQUIERE_CONFIRMACION');

        $this->assertSame('PENDIENTE', Gasto::where('usuario_id', $this->usuario->id)->value('estado'));
    }

    public function test_inicio_futuro_no_genera_nada_hasta_su_fecha(): void
    {
        $r = $this->gastoRecurrente('2026-12-01');

        $this->assertSame(0, $r->json('data.ocurrencias_generadas'));
        $this->assertSame(0, Gasto::where('usuario_id', $this->usuario->id)->count());
    }

    public function test_ingreso_recurrente_con_inicio_hoy_genera_la_ocurrencia_al_guardar(): void
    {
        $fuente = $this->crearFuente($this->usuario);

        $r = $this->postJson(route('ingresos.recurrentes.store'), [
            'periodicidad' => 'MENSUAL', 'fecha_inicio' => '2026-11-24', 'modo_confirmacion' => 'AUTOMATICO',
            'fuente_id' => $fuente, 'monto_original' => '2000000.00', 'moneda' => 'COP',
        ])->assertCreated();

        $this->assertSame(1, $r->json('data.ocurrencias_generadas'));
        $ingresos = Ingreso::where('usuario_id', $this->usuario->id)->get();
        $this->assertCount(1, $ingresos);
        $this->assertSame('2026-11-24', $ingresos[0]->fecha_recepcion->toDateString());
    }

    public function test_la_tarjeta_de_gastos_recurrentes_titula_con_el_comercio(): void
    {
        $this->gastoRecurrente('2026-12-01');
        $html = $this->get(route('gastos.recurrentes.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<h2>Claro Hogar</h2>', $html);

        $this->gastoRecurrente('2026-12-02', 'AUTOMATICO', ['comercio_beneficiario' => '', 'descripcion' => 'Cuota gimnasio']);
        $html = $this->get(route('gastos.recurrentes.index'))->getContent();
        $this->assertStringContainsString('<h2>Cuota gimnasio</h2>', $html);
    }
}
