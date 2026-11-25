<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Services\Renta\ConsolidadoRentaService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H20 (decisión: incluir): los dividendos recibidos en el año gravable suman a los ingresos
 * brutos de la cédula general (C2) y la pantalla ya no dice que no se consideran.
 */
class RentaDividendosC2Test extends TestCase
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

    private function ingreso(string $usuarioId, string $fuente, string $clasificacion, string $fecha, string $bruto): void
    {
        (new Ingreso)->forceFill([
            'usuario_id' => $usuarioId, 'fuente_id' => $fuente, 'fecha_recepcion' => $fecha,
            'clasificacion_tributaria' => $clasificacion, 'moneda' => 'COP', 'monto_original' => $bruto,
            'tasa_cambio_usada' => '1.0000', 'monto_bruto_cop' => $bruto, 'retencion_fuente_cop' => '0.00',
            'aportes_descontados_cop' => '0.00', 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
    }

    public function test_los_dividendos_del_anio_suman_a_los_ingresos_brutos_de_c2(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $fuente = $this->crearFuente($u);

        $this->ingreso($u->id, $fuente, 'DIVIDENDOS', '2026-10-15', '240000.00');
        $this->ingreso($u->id, $fuente, 'DIVIDENDOS', '2025-10-15', '999999.00'); // otro año: no cuenta
        $this->ingreso($u->id, $fuente, 'RENTAS_TRABAJO', '2026-10-31', '1000000.00');

        $brutos = app(ConsolidadoRentaService::class)->consolidar($u, 2026)['ingresos_brutos'];

        $this->assertEqualsWithDelta(240000.0, $brutos['dividendos_cop'], 0.005);
        $this->assertEqualsWithDelta(1240000.0, $brutos['total_cop'], 0.005);
        $this->assertCount(1, $brutos['drill_down']['dividendos']);
    }

    public function test_la_estimacion_ya_no_dice_que_no_considera_dividendos(): void
    {
        $u = $this->crearUsuario();
        $this->entrarComo($u);
        $fuente = $this->crearFuente($u);
        $this->ingreso($u->id, $fuente, 'DIVIDENDOS', '2026-10-15', '240000.00');

        $this->get('/renta/2026/estimacion')->assertOk()
            ->assertDontSee('Cédula de dividendos y participaciones (ETFs/Acciones con dividendos decretados)', false)
            ->assertSee('Dividendos y participaciones');
    }
}
