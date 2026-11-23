<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * Ingresos en el servidor: aviso sin fuentes activas, obligatorios (RN-021.1),
 * USD con TRM por defecto / tasa manual / sin TRM (CA-017.1, RN-021.3) y
 * retención frente al bruto.
 */
class IngresosServidorTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal();
        // Sin red: la TRM sale solo de la tabla local; la fuente oficial "no responde".
        Http::fake(['*' => Http::response([], 200)]);
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function registrar(array $datos)
    {
        return $this->postJson(route('ingresos.store'), $datos);
    }

    public function test_sin_fuentes_activas_pide_crear_una(): void
    {
        $this->get(route('ingresos.create'))->assertOk()->assertSee('Crea una fuente de ingreso para continuar');

        $this->crearFuente($this->usuario, activa: false);
        $this->get(route('ingresos.create'))->assertOk()->assertSee('Crea una fuente de ingreso para continuar');

        $this->crearFuente($this->usuario);
        $this->get(route('ingresos.create'))->assertOk()->assertDontSee('Crea una fuente de ingreso para continuar');
    }

    public function test_obligatorios_en_el_servidor(): void
    {
        $this->registrar(['moneda' => 'COP'])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'fuente_id' => 'Seleccione la fuente de ingreso.',
                'fecha_recepcion' => 'Indique la fecha en que recibió el ingreso.',
                'monto_original' => 'Indique el monto del ingreso.',
            ]);
    }

    public function test_usd_con_trm_por_defecto_tasa_manual_y_sin_trm(): void
    {
        $fuente = $this->crearFuente($this->usuario);
        $this->assertFalse(DB::table('tasas_cambio')->where('moneda', 'USD')->where('vigencia_desde', '2025-03-05')->exists());
        DB::table('tasas_cambio')->insert(['moneda' => 'USD', 'valor' => '4100.0000', 'vigencia_desde' => '2025-03-05',
            'vigencia_hasta' => '2025-03-05', 'fuente' => 'MANUAL', 'consultado_en' => now()]);
        $usd = fn (array $extra) => $this->registrar(array_merge(['fuente_id' => $fuente, 'monto_original' => '1000', 'moneda' => 'USD'], $extra));

        // Sin tasa: usa la TRM de la fecha.
        $id = $usd(['fecha_recepcion' => '2025-03-05'])->assertCreated()->json('data.id');
        $this->assertSame(['4100.0000', '4100000.00'], array_values((array) DB::table('ingresos')->where('id', $id)->first(['tasa_cambio_usada', 'monto_bruto_cop'])));

        // Tasa manual: se respeta.
        $id = $usd(['fecha_recepcion' => '2025-03-05', 'tasa_cambio_usada' => '4200'])->assertCreated()->json('data.id');
        $this->assertSame('4200000.00', (string) DB::table('ingresos')->where('id', $id)->value('monto_bruto_cop'));

        // Sin TRM para la fecha y sin tasa: exige la tasa manual.
        $usd(['fecha_recepcion' => '1991-01-15'])->assertStatus(422)
            ->assertJsonValidationErrors(['tasa_cambio_usada' => 'No hay TRM disponible para el 1991-01-15. Indique la tasa de cambio manualmente.']);
    }

    public function test_retencion_frente_al_bruto(): void
    {
        $fuente = $this->crearFuente($this->usuario);
        $base = ['fuente_id' => $fuente, 'fecha_recepcion' => '2026-11-20', 'monto_original' => '100000', 'moneda' => 'COP'];

        $id = $this->registrar($base + ['retencion_fuente' => '100000'])->assertCreated()->json('data.id');
        $this->assertSame('0.00', (string) DB::table('ingresos')->where('id', $id)->value('monto_neto_cop'));

        $this->registrar($base + ['retencion_fuente' => '100001'])->assertStatus(422)
            ->assertJsonValidationErrors(['retencion_fuente' => 'La retención no puede superar el monto bruto.']);
        $this->registrar($base + ['retencion_fuente' => '60000', 'aportes_descontados' => '40001'])->assertStatus(422)
            ->assertJsonValidationErrors(['aportes_descontados' => 'La suma de la retención y los aportes no puede superar el monto bruto.']);
    }
}
