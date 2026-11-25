<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Services\InversionService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H03: el ingreso generado por un rendimiento de inversión guarda bruto,
 * retención y neto por separado (coherente con Inversiones y Reportes).
 */
class IngresoRendimientoInversionBrutoTest extends TestCase
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

    private function cdt(string $usuarioId): Inversion
    {
        return Inversion::create([
            'usuario_id' => $usuarioId, 'nombre' => 'CDT Davivienda', 'tipo' => 'CDT', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => '5000000.00', 'estado' => 'ACTIVA',
        ]);
    }

    public function test_abono_de_intereses_de_cdt_guarda_bruto_retencion_y_neto(): void
    {
        $u = $this->crearUsuario();
        $inv = $this->cdt($u->id);
        $mov = MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $inv->id, 'tipo' => 'ABONO_INTERES', 'fecha' => '2026-10-24',
            'monto' => '43137.98', 'monto_bruto' => '44935.40', 'retencion_aplicada' => '1797.42',
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => '43137.98',
        ]);

        InversionService::registrarPuentesTributarios($mov, $inv);

        $ingreso = Ingreso::findOrFail($mov->fresh()->ingreso_id);
        $this->assertSame('44935.40', (string) $ingreso->monto_bruto_cop);
        $this->assertSame('1797.42', (string) $ingreso->retencion_fuente_cop);
        $this->assertSame('43137.98', (string) $ingreso->monto_neto_cop);
    }

    public function test_redencion_de_cdt_guarda_rendimiento_bruto_retencion_y_neto(): void
    {
        $u = $this->crearUsuario();
        $inv = $this->cdt($u->id);
        $mov = MovimientoInversion::create([
            'usuario_id' => $u->id, 'inversion_id' => $inv->id, 'tipo' => 'REDENCION', 'fecha' => '2026-11-24',
            'monto' => '5221575.37', 'monto_bruto' => '5230807.68', 'retencion_aplicada' => '9232.31',
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => '5221575.37',
        ]);

        InversionService::registrarPuentesTributarios($mov, $inv, ['parte_utilidad' => '230807.68']);

        $ingreso = Ingreso::findOrFail($mov->fresh()->ingreso_id);
        $this->assertSame('230807.68', (string) $ingreso->monto_bruto_cop);
        $this->assertSame('9232.31', (string) $ingreso->retencion_fuente_cop);
        $this->assertSame('221575.37', (string) $ingreso->monto_neto_cop);
    }
}
