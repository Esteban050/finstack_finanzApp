<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Usuario;
use App\Services\AlertaCreditoService;
use App\Services\EndeudamientoService;
use App\Services\PresupuestoService;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H09: una sola definicion de "ingreso promedio" (EndeudamientoService::ingresoPromedio, la base de
 * H08) para Presupuestos, Notificaciones, Reportes y Creditos.
 */
class IngresoPromedioUnicoTest extends TestCase
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

    /** @return array<string, string> el valor que cada modulo usa como "ingreso promedio" */
    private function valoresPorModulo(): array
    {
        $mes = CarbonImmutable::parse('2026-11-01');

        return [
            'presupuestos' => PresupuestoService::ingresoBase($this->usuario->id, $mes),
            'presupuestos_base' => PresupuestoService::base($this->usuario->id, $mes)['ingreso_promedio'],
            'notificaciones' => PresupuestoService::ingresoBase($this->usuario->id, $mes),
            'creditos_alertas' => AlertaCreditoService::ingresoMensualPromedio($this->usuario->id),
            'creditos_endeudamiento' => EndeudamientoService::calcular($this->usuario->id, CarbonImmutable::parse('2026-11-24'))['ingresos_netos']['promedio'],
            'reportes' => EndeudamientoService::calcular($this->usuario->id, CarbonImmutable::parse('2026-11-30'))['ingresos_netos']['promedio'],
        ];
    }

    public function test_meses_cerrados_insuficientes_todos_usan_el_ingreso_del_mes_en_curso(): void
    {
        $this->ingreso('2026-10-24', '43137.98');       // un unico mes cerrado con ingresos
        $this->ingreso('2026-11-20', '13536417.04');

        foreach ($this->valoresPorModulo() as $modulo => $valor) {
            $this->assertSame('13536417', $valor, "modulo {$modulo}");
        }
    }

    public function test_con_meses_cerrados_suficientes_todos_usan_el_mismo_promedio(): void
    {
        $this->ingreso('2026-08-10', '3000000');
        $this->ingreso('2026-09-10', '3000000');
        $this->ingreso('2026-11-20', '9000000');

        foreach ($this->valoresPorModulo() as $modulo => $valor) {
            $this->assertSame('3000000', $valor, "modulo {$modulo}");
        }
    }

    public function test_sin_ingresos_todos_dan_cero(): void
    {
        foreach ($this->valoresPorModulo() as $modulo => $valor) {
            $this->assertSame('0', $valor, "modulo {$modulo}");
        }
    }
}
