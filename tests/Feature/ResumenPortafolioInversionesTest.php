<?php

namespace Tests\Feature;

use App\Models\Inversion;
use App\Models\ValoracionInversion;
use App\Services\RentabilidadService;
use App\Services\Reportes\DashboardResumenService;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H05: el KPI "Portafolio de inversiones" de /resumen usa el mismo valor
 * consolidado que el módulo Inversiones, a la misma fecha de corte.
 */
class ResumenPortafolioInversionesTest extends TestCase
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

    private function cdt(string $usuarioId, string $nombre, string $estado, ?string $liquidada): Inversion
    {
        return Inversion::create([
            'usuario_id' => $usuarioId, 'nombre' => $nombre, 'tipo' => 'CDT', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => '2000000.00', 'estado' => $estado, 'liquidada_en' => $liquidada,
        ]);
    }

    public function test_kpi_portafolio_coincide_con_el_valor_consolidado_de_inversiones(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);

        $activa = $this->cdt($u->id, 'CDT Bancolombia', 'ACTIVA', null);
        ValoracionInversion::create(['usuario_id' => $u->id, 'inversion_id' => $activa->id, 'fecha' => '2026-11-20', 'valor' => '2000000.00', 'origen' => 'MANUAL']);

        // Liquidada antes del corte: no debe sumar.
        $liquidada = $this->cdt($u->id, 'CDT Davivienda', 'LIQUIDADA', '2026-11-10');
        ValoracionInversion::create(['usuario_id' => $u->id, 'inversion_id' => $liquidada->id, 'fecha' => '2026-11-10', 'valor' => '5230807.68', 'origen' => 'MANUAL']);

        $hoy = CarbonImmutable::parse('2026-11-22');
        $consolidado = RentabilidadService::portafolio($hoy);
        $this->assertSame('2000000.00', $consolidado['valor_total']);

        $datos = app(DashboardResumenService::class)->obtenerDashboard($u, 'mes-actual', null, null, $hoy);
        $kpi = collect($datos['kpis'])->firstWhere('clave', 'portafolio');

        $this->assertSame($consolidado['valor_total'], $kpi['valor']);
        $this->assertSame(1, $kpi['inversiones_activas']);
        $this->assertSame((string) $consolidado['ganancia'], (string) $kpi['rendimiento_estimado']);
    }
}
