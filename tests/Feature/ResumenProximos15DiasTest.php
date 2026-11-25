<?php

namespace Tests\Feature;

use App\Models\PlantillaRecurrencia;
use App\Services\Reportes\DashboardResumenService;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H14: "Próximos 15 días" del Resumen muestra el monto del gasto recurrente.
 */
class ResumenProximos15DiasTest extends TestCase
{
    use UsaBaseLocal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal();
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_gasto_recurrente_muestra_su_monto(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);

        PlantillaRecurrencia::create([
            'usuario_id' => $u->id, 'tipo_movimiento' => 'GASTO', 'periodicidad' => 'MENSUAL',
            'fecha_inicio' => '2026-11-24', 'modo_confirmacion' => 'AUTOMATICO', 'es_monto_estimado' => false,
            'proxima_ejecucion' => '2026-11-24', 'activa' => true,
            // Mismas claves que guarda GastoRecurrenteController (GastoService::prepararMontos).
            'datos_plantilla' => ['descripcion' => 'Claro Hogar Internet', 'moneda' => 'COP', 'monto_original' => '110000.00', 'monto_cop' => '110000.00'],
        ]);

        $items = app(DashboardResumenService::class)->obtenerProximos15Dias($u, CarbonImmutable::parse('2026-11-22'));

        $this->assertCount(1, $items);
        $this->assertSame('110000.00', $items[0]['monto']);
    }
}
