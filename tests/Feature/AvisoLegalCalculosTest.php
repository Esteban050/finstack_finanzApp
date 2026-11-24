<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** RNG-08 (CP-RNG-008): toda pantalla con cálculos muestra el aviso de valores estimados. */
class AvisoLegalCalculosTest extends TestCase
{
    use UsaBaseLocal;

    private const AVISO = 'Valores estimados con fines informativos. No constituyen asesoría tributaria, contable ni financiera.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal();
        Http::fake(['*' => Http::response([], 200)]);
        $this->entrarComo($this->crearUsuario());
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_pantallas_con_calculos_muestran_el_aviso(): void
    {
        foreach (['/resumen', '/reportes/indicadores', '/reportes/comparativo', '/presupuestos', '/seguridad-social',
            '/creditos', '/inversiones', '/renta/2025/consolidado', '/renta/2025/estimacion'] as $ruta) {
            $this->get($ruta)->assertOk()->assertSee(self::AVISO, false);
        }
    }
}
