<?php

namespace Tests\Feature;

use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Services\Renta\ConsolidadoRentaService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * T20b (H20): el dividendo generado por un movimiento DIVIDENDO de Inversiones cuenta una sola vez
 * en C2 y la pantalla del consolidado tolera payloads sin las claves de dividendos.
 */
class RentaDividendosInversionTest extends TestCase
{
    use UsaBaseLocal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function dividendoDeInversion(string $usuarioId): void
    {
        $accion = Inversion::create([
            'usuario_id' => $usuarioId, 'nombre' => 'Ecopetrol', 'tipo' => 'ACCION_ETF', 'moneda' => 'COP',
            'fecha_apertura' => '2026-01-01', 'monto_inicial' => '1500000.00', 'estado' => 'ACTIVA',
        ]);
        $accion->detalleRentaVariable()->create([
            'simbolo' => 'ECOPETROL', 'nombre_instrumento' => 'Ecopetrol', 'tipo' => 'ACCION', 'mercado' => 'BVC',
            'unidades_total' => '150.00000000', 'costo_promedio_unitario' => '10000.00000000',
            'fecha_primera_compra' => '2026-01-01', 'tipo_reparto' => 'DISTRIBUTIVO',
        ]);

        $this->post(route('inversiones.movimientos.store', $accion->id), [
            'tipo' => 'DIVIDENDO', 'fecha' => '2026-11-10', 'monto' => '240000', 'unidades' => '150',
            'valor_unidad' => '1600', 'moneda' => 'COP',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, MovimientoInversion::where('inversion_id', $accion->id)->where('tipo', 'DIVIDENDO')->count());
    }

    public function test_dividendo_de_inversion_cuenta_una_sola_vez_en_c2(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $this->dividendoDeInversion($u->id);

        $brutos = app(ConsolidadoRentaService::class)->consolidar($u, 2026)['ingresos_brutos'];

        $this->assertEqualsWithDelta(240000.0, $brutos['dividendos_cop'], 0.005);
        $this->assertCount(1, $brutos['drill_down']['dividendos']);
        $this->assertEqualsWithDelta(240000.0, $brutos['total_cop'], 0.005);
    }

    public function test_consolidado_tolera_un_payload_sin_claves_de_dividendos(): void
    {
        $u = $this->crearUsuario();
        $this->entrarComo($u);
        $this->dividendoDeInversion($u->id);

        $real = app(ConsolidadoRentaService::class)->consolidar($u, 2026);
        unset($real['ingresos_brutos']['dividendos_cop'], $real['ingresos_brutos']['dividendos_uvt']);

        $this->mock(ConsolidadoRentaService::class, fn ($m) => $m->shouldReceive('consolidar')->andReturn($real));

        $this->get('/renta/2026/consolidado')->assertOk()->assertSee('Dividendos y participaciones');
    }
}
