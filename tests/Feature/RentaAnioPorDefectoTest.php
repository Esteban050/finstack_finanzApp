<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Usuario;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H27: Renta abre por defecto en el ano gravable en curso (el que tiene datos), no en 2025 con todo en cero.
 */
class RentaAnioPorDefectoTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function ingreso(string $fecha): void
    {
        (new Ingreso)->forceFill([
            'usuario_id' => $this->usuario->id, 'fuente_id' => $this->crearFuente($this->usuario), 'fecha_recepcion' => $fecha,
            'clasificacion_tributaria' => 'RENTAS_TRABAJO', 'moneda' => 'COP', 'monto_original' => '1000000',
            'tasa_cambio_usada' => '1.0000', 'monto_bruto_cop' => '1000000', 'retencion_fuente_cop' => '0.00',
            'aportes_descontados_cop' => '0.00', 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ])->save();
    }

    public function test_abre_en_el_ano_en_curso_si_tiene_datos(): void
    {
        $this->ingreso('2026-11-03');

        $this->get('/renta')->assertRedirect(route('renta.consolidado', ['anio' => 2026]));
    }

    public function test_sin_datos_en_el_ano_en_curso_usa_el_ultimo_ano_con_datos(): void
    {
        $this->ingreso('2025-05-03');

        $this->get('/renta')->assertRedirect(route('renta.consolidado', ['anio' => 2025]));
    }

    public function test_sin_ningun_dato_usa_el_ano_en_curso_y_el_parametro_anio_manda(): void
    {
        $this->get('/renta')->assertRedirect(route('renta.consolidado', ['anio' => 2026]));
        $this->get('/renta?anio=2024')->assertRedirect(route('renta.consolidado', ['anio' => 2024]));
        $this->get('/renta/calculadora')->assertRedirect(route('renta.calculadora', ['anio' => 2026]));
    }

    public function test_la_calculadora_aclara_que_la_estimacion_usa_los_registros_del_usuario(): void
    {
        $this->get(route('renta.calculadora', ['anio' => 2026]))
            ->assertOk()
            ->assertSee('no cambia con los valores que simulas');
    }
}
