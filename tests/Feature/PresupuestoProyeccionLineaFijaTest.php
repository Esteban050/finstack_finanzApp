<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Ingreso;
use App\Models\Notificacion;
use App\Models\PlantillaRecurrencia;
use App\Models\Presupuesto;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\Usuario;
use App\Services\AlertaService;
use App\Services\PresupuestoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H21: una sola regla para las lineas fijas sin gasto: proyectan su monto asignado, tengan o no una
 * recurrencia pendiente. Una linea variable sin gasto sigue proyectando 0.
 */
class PresupuestoProyeccionLineaFijaTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    private Categoria $padre;

    private ?string $fuente = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $this->padre = $this->categoria(null, null, 'Obligaciones');
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function categoria(?Categoria $padre, ?string $naturaleza, string $nombre): Categoria
    {
        $c = new Categoria;
        $c->forceFill([
            'usuario_id' => $this->usuario->id, 'categoria_padre_id' => $padre?->id, 'tipo' => 'GASTO',
            'nombre' => $nombre.' '.Str::random(5), 'naturaleza_sugerida' => $naturaleza,
            'necesidad_sugerida' => $padre === null ? null : 'ESENCIAL', 'es_predeterminada' => false,
            'activa' => true, 'creado_en' => now(),
        ])->save();

        return $c;
    }

    private function lineaPorCategoria(string $categoriaId): array
    {
        return collect($this->getJson('/presupuestos')->assertOk()->json('data.lineas'))
            ->firstWhere('categoria_id', $categoriaId);
    }

    public function test_lineas_fijas_sin_gasto_proyectan_su_asignado_y_las_variables_cero(): void
    {
        $cuota = $this->categoria($this->padre, 'FIJO', 'Cuota de credito');
        $internet = $this->categoria($this->padre, 'FIJO', 'Internet Hogar');
        $mercado = $this->categoria($this->padre, 'VARIABLE', 'Mercado');

        // Internet Hogar tiene su recurrente del 24/11 pendiente por 110.000.
        PlantillaRecurrencia::create([
            'usuario_id' => $this->usuario->id, 'tipo_movimiento' => 'GASTO', 'periodicidad' => 'MENSUAL',
            'fecha_inicio' => '2026-11-24', 'modo_confirmacion' => 'AUTOMATICO', 'es_monto_estimado' => false,
            'proxima_ejecucion' => '2026-11-24', 'activa' => true,
            'datos_plantilla' => [
                'categoria_id' => $this->padre->id, 'subcategoria_id' => $internet->id, 'descripcion' => 'Claro Hogar',
                'moneda' => 'COP', 'monto_original' => '110000.00', 'monto_cop' => '110000.00',
            ],
        ]);

        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false, 'trasladar_saldo' => false,
            'lineas' => [
                ['categoria_id' => $cuota->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '500000'],
                ['categoria_id' => $internet->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '110000'],
                ['categoria_id' => $mercado->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '800000'],
            ],
        ])->assertSuccessful();

        $this->assertSame('500000', $this->lineaPorCategoria($cuota->id)['proyeccion'], 'cuota de credito (fija, sin gasto)');
        $this->assertSame('110000', $this->lineaPorCategoria($internet->id)['pendientes_recurrentes'], 'la recurrencia esta pendiente');
        $this->assertSame('110000', $this->lineaPorCategoria($internet->id)['proyeccion'], 'internet (fija, recurrencia pendiente)');
        $this->assertSame('0', $this->lineaPorCategoria($mercado->id)['proyeccion'], 'variable sin gasto');
    }

    private function ingreso(string $fecha, string $bruto): void
    {
        $fuente = $this->fuente ??= $this->crearFuente($this->usuario);
        (new Ingreso)->forceFill([
            'usuario_id' => $this->usuario->id, 'fuente_id' => $fuente, 'fecha_recepcion' => $fecha,
            'clasificacion_tributaria' => 'RENTAS_TRABAJO', 'moneda' => 'COP', 'monto_original' => $bruto,
            'tasa_cambio_usada' => '1.0000', 'monto_bruto_cop' => $bruto, 'retencion_fuente_cop' => '0.00',
            'aportes_descontados_cop' => '0.00', 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
    }

    private function plantillaPendiente(Categoria $sub, string $monto): void
    {
        PlantillaRecurrencia::create([
            'usuario_id' => $this->usuario->id, 'tipo_movimiento' => 'GASTO', 'periodicidad' => 'MENSUAL',
            'fecha_inicio' => '2026-11-24', 'modo_confirmacion' => 'AUTOMATICO', 'es_monto_estimado' => false,
            'proxima_ejecucion' => '2026-11-24', 'activa' => true,
            'datos_plantilla' => [
                'categoria_id' => $this->padre->id, 'subcategoria_id' => $sub->id, 'descripcion' => 'Recurrente',
                'moneda' => 'COP', 'monto_original' => $monto, 'monto_cop' => $monto,
            ],
        ]);
    }

    /** @return array<int, string> tipos de notificacion del usuario tras evaluar las alertas el 24/11 */
    private function alertasEmitidas(): array
    {
        AlertaService::evaluarUsuario($this->usuario->id, CarbonImmutable::parse('2026-11-24'), AlertaService::DIARIO);

        return Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $this->usuario->id)->pluck('tipo')->all();
    }

    public function test_t12b_linea_fija_proyectada_en_su_asignado_no_dispara_alertas_de_proyeccion(): void
    {
        $this->ingreso('2026-11-20', '1000000');
        $cuota = $this->categoria($this->padre, 'FIJO', 'Cuota de credito');
        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false, 'trasladar_saldo' => false,
            'lineas' => [['categoria_id' => $cuota->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '900000']],
        ])->assertSuccessful();

        $linea = $this->lineaPorCategoria($cuota->id);
        $this->assertSame('900000', $linea['proyeccion']);
        $this->assertSame('100.00', (string) $linea['proyeccion_porc']);

        $tipos = $this->alertasEmitidas();
        $this->assertNotContains('PROYECCION_LINEA', $tipos);
        $this->assertNotContains('GASTO_PROYECTADO_ALTO', $tipos, 'la proyeccion global no usa el asignado de las lineas fijas');
    }

    public function test_t12b_recurrente_pendiente_mayor_que_el_asignado_si_alerta(): void
    {
        $this->ingreso('2026-11-20', '1000000');
        $internet = $this->categoria($this->padre, 'FIJO', 'Internet');
        $this->plantillaPendiente($internet, '150000');
        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false, 'trasladar_saldo' => false,
            'lineas' => [['categoria_id' => $internet->id, 'modo_asignacion' => 'PESOS', 'monto_asignado' => '100000']],
        ])->assertSuccessful();

        $this->assertSame('150000', $this->lineaPorCategoria($internet->id)['proyeccion']);
        $this->assertContains('PROYECCION_LINEA', $this->alertasEmitidas());
    }

    public function test_t12b_linea_fija_en_porcentaje_proyecta_su_monto_y_no_alerta(): void
    {
        $this->ingreso('2026-11-20', '10000000');
        $cuota = $this->categoria($this->padre, 'FIJO', 'Cuota');
        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false, 'trasladar_saldo' => false,
            'lineas' => [['categoria_id' => $cuota->id, 'modo_asignacion' => 'PORC_INGRESO', 'porcentaje_ingreso' => '5']],
        ])->assertSuccessful();

        $linea = $this->lineaPorCategoria($cuota->id);
        $this->assertSame('500000', $linea['monto_asignado']);
        $this->assertSame('500000', $linea['proyeccion']);
        $this->assertNotContains('PROYECCION_LINEA', $this->alertasEmitidas());
    }

    /**
     * Comportamiento elegido (RN-055.4): la base del ingreso (que usa el mes en curso si no hay 3 meses
     * cerrados) se fija al crear/empezar el mes; los montos de las lineas en % no se mueven despues.
     */
    public function test_t12b_los_montos_en_porcentaje_no_se_mueven_a_mitad_de_mes(): void
    {
        $this->ingreso('2026-11-10', '10000000');
        $cuota = $this->categoria($this->padre, 'FIJO', 'Cuota');
        $this->putJson('/presupuestos/2026-11', [
            'repetir_cada_mes' => false, 'trasladar_saldo' => false,
            'lineas' => [['categoria_id' => $cuota->id, 'modo_asignacion' => 'PORC_INGRESO', 'porcentaje_ingreso' => '5']],
        ])->assertSuccessful();
        $this->assertSame('500000', $this->lineaPorCategoria($cuota->id)['monto_asignado']);

        $this->ingreso('2026-11-22', '5000000');   // entra mas ingreso del mes en curso
        PresupuestoService::asegurarMes($this->usuario->id, CarbonImmutable::parse('2026-11-24'));

        $this->assertSame('500000', $this->lineaPorCategoria($cuota->id)['monto_asignado']);
        $this->assertSame('10000000.00', (string) Presupuesto::where('usuario_id', $this->usuario->id)->first()->ingreso_base);
    }
}
