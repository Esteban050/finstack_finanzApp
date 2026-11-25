<?php

namespace Tests\Feature;

use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\Usuario;
use App\Models\ValoracionInversion;
use App\Services\RentabilidadService;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H26: el ROI del portafolio usa la misma base que el de cada inversion (total de aportes, RN-040.1), la XIRR de
 * un periodo menor a un ano se rotula como anualizada con advertencia y la columna GMF explica que la exencion
 * 4x1000 no se registra.
 */
class InversionRoiXirrGmfTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->actingAs($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function inversion(string $nombre, string $apertura, string $aporte, string $valor): Inversion
    {
        $inv = Inversion::create([
            'usuario_id' => $this->usuario->id, 'nombre' => $nombre, 'tipo' => 'FIC', 'moneda' => 'COP',
            'fecha_apertura' => $apertura, 'monto_inicial' => $aporte, 'estado' => 'ACTIVA',
        ]);
        MovimientoInversion::create([
            'usuario_id' => $this->usuario->id, 'inversion_id' => $inv->id, 'tipo' => 'APORTE', 'fecha' => $apertura,
            'monto' => $aporte, 'tasa_cambio_usada' => '1.0000', 'monto_cop' => $aporte,
        ]);
        ValoracionInversion::create([
            'usuario_id' => $this->usuario->id, 'inversion_id' => $inv->id, 'fecha' => '2026-11-24', 'valor' => $valor, 'origen' => 'MANUAL',
        ]);

        return $inv;
    }

    public function test_el_roi_del_portafolio_es_la_ganancia_total_sobre_el_total_de_aportes(): void
    {
        $a = $this->inversion('Fondo A', '2026-09-24', '6112000.00', '6835000.00');   // ganancia 723.000 -> 11,83 %
        $this->inversion('Fondo B', '2026-09-24', '2000000.00', '2000000.00');         // sin ganancia

        $portafolio = RentabilidadService::portafolio(CarbonImmutable::parse('2026-11-24'));
        $filas = collect($portafolio['detalle'])->keyBy('nombre');

        $this->assertSame('11.83', $filas['Fondo A']['roi']);
        $this->assertSame('0.00', $filas['Fondo B']['roi']);
        $this->assertSame('8.91', $portafolio['roi'], '723.000 / (6.112.000 + 2.000.000): la misma base de aportes de cada inversion');
        $this->assertSame('11.83', RentabilidadService::indicadores($a, CarbonImmutable::parse('2026-11-24'))['roi']);
    }

    public function test_la_xirr_de_menos_de_un_ano_se_marca_como_anualizada_de_periodo_corto(): void
    {
        $corta = $this->inversion('Corta', '2026-09-24', '1000000.00', '1100000.00');
        $larga = $this->inversion('Larga', '2025-09-01', '1000000.00', '1100000.00');
        $corte = CarbonImmutable::parse('2026-11-24');

        $this->assertTrue(RentabilidadService::indicadores($corta, $corte)['xirr_periodo_corto']);
        $this->assertFalse(RentabilidadService::indicadores($larga, $corte)['xirr_periodo_corto']);

        $filas = collect(RentabilidadService::portafolio($corte)['detalle'])->keyBy('nombre');
        $this->assertTrue($filas['Corta']['xirr_periodo_corto']);
        $this->assertFalse($filas['Larga']['xirr_periodo_corto']);
    }

    public function test_el_detalle_y_el_portafolio_advierten_de_la_xirr_anualizada_en_periodo_corto(): void
    {
        $corta = $this->inversion('Corta', '2026-09-24', '1000000.00', '1100000.00');

        $this->get(route('inversiones.show', $corta->id))->assertOk()
            ->assertSee('XIRR anualizada')
            ->assertSee('menos de 1 año');
        $this->get(route('inversiones.portafolio'))->assertOk()
            ->assertSee('menos de 1 año');
    }

    public function test_la_columna_gmf_explica_que_la_exencion_no_se_registra(): void
    {
        $inv = $this->inversion('Fondo', '2026-09-24', '1000000.00', '1100000.00');

        $this->get(route('inversiones.show', $inv->id))->assertOk()
            ->assertSee('no se registra la exención');
    }
}
