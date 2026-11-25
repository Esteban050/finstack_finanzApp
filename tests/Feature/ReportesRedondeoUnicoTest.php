<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Usuario;
use App\Services\Reportes\IndicadoresSaludService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H22: los porcentajes se redondean una sola vez, desde el valor crudo, a 2 decimales (ejecucion del
 * presupuesto, concentracion por categoria) y la regla 50/30/20 suma exactamente 100 %.
 */
class ReportesRedondeoUnicoTest extends TestCase
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

    /** @return Categoria la subcategoria donde se registran los gastos */
    private function gasto(string $monto, ?Categoria $sub = null, string $necesidad = 'DISCRECIONAL'): Categoria
    {
        $sub ??= $this->categoria($this->categoria());
        (new Gasto)->forceFill([
            'usuario_id' => $this->usuario->id, 'categoria_id' => $sub->categoria_padre_id, 'subcategoria_id' => $sub->id,
            'fecha' => '2026-11-05', 'moneda' => 'COP', 'monto_original' => $monto, 'tasa_cambio_usada' => '1.0000',
            'monto_cop' => $monto, 'naturaleza' => 'VARIABLE', 'necesidad' => $necesidad, 'medio_pago' => 'EFECTIVO',
            'relevancia_tributaria' => 'NO_DEDUCIBLE', 'etiquetas' => [], 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();

        return $sub;
    }

    private function ingreso(string $bruto): void
    {
        (new Ingreso)->forceFill([
            'usuario_id' => $this->usuario->id, 'fuente_id' => $this->crearFuente($this->usuario), 'fecha_recepcion' => '2026-11-03',
            'clasificacion_tributaria' => 'RENTAS_TRABAJO', 'moneda' => 'COP', 'monto_original' => $bruto,
            'tasa_cambio_usada' => '1.0000', 'monto_bruto_cop' => $bruto, 'retencion_fuente_cop' => '0.00',
            'aportes_descontados_cop' => '0.00', 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
    }

    private function indicador(string $id): array
    {
        $r = app(IndicadoresSaludService::class)->calcularIndicadores($this->usuario, '2026-11', CarbonImmutable::parse('2026-11-24'));

        return collect($r['indicadores'])->firstWhere('id', $id);
    }

    public function test_ejecucion_del_presupuesto_se_redondea_una_sola_vez(): void
    {
        $sub = $this->gasto('4118900');
        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false, 'trasladar_saldo' => false,
            'lineas' => [['categoria_id' => $sub->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '3160000']],
        ])->assertSuccessful();

        $ind = $this->indicador('ejecucion_presupuesto');

        $this->assertEqualsWithDelta(130.34, $ind['valor'], 0.0001);
        $this->assertSame('130.34%', $ind['valor_formateado']);
        $this->assertSame('EXCEDIDO', $ind['estado']);
    }

    public function test_concentracion_por_categoria_con_dos_decimales(): void
    {
        $this->gasto('1543900');
        $this->gasto('670000');
        $this->gasto('1905000');

        $ind = $this->indicador('concentracion_gasto');

        $this->assertEqualsWithDelta(46.25, $ind['valor'], 0.0001);
        $this->assertEqualsWithDelta(37.48, $ind['top3'][1]['porcentaje'], 0.0001);
        $this->assertEqualsWithDelta(16.27, $ind['top3'][2]['porcentaje'], 0.0001);
    }

    public function test_la_regla_50_30_20_suma_exactamente_100(): void
    {
        $this->ingreso('10000000');
        $this->gasto('3333333', null, 'ESENCIAL');
        $this->gasto('3333333', null, 'DISCRECIONAL');   // ahorro = 3.333.334

        $v = $this->indicador('regla_50_30_20')['valor'];

        $this->assertEqualsWithDelta(100.0, $v['esenciales'] + $v['discrecionales'] + $v['ahorro'], 0.00001);
    }
}
