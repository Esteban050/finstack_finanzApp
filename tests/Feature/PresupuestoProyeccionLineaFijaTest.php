<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\PlantillaRecurrencia;
use App\Models\Usuario;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H21: una sola regla para las lineas fijas sin gasto: proyectan su monto asignado, tengan o no una
 * recurrencia pendiente. Una linea variable sin gasto sigue proyectando 0.
 */
class PresupuestoProyeccionLineaFijaTest extends TestCase
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
        $this->padre = $this->categoria(null, null, 'Obligaciones');
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function categoria(?Categoria $padre, ?string $naturaleza, string $nombre): Categoria
    {
        $c = new Categoria;
        $c->forceFill([
            'usuario_id' => $this->usuario->id, 'categoria_padre_id' => $padre?->id, 'tipo' => 'GASTO',
            'nombre' => $nombre.' '.Str::random(5), 'naturaleza_sugerida' => $naturaleza,
            'necesidad_sugerida' => $padre === null ? null : 'ESENCIAL', 'es_predeterminada' => false,
            'activa' => true, 'creado_en' => now(),
        ])->save();

        return $c;
    }

    private function lineaPorCategoria(string $categoriaId): array
    {
        return collect($this->getJson('/presupuestos')->assertOk()->json('data.lineas'))
            ->firstWhere('categoria_id', $categoriaId);
    }

    public function test_lineas_fijas_sin_gasto_proyectan_su_asignado_y_las_variables_cero(): void
    {
        $cuota = $this->categoria($this->padre, 'FIJO', 'Cuota de credito');
        $internet = $this->categoria($this->padre, 'FIJO', 'Internet Hogar');
        $mercado = $this->categoria($this->padre, 'VARIABLE', 'Mercado');

        // Internet Hogar tiene su recurrente del 24/11 pendiente por 110.000.
        PlantillaRecurrencia::create([
            'usuario_id' => $this->usuario->id, 'tipo_movimiento' => 'GASTO', 'periodicidad' => 'MENSUAL',
            'fecha_inicio' => '2026-11-24', 'modo_confirmacion' => 'AUTOMATICO', 'es_monto_estimado' => false,
            'proxima_ejecucion' => '2026-11-24', 'activa' => true,
            'datos_plantilla' => [
                'categoria_id' => $this->padre->id, 'subcategoria_id' => $internet->id, 'descripcion' => 'Claro Hogar',
                'moneda' => 'COP', 'monto_original' => '110000.00', 'monto_cop' => '110000.00',
            ],
        ]);

        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false, 'trasladar_saldo' => false,
            'lineas' => [
                ['categoria_id' => $cuota->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '500000'],
                ['categoria_id' => $internet->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '110000'],
                ['categoria_id' => $mercado->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '800000'],
            ],
        ])->assertSuccessful();

        $this->assertSame('500000', $this->lineaPorCategoria($cuota->id)['proyeccion'], 'cuota de credito (fija, sin gasto)');
        $this->assertSame('110000', $this->lineaPorCategoria($internet->id)['pendientes_recurrentes'], 'la recurrencia esta pendiente');
        $this->assertSame('110000', $this->lineaPorCategoria($internet->id)['proyeccion'], 'internet (fija, recurrencia pendiente)');
        $this->assertSame('0', $this->lineaPorCategoria($mercado->id)['proyeccion'], 'variable sin gasto');
    }
}
