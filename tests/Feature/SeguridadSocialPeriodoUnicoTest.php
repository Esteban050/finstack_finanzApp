<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Usuario;
use App\Services\SeguridadSocialService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H24: una sola regla para el periodo de PILA: el periodo M se cotiza con los ingresos confirmados de M-1 (mes
 * vencido), se aceptan periodos hasta el mes siguiente al actual (diciembre 2026 y no enero 2027 el 24/11/2026)
 * y todos los puntos de entrada responden con el mismo mensaje.
 */
class SeguridadSocialPeriodoUnicoTest extends TestCase
{
    use UsaBaseLocal;

    private const MENSAJE = 'El periodo no puede ser posterior a 2026-12 (el mes siguiente al actual).';

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $this->ingresoDeTrabajo('2026-11-10', '5000000');
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function ingresoDeTrabajo(string $fecha, string $bruto): void
    {
        (new Ingreso)->forceFill([
            'usuario_id' => $this->usuario->id, 'fuente_id' => $this->crearFuente($this->usuario), 'fecha_recepcion' => $fecha,
            'clasificacion_tributaria' => 'RENTAS_TRABAJO', 'moneda' => 'COP', 'monto_original' => $bruto,
            'tasa_cambio_usada' => '1.0000', 'monto_bruto_cop' => $bruto, 'retencion_fuente_cop' => '0.00',
            'aportes_descontados_cop' => '0.00', 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
    }

    public function test_el_periodo_m_usa_los_ingresos_confirmados_de_m_menos_1_en_todos_los_caminos(): void
    {
        // Selector del formulario (ingresos-mes): diciembre toma noviembre.
        $r = $this->getJson(route('seguridad-social.ingresos-mes', ['tipo_cotizante' => 'INDEPENDIENTE', 'periodo' => '2026-12']))->assertOk();
        $this->assertSame('2026-11', $r->json('data.mes_ingresos'));
        $this->assertSame('5000000.00', $r->json('data.total'));

        // Consulta del pago PILA: mismo mes de ingresos.
        $r = $this->getJson(route('seguridad-social.pagos.ingresos-periodo', ['periodo' => '2026-12']))->assertOk();
        $this->assertTrue($r->json('data.tiene_ingresos'));
        $this->assertSame('5000000.00', $r->json('data.total_ingresos'));

        // Liquidacion estimada: la base es el ingreso de noviembre.
        $r = $this->postJson(route('seguridad-social.liquidaciones.store'), ['periodo' => '2026-12'])->assertSuccessful();
        $this->assertSame('5000000.00', (string) $r->json('data.ingreso_base'));
    }

    public function test_octubre_sin_ingresos_de_septiembre_explica_el_mes_que_se_uso(): void
    {
        $r = $this->getJson(route('seguridad-social.pagos.ingresos-periodo', ['periodo' => '2026-10']))->assertOk();

        $this->assertFalse($r->json('data.tiene_ingresos'));
        $this->assertStringContainsString('2026-09', (string) $r->json('mensaje'));
        $this->assertStringContainsString('mes vencido', (string) $r->json('mensaje'));
    }

    public function test_se_aceptan_periodos_hasta_el_mes_siguiente_y_se_rechaza_el_siguiente_con_un_solo_mensaje(): void
    {
        $this->assertSame('2026-12', SeguridadSocialService::periodoMaximo()->format('Y-m'));

        $this->getJson(route('seguridad-social.ingresos-mes', ['tipo_cotizante' => 'INDEPENDIENTE', 'periodo' => '2026-12']))->assertOk();
        $this->postJson(route('seguridad-social.calcular'), ['tipo_cotizante' => 'INDEPENDIENTE', 'monto_contrato' => '5000000', 'periodo' => '2026-12'])->assertOk();

        $rechazos = [
            $this->getJson(route('seguridad-social.ingresos-mes', ['tipo_cotizante' => 'INDEPENDIENTE', 'periodo' => '2027-01'])),
            $this->postJson(route('seguridad-social.calcular'), ['tipo_cotizante' => 'INDEPENDIENTE', 'monto_contrato' => '5000000', 'periodo' => '2027-01']),
            $this->postJson(route('seguridad-social.liquidaciones.store'), ['periodo' => '2027-01']),
            $this->postJson(route('seguridad-social.liquidaciones.simular'), ['periodo' => '2027-01', 'ingreso_base' => '5000000']),
            $this->getJson(route('seguridad-social.pagos.ingresos-periodo', ['periodo' => '2027-01'])),
        ];

        foreach ($rechazos as $i => $respuesta) {
            $respuesta->assertStatus(422);
            $this->assertSame(self::MENSAJE, $respuesta->json('errors.periodo.0'), "camino {$i}");
        }

        $pago = $this->postJson(route('seguridad-social.pagos.store'), [
            'periodo' => '2027-01', 'fecha_pago' => '2026-11-24', 'salud' => '1', 'pension' => '1',
        ]);
        $pago->assertStatus(422);
        $this->assertSame(self::MENSAJE, $pago->json('errors.periodo.0'));
    }
}
