<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Usuario;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** El detalle de una tarjeta lista las compras (gastos vigentes) vinculadas a ella. */
class TarjetaComprasDetalleTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-23 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_el_detalle_de_la_tarjeta_lista_sus_compras_vigentes(): void
    {
        $id = $this->postJson('/creditos', $this->tarjeta())->assertCreated()->json('data.id');
        $uno = $this->postJson('/gastos', $this->gasto($id, '100000.00', ['fecha' => '2026-11-10', 'descripcion' => 'Mercado']))->assertCreated()->json('data.id');
        $this->postJson('/gastos', $this->gasto($id, '250000.00', ['fecha' => '2026-11-20', 'descripcion' => 'Zapatos']))->assertCreated();
        $borrado = $this->postJson('/gastos', $this->gasto($id, '50000.00', ['fecha' => '2026-11-21']))->assertCreated()->json('data.id');
        $this->deleteJson("/gastos/{$borrado}")->assertOk();

        $compras = $this->getJson("/creditos/{$id}")->assertOk()->json('data.compras');

        $this->assertCount(2, $compras);
        $this->assertSame('2026-11-20', $compras[0]['fecha']);
        $this->assertSame('Zapatos', $compras[0]['descripcion']);
        $this->assertSame('250000.00', $compras[0]['monto_cop']);
        $this->assertSame(1, $compras[0]['numero_cuotas']);
        $this->assertFalse($compras[0]['sin_intereses']);
        $this->assertSame($uno, $compras[1]['id']);
    }

    public function test_un_credito_que_no_es_tarjeta_no_trae_compras(): void
    {
        $id = $this->postJson('/creditos', $this->tarjeta(['tipo_credito' => 'LIBRE_INVERSION', 'cupo' => null]))->assertCreated()->json('data.id');

        $this->assertSame([], $this->getJson("/creditos/{$id}")->assertOk()->json('data.compras'));
    }

    private function tarjeta(array $extra = []): array
    {
        return array_filter(array_merge([
            'tipo_credito' => 'TARJETA_CREDITO', 'banco' => 'Banco QA', 'proposito' => 'Tarjeta QA', 'monto' => '1000000',
            'tasa' => '2', 'tipo_tasa' => 'MENSUAL', 'numero_cuotas' => 12, 'periodicidad' => 'MENSUAL',
            'fecha_inicio' => '2026-11-01', 'cupo' => '5000000',
        ], $extra), fn ($v) => $v !== null);
    }

    private function gasto(string $creditoId, string $monto, array $extra = []): array
    {
        $padre = $this->categoria();
        $sub = $this->categoria($padre);

        return array_merge([
            'categoria_id' => $padre->id, 'subcategoria_id' => $sub->id, 'fecha' => '2026-11-20', 'moneda' => 'COP',
            'monto_original' => $monto, 'medio_pago' => 'TARJETA_CREDITO', 'credito_id' => $creditoId,
        ], $extra);
    }

    private function categoria(?Categoria $padre = null): Categoria
    {
        $c = new Categoria;
        $c->forceFill([
            'usuario_id' => $this->usuario->id, 'categoria_padre_id' => $padre?->id, 'tipo' => 'GASTO',
            'nombre' => 'Prueba '.Str::random(8), 'naturaleza_sugerida' => $padre === null ? null : 'VARIABLE',
            'necesidad_sugerida' => $padre === null ? null : 'DISCRECIONAL', 'es_predeterminada' => false, 'activa' => true, 'creado_en' => now(),
        ])->save();

        return $c;
    }
}
