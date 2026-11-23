<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * RNG-01 / Ley 1581: un usuario no puede ver ni modificar recursos de otro. Cada
 * ruta con id de un recurso ajeno responde 404 "Recurso no encontrado" (como si no
 * existiera) y el recurso no cambia.
 */
class AislamientoRecursosTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $duenio;
    private Usuario $intruso;
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal();
        $this->duenio = $this->crearUsuario();
        $this->intruso = $this->crearUsuario();

        // El dueño crea un recurso de cada tipo por la app.
        $this->entrarComo($this->duenio);
        $fuente = $this->crearFuente($this->duenio);
        $this->ids['fuente'] = $fuente;
        $this->ids['ingreso'] = $this->postJson(route('ingresos.store'), ['fuente_id' => $fuente, 'fecha_recepcion' => '2026-11-10',
            'monto_original' => '500000', 'moneda' => 'COP'])->assertCreated()->json('data.id');
        $this->ids['recurrente'] = $this->postJson(route('ingresos.recurrentes.store'), ['periodicidad' => 'MENSUAL', 'fecha_inicio' => '2026-12-01',
            'modo_confirmacion' => 'AUTOMATICO', 'fuente_id' => $fuente, 'monto_original' => '500000', 'moneda' => 'COP'])->assertCreated()->json('data.id');
        $cat = DB::table('categorias')->where('tipo', 'GASTO')->whereNull('usuario_id')->whereNull('categoria_padre_id')->where('nombre', 'Alimentación')->value('id');
        $sub = DB::table('categorias')->where('categoria_padre_id', $cat)->value('id');
        $this->ids['gasto'] = $this->postJson(route('gastos.store'), ['fecha' => '2026-11-10', 'monto_original' => '80000', 'moneda' => 'COP',
            'categoria_id' => $cat, 'subcategoria_id' => $sub, 'medio_pago' => 'EFECTIVO', 'naturaleza' => 'VARIABLE', 'necesidad' => 'ESENCIAL',
            'relevancia_tributaria' => 'NO_DEDUCIBLE'])->assertCreated()->json('data.id');
        $this->ids['inversion'] = $this->postJson(route('inversiones.store'), ['nombre' => 'FIC del dueño', 'tipo' => 'FIC', 'moneda' => 'COP',
            'fecha_apertura' => '2026-03-01', 'monto_inicial' => '2000000'])->assertCreated()->json('data.id');

        $this->entrarComo($this->intruso);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public static function rutas(): array
    {
        return [
            'ver edición de ingreso' => ['get', 'ingresos.edit', 'ingreso', []],
            'editar ingreso' => ['putJson', 'ingresos.update', 'ingreso', ['monto_original' => '1', 'moneda' => 'COP', 'fecha_recepcion' => '2026-11-10']],
            'confirmar ingreso' => ['patchJson', 'ingresos.confirmar', 'ingreso', []],
            'eliminar ingreso' => ['deleteJson', 'ingresos.destroy', 'ingreso', []],
            'editar fuente' => ['putJson', 'ingresos.fuentes.update', 'fuente', ['nombre' => 'Robada']],
            'inactivar fuente' => ['patchJson', 'ingresos.fuentes.toggle', 'fuente', []],
            'eliminar fuente' => ['deleteJson', 'ingresos.fuentes.destroy', 'fuente', []],
            'editar recurrente' => ['putJson', 'ingresos.recurrentes.update', 'recurrente', ['monto_original' => '1']],
            'pausar recurrente' => ['patchJson', 'ingresos.recurrentes.toggle', 'recurrente', []],
            'eliminar recurrente' => ['deleteJson', 'ingresos.recurrentes.destroy', 'recurrente', []],
            'ver edición de gasto' => ['get', 'gastos.edit', 'gasto', []],
            'editar gasto' => ['putJson', 'gastos.update', 'gasto', ['monto_original' => '1']],
            'eliminar gasto' => ['deleteJson', 'gastos.destroy', 'gasto', []],
            'ver inversión' => ['getJson', 'inversiones.show', 'inversion', []],
            'editar inversión' => ['putJson', 'inversiones.update', 'inversion', ['nombre' => 'Robada']],
            'eliminar inversión' => ['deleteJson', 'inversiones.destroy', 'inversion', []],
            'movimientos de inversión' => ['getJson', 'inversiones.movimientos.index', 'inversion', []],
            'valorar inversión' => ['postJson', 'inversiones.valoraciones.store', 'inversion', ['fecha' => '2026-11-20', 'valor' => '1']],
            'rentabilidad de inversión' => ['getJson', 'inversiones.rentabilidad', 'inversion', []],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rutas')]
    public function test_un_recurso_ajeno_responde_404(string $metodo, string $ruta, string $recurso, array $datos): void
    {
        $url = route($ruta, $this->ids[$recurso]);
        $respuesta = $metodo === 'get' ? $this->get($url) : $this->{$metodo}($url, $datos);

        $respuesta->assertNotFound();
        if ($metodo === 'get') {
            $respuesta->assertSee('Recurso no encontrado');
        }
    }

    public function test_el_recurso_ajeno_no_cambia_y_el_intento_queda_en_la_bitacora(): void
    {
        $this->deleteJson(route('ingresos.destroy', $this->ids['ingreso']))->assertNotFound();
        $this->putJson(route('ingresos.fuentes.update', $this->ids['fuente']), ['nombre' => 'Robada'])->assertNotFound();

        $this->assertNull(DB::table('ingresos')->where('id', $this->ids['ingreso'])->value('eliminado_en'));
        $this->assertNotSame('Robada', DB::table('fuentes_ingreso')->where('id', $this->ids['fuente'])->value('nombre'));
        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'ACCESO_DENEGADO_RECURSO_AJENO', 'entidad_id' => $this->ids['ingreso'], 'usuario_id' => $this->intruso->id,
        ]);
    }
}
