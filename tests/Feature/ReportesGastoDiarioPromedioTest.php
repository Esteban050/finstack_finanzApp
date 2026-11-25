<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Usuario;
use App\Services\Reportes\IndicadoresSaludService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H19: el gasto diario promedio divide entre los dias transcurridos cuando el periodo es el mes en curso
 * y entre los dias del mes cuando esta cerrado.
 */
class ReportesGastoDiarioPromedioTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    private Categoria $sub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $padre = $this->categoria();
        $this->sub = $this->categoria($padre);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function categoria(?Categoria $padre = null): Categoria
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

    private function gasto(string $fecha, string $monto): void
    {
        (new Gasto)->forceFill([
            'usuario_id' => $this->usuario->id, 'categoria_id' => $this->sub->categoria_padre_id,
            'subcategoria_id' => $this->sub->id, 'fecha' => $fecha, 'moneda' => 'COP', 'monto_original' => $monto,
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => $monto, 'naturaleza' => 'VARIABLE',
            'necesidad' => 'DISCRECIONAL', 'medio_pago' => 'EFECTIVO', 'relevancia_tributaria' => 'NO_DEDUCIBLE',
            'etiquetas' => [], 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
    }

    private function indicador(string $mes, string $hoy): array
    {
        $r = app(IndicadoresSaludService::class)->calcularIndicadores($this->usuario, $mes, CarbonImmutable::parse($hoy));

        return collect($r['indicadores'])->firstWhere('id', 'gasto_diario_promedio');
    }

    public function test_mes_en_curso_divide_entre_los_dias_transcurridos(): void
    {
        $this->gasto('2026-11-02', '4118900');

        $ind = $this->indicador('2026-11', '2026-11-24');

        $this->assertEqualsWithDelta(171620.83, $ind['valor'], 0.005);
        $this->assertSame('24', $ind['detalle']['variables']['Días transcurridos']);
        $this->assertEqualsWithDelta(171620.83, end($ind['tendencia_6m'])['promedio_diario'], 0.005);
    }

    public function test_mes_cerrado_divide_entre_los_dias_del_mes(): void
    {
        $this->gasto('2026-10-02', '3100000');

        $ind = $this->indicador('2026-10', '2026-11-24');

        $this->assertEqualsWithDelta(100000.0, $ind['valor'], 0.005);
        $this->assertSame('31', $ind['detalle']['variables']['Días transcurridos']);
    }

    public function test_el_mes_anterior_usa_los_dias_completos_del_mes(): void
    {
        $this->gasto('2026-10-02', '3100000');
        $this->gasto('2026-11-02', '240000');

        $ind = $this->indicador('2026-11', '2026-11-24');

        $this->assertSame('$ 100.000', $ind['referencia_anterior']);
    }

    public function test_t12b_el_mes_en_curso_excluye_los_gastos_posteriores_a_hoy(): void
    {
        $this->gasto('2026-11-02', '4118900');
        $this->gasto('2026-11-28', '1000000');   // futuro: aun no ocurre

        $ind = $this->indicador('2026-11', '2026-11-24');

        $this->assertEqualsWithDelta(171620.83, $ind['valor'], 0.005);
        $this->assertEqualsWithDelta(171620.83, end($ind['tendencia_6m'])['promedio_diario'], 0.005);
    }
}
