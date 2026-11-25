<?php

namespace Tests\Feature;

use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\ValoracionInversion;
use App\Services\InversionService;
use App\Services\RentabilidadService;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H22/H23: "Valoracion desactualizada" no aparece en inversiones recien creadas aunque su fecha de apertura
 * sea antigua; la antiguedad de una accion se muestra redondeada.
 */
class InversionPresentacionUxTest extends TestCase
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

    private function inversion(string $apertura, ?string $creada = null): Inversion
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);
        $inv = Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'Fondo', 'tipo' => 'FIC', 'moneda' => 'COP',
            'fecha_apertura' => $apertura, 'monto_inicial' => '1000000.00', 'estado' => 'ACTIVA',
        ]);
        if ($creada !== null) {
            $inv->forceFill(['creado_en' => $creada])->save();
        }

        return $inv->refresh();
    }

    public function test_inversion_recien_creada_con_apertura_antigua_no_esta_desactualizada(): void
    {
        $inv = $this->inversion('2026-03-01');

        $this->assertFalse(InversionService::valoracionDesactualizada($inv, null, CarbonImmutable::parse('2026-11-24')));
    }

    public function test_inversion_creada_hace_mas_de_35_dias_sin_valoracion_si_esta_desactualizada(): void
    {
        $inv = $this->inversion('2026-03-01', '2026-09-01 10:00:00');

        $this->assertTrue(InversionService::valoracionDesactualizada($inv, null, CarbonImmutable::parse('2026-11-24')));
    }

    public function test_una_valoracion_reciente_o_la_creacion_reciente_evitan_el_aviso(): void
    {
        $inv = $this->inversion('2026-03-01', '2026-09-01 10:00:00');
        $reciente = new ValoracionInversion(['fecha' => '2026-11-10', 'valor' => '1100000.00']);
        $vieja = new ValoracionInversion(['fecha' => '2026-09-10', 'valor' => '1100000.00']);

        $this->assertFalse(InversionService::valoracionDesactualizada($inv, $reciente, CarbonImmutable::parse('2026-11-24')));
        $this->assertTrue(InversionService::valoracionDesactualizada($inv, $vieja, CarbonImmutable::parse('2026-11-24')));
    }

    public function test_la_antiguedad_se_expresa_en_anios_enteros_o_meses(): void
    {
        $hoy = CarbonImmutable::parse('2026-11-24');

        $this->assertSame('2 meses', InversionService::antiguedadTexto('2026-09-24', $hoy)['texto']);
        $this->assertSame(0, InversionService::antiguedadTexto('2026-09-24', $hoy)['anios']);
        $this->assertSame('1 mes', InversionService::antiguedadTexto('2026-10-20', $hoy)['texto']);
        $this->assertSame('menos de 1 mes', InversionService::antiguedadTexto('2026-11-10', $hoy)['texto']);
        $this->assertSame('1 año', InversionService::antiguedadTexto('2025-11-24', $hoy)['texto']);
        $this->assertSame('2 años y 3 meses', InversionService::antiguedadTexto('2024-08-20', $hoy)['texto']);
        $this->assertSame(2, InversionService::antiguedadTexto('2024-08-20', $hoy)['anios']);
        $this->assertSame('menos de 1 mes', InversionService::antiguedadTexto('2027-01-01', $hoy)['texto']);
    }

    /** H22: el ROI se redondea una sola vez desde el valor crudo (413.000 / 6.112.000 = 6,757 %) en todas las pantallas. */
    public function test_el_roi_es_el_mismo_en_el_detalle_y_en_el_portafolio(): void
    {
        $inv = $this->inversion('2026-09-24');
        MovimientoInversion::create([
            'usuario_id' => $inv->usuario_id, 'inversion_id' => $inv->id, 'tipo' => 'APORTE', 'fecha' => '2026-09-24',
            'monto' => '6112000.00', 'tasa_cambio_usada' => '1.0000', 'monto_cop' => '6112000.00',
        ]);
        ValoracionInversion::create([
            'usuario_id' => $inv->usuario_id, 'inversion_id' => $inv->id, 'fecha' => '2026-11-24', 'valor' => '6525000.00', 'origen' => 'MANUAL',
        ]);

        $corte = CarbonImmutable::parse('2026-11-24');
        $detalle = RentabilidadService::indicadores($inv, $corte);
        $portafolio = RentabilidadService::portafolio($corte);

        $this->assertSame('6.76', $detalle['roi']);
        $this->assertSame('6.76', $portafolio['detalle'][0]['roi']);
        $this->assertSame('6.76', $portafolio['roi']);
    }
}
