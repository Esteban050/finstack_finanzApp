<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Usuario;
use App\Services\Reportes\IndicadoresSaludService;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H08 (decisión: usar el mes en curso): si los meses cerrados no tienen ingresos suficientes
 * (menos de 2 de los últimos 3 con ingresos, o promedio 0), el denominador del endeudamiento es
 * el ingreso neto del mes en curso. Créditos y Reportes muestran qué base se usó.
 */
class EndeudamientoBaseIngresoTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    private string $fuente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $this->fuente = $this->crearFuente($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function ingreso(string $fecha, string $bruto): void
    {
        (new Ingreso)->forceFill([
            'usuario_id' => $this->usuario->id, 'fuente_id' => $this->fuente, 'fecha_recepcion' => $fecha,
            'clasificacion_tributaria' => 'RENTAS_TRABAJO', 'moneda' => 'COP', 'monto_original' => $bruto,
            'tasa_cambio_usada' => '1.0000', 'monto_bruto_cop' => $bruto, 'retencion_fuente_cop' => '0.00',
            'aportes_descontados_cop' => '0.00', 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
    }

    /** Una cuota sin intereses de $ 206.199 que vence el 20/11/2026. */
    private function cuotaDeNoviembre(): void
    {
        $this->postJson('/creditos', [
            'tipo_credito' => 'LIBRE_INVERSION', 'banco' => 'Banco QA', 'proposito' => 'Cuota de noviembre',
            'monto' => '206199', 'tasa' => '0', 'tipo_tasa' => 'EA', 'numero_cuotas' => 1,
            'periodicidad' => 'MENSUAL', 'fecha_inicio' => '2026-10-20',
        ])->assertCreated();
    }

    private function endeudamiento(): array
    {
        return $this->getJson('/creditos/endeudamiento')->assertOk()->json('data');
    }

    public function test_con_un_solo_mes_cerrado_con_ingresos_usa_el_ingreso_del_mes_en_curso(): void
    {
        $this->ingreso('2026-10-24', '43137.98');
        $this->ingreso('2026-11-10', '13536417.04');
        $this->cuotaDeNoviembre();

        $data = $this->endeudamiento();

        $this->assertSame('MES_EN_CURSO', $data['ingresos_netos']['base']);
        $this->assertSame('13536417', $data['ingresos_netos']['promedio']);
        $this->assertSame('1.52', $data['indicador_porc']);
    }

    public function test_sin_ingresos_en_meses_cerrados_usa_el_mes_en_curso(): void
    {
        $this->ingreso('2026-11-10', '13536417.04');
        $this->cuotaDeNoviembre();

        $data = $this->endeudamiento();

        $this->assertSame('MES_EN_CURSO', $data['ingresos_netos']['base']);
        $this->assertSame('1.52', $data['indicador_porc']);
    }

    public function test_con_dos_meses_cerrados_con_ingresos_mantiene_el_promedio_de_meses_cerrados(): void
    {
        $this->ingreso('2026-09-10', '4000000.00');
        $this->ingreso('2026-10-10', '4000000.00');
        $this->ingreso('2026-11-10', '13536417.04');
        $this->cuotaDeNoviembre();

        $data = $this->endeudamiento();

        $this->assertSame('MESES_CERRADOS', $data['ingresos_netos']['base']);
        $this->assertSame('4000000', $data['ingresos_netos']['promedio']);
        $this->assertSame('5.15', $data['indicador_porc']);
    }

    public function test_sin_ingreso_en_el_mes_en_curso_conserva_los_meses_cerrados(): void
    {
        $this->ingreso('2026-10-24', '43137.98');
        $this->cuotaDeNoviembre();

        $data = $this->endeudamiento();

        $this->assertSame('MESES_CERRADOS', $data['ingresos_netos']['base']);
        $this->assertSame('43138', $data['ingresos_netos']['promedio']);
    }

    public function test_reportes_muestra_la_base_usada(): void
    {
        $this->ingreso('2026-10-24', '43137.98');
        $this->ingreso('2026-11-10', '13536417.04');
        $this->cuotaDeNoviembre();

        $resultado = app(IndicadoresSaludService::class)
            ->calcularIndicadores($this->usuario, '2026-11', CarbonImmutable::parse('2026-11-24'));
        $endeudamiento = collect($resultado['indicadores'])->firstWhere('id', 'endeudamiento');

        $this->assertSame('1.52', (string) $endeudamiento['valor']);
        $variables = $endeudamiento['detalle']['variables'];
        $this->assertSame('Mes en curso', $variables['Base del ingreso']);
        $this->assertSame('$ 13.536.417', $variables['Ingreso neto usado']);
    }
}
