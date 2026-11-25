<?php

namespace Tests\Feature;

use App\Models\Credito;
use App\Services\CreditoService;
use App\Services\PagoCreditoService;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * N01 (blindaje): una tarjeta sin compras registradas que pasa por regenerar() cobra en la primera cuota el
 * interés por días sobre el saldo (no 0): lo que el saldo tiene de más que las compras fechadas causa desde el inicio.
 */
class TarjetaSinComprasRegenerarInteresTest extends TestCase
{
    use UsaBaseLocal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-10 10:00:00');
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_primera_cuota_sin_compras_cobra_el_interes_por_dias_sobre_el_saldo(): void
    {
        $credito = new Credito;
        $credito->forceFill([
            'id' => (string) Str::uuid(), 'tipo_credito' => 'TARJETA_CREDITO', 'tasa_ea' => '0.2800', 'tasa_periodica' => '0.020758',
            'periodicidad' => 'MENSUAL', 'sistema_amortizacion' => 'FRANCES',
            'fecha_inicio' => '2026-11-10', 'fecha_primer_pago' => '2026-11-30',
        ]);
        $futuras = [
            ['numero' => 1, 'fecha' => '2026-11-30', 'cuota' => '500000', 'abono_capital' => '400000'],
            ['numero' => 2, 'fecha' => '2026-12-30', 'cuota' => '500000', 'abono_capital' => '400000'],
        ];

        $metodo = new \ReflectionMethod(PagoCreditoService::class, 'regenerar');
        $plan = $metodo->invoke(null, $credito, ['periodo_inicio' => '2026-11-10'], $futuras, '1000000', 'CUOTA', CarbonImmutable::parse('2026-11-10'));

        $this->assertSame(CreditoService::interesPorDias('1000000', '0.2800', 20), $plan[0]['interes']);
        $this->assertGreaterThan(0, (int) $plan[0]['interes']);
    }
}
