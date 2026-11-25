<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Ingreso;
use App\Models\Usuario;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H09: el payload del presupuesto dice de dónde sale el ingreso base (MES_EN_CURSO o MESES_CERRADOS),
 * para que la pantalla no lo rotule "promedio" cuando es el ingreso del mes en curso.
 */
class PresupuestoBaseIngresoEtiquetaTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function ingreso(string $fecha, string $monto, string $fuente): void
    {
        (new Ingreso)->forceFill([
            'usuario_id' => $this->usuario->id, 'fuente_id' => $fuente, 'fecha_recepcion' => $fecha,
            'clasificacion_tributaria' => 'RENTAS_TRABAJO', 'moneda' => 'COP', 'monto_original' => $monto,
            'tasa_cambio_usada' => '1.0000', 'monto_bruto_cop' => $monto, 'retencion_fuente_cop' => '0.00',
            'aportes_descontados_cop' => '0.00', 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
    }

    private function presupuesto(): array
    {
        $padre = new Categoria;
        $padre->forceFill(['usuario_id' => $this->usuario->id, 'tipo' => 'GASTO', 'nombre' => 'P '.Str::random(6),
            'es_predeterminada' => false, 'activa' => true, 'creado_en' => now()])->save();
        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false, 'trasladar_saldo' => false,
            'lineas' => [['categoria_id' => $padre->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '1000000']],
        ])->assertSuccessful();

        return $this->getJson('/presupuestos?mes=2026-11')->assertOk()->json('data');
    }

    public function test_sin_meses_cerrados_con_ingresos_la_base_es_el_mes_en_curso(): void
    {
        $fuente = $this->crearFuente($this->usuario);
        $this->ingreso('2026-11-03', '5000000', $fuente);

        $this->assertSame('MES_EN_CURSO', $this->presupuesto()['ingreso_base_tipo']);
    }

    public function test_con_meses_cerrados_suficientes_la_base_es_el_promedio(): void
    {
        $fuente = $this->crearFuente($this->usuario);
        $this->ingreso('2026-10-03', '5000000', $fuente);
        $this->ingreso('2026-09-03', '5000000', $fuente);

        $this->assertSame('MESES_CERRADOS', $this->presupuesto()['ingreso_base_tipo']);
    }
}
