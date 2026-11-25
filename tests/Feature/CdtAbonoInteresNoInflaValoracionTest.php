<?php

namespace Tests\Feature;

use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\ValoracionInversion;
use App\Services\InversionService;
use App\Services\RentabilidadService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * N02: el interés cobrado de un CDT sale de la posición; no suma a la valoración ni se cuenta dos veces en la ganancia.
 * La cuenta de alto rendimiento sí capitaliza el abono.
 */
class CdtAbonoInteresNoInflaValoracionTest extends TestCase
{
    use UsaBaseLocal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-25 10:00:00');
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function abono(string $tipo): array
    {
        $u = $this->crearUsuario();
        $inv = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'Inv', 'tipo' => $tipo, 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => '5000000.00', 'estado' => 'ACTIVA',
        ]);
        MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $inv->id, 'tipo' => 'APORTE', 'fecha' => '2026-09-24',
            'monto' => '5000000.00', 'tasa_cambio_usada' => '1.0000', 'monto_cop' => '5000000.00', 'es_inicial' => true,
        ]);
        $mov = MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $inv->id, 'tipo' => 'ABONO_INTERES', 'fecha' => '2026-10-24',
            'monto' => '43137.98', 'monto_bruto' => '44935.40', 'retencion_aplicada' => '1797.42',
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => '43137.98',
        ]);

        return [$inv, $mov];
    }

    public function test_abono_de_intereses_de_cdt_no_aumenta_la_valoracion(): void
    {
        [$inv, $mov] = $this->abono('CDT');

        $val = InversionService::sincronizarValoracionTrasMovimiento($inv, $mov);

        $this->assertSame('5000000.00', (string) $val->valor);
    }

    public function test_abono_de_intereses_de_cdt_cuenta_la_ganancia_una_sola_vez(): void
    {
        [$inv, $mov] = $this->abono('CDT');
        InversionService::sincronizarValoracionTrasMovimiento($inv, $mov);

        $r = RentabilidadService::indicadores($inv->fresh(), \Carbon\CarbonImmutable::parse('2026-11-25'));

        $this->assertSame('43137.98', bcadd((string) $r['ganancia'], '0', 2));
    }

    public function test_abono_de_intereses_de_cuenta_de_alto_rendimiento_si_capitaliza(): void
    {
        [$inv, $mov] = $this->abono('CUENTA_ALTO_RENDIMIENTO');

        $val = InversionService::sincronizarValoracionTrasMovimiento($inv, $mov);

        $this->assertSame('5043137.98', (string) $val->valor);
    }
}
