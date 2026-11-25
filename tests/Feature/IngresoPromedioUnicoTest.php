<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Notificacion;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\Usuario;
use App\Services\AlertaCreditoService;
use App\Services\AlertaService;
use App\Services\EndeudamientoService;
use App\Services\PresupuestoService;
use App\Services\Reportes\IndicadoresSaludService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
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

    private function gasto(string $fecha, string $monto): void
    {
        $padre = new Categoria;
        $padre->forceFill(['usuario_id' => $this->usuario->id, 'tipo' => 'GASTO', 'nombre' => 'P '.Str::random(8),
            'es_predeterminada' => false, 'activa' => true, 'creado_en' => now()])->save();
        $sub = new Categoria;
        $sub->forceFill(['usuario_id' => $this->usuario->id, 'categoria_padre_id' => $padre->id, 'tipo' => 'GASTO',
            'nombre' => 'S '.Str::random(8), 'naturaleza_sugerida' => 'VARIABLE', 'necesidad_sugerida' => 'DISCRECIONAL',
            'es_predeterminada' => false, 'activa' => true, 'creado_en' => now()])->save();
        (new Gasto)->forceFill([
            'usuario_id' => $this->usuario->id, 'categoria_id' => $padre->id, 'subcategoria_id' => $sub->id, 'fecha' => $fecha,
            'moneda' => 'COP', 'monto_original' => $monto, 'tasa_cambio_usada' => '1.0000', 'monto_cop' => $monto,
            'naturaleza' => 'VARIABLE', 'necesidad' => 'DISCRECIONAL', 'medio_pago' => 'EFECTIVO',
            'relevancia_tributaria' => 'NO_DEDUCIBLE', 'etiquetas' => [], 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
    }

    /** Ingreso promedio que la alerta real GASTO_PROYECTADO_ALTO guarda en su notificacion (null si no salto). */
    private function ingresoDeLaAlerta(): ?string
    {
        AlertaService::evaluarUsuario($this->usuario->id, CarbonImmutable::parse('2026-11-24'), AlertaService::DIARIO);
        $n = Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $this->usuario->id)->where('tipo', 'GASTO_PROYECTADO_ALTO')->first();

        return $n === null ? null : (string) $n->datos['ingreso_promedio'];
    }

    /** Ingreso neto usado que muestra el indicador de Endeudamiento de Reportes, en pesos. */
    private function ingresoDeReportes(): string
    {
        $r = app(IndicadoresSaludService::class)->calcularIndicadores($this->usuario, '2026-11', CarbonImmutable::parse('2026-11-24'));
        $texto = collect($r['indicadores'])->firstWhere('id', 'endeudamiento')['detalle']['variables']['Ingreso neto usado'];

        return preg_replace('/\D/', '', $texto);
    }

    /** @return array<string, string> el valor que cada modulo usa como "ingreso promedio" */
    private function valoresPorModulo(): array
    {
        $mes = CarbonImmutable::parse('2026-11-01');

        return [
            'presupuestos' => PresupuestoService::ingresoBase($this->usuario->id, $mes),
            'presupuestos_base' => PresupuestoService::base($this->usuario->id, $mes)['ingreso_promedio'],
            'creditos_alertas' => AlertaCreditoService::ingresoMensualPromedio($this->usuario->id),
            'creditos_endeudamiento' => EndeudamientoService::calcular($this->usuario->id, CarbonImmutable::parse('2026-11-24'))['ingresos_netos']['promedio'],
            'reportes' => $this->ingresoDeReportes(),
        ];
    }

    public function test_meses_cerrados_insuficientes_todos_usan_el_ingreso_del_mes_en_curso(): void
    {
        $this->ingreso('2026-10-24', '43137.98');       // un unico mes cerrado con ingresos
        $this->ingreso('2026-11-20', '13536417.04');
        $this->gasto('2026-11-02', '12000000');   // proyeccion lineal 15M >= 90 % del ingreso

        foreach ($this->valoresPorModulo() as $modulo => $valor) {
            $this->assertSame('13536417', $valor, "modulo {$modulo}");
        }
        $this->assertSame('13536417', $this->ingresoDeLaAlerta(), 'alerta GASTO_PROYECTADO_ALTO');
    }

    public function test_con_meses_cerrados_suficientes_todos_usan_el_mismo_promedio(): void
    {
        $this->ingreso('2026-08-10', '3000000');
        $this->ingreso('2026-09-10', '3000000');
        $this->ingreso('2026-11-20', '9000000');
        $this->gasto('2026-11-02', '3000000');   // proyeccion lineal 3,75M >= 90 % de 3M

        foreach ($this->valoresPorModulo() as $modulo => $valor) {
            $this->assertSame('3000000', $valor, "modulo {$modulo}");
        }
        $this->assertSame('3000000', $this->ingresoDeLaAlerta(), 'alerta GASTO_PROYECTADO_ALTO');
    }

    public function test_sin_ingresos_todos_dan_cero(): void
    {
        foreach ($this->valoresPorModulo() as $modulo => $valor) {
            $this->assertSame('0', $valor, "modulo {$modulo}");
        }
        $this->gasto('2026-11-02', '3000000');
        $this->assertNull($this->ingresoDeLaAlerta(), 'sin ingresos no hay porcentaje ni alerta');
    }
}
