<?php

namespace Tests\Feature;

use App\Models\Inversion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * N02 (T7a): la migración de datos solo baja las valoraciones que el sistema infló al registrar un abono
 * de intereses (misma fecha del abono, o fechas posteriores que ya existían cuando se registró), y no toca
 * los valores que el usuario digitó después.
 */
class MigracionCorregirValoracionCdtTest extends TestCase
{
    use UsaBaseLocal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-25 10:00:00');
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function cdt(string $estado = 'ACTIVA'): Inversion
    {
        $u = $this->crearUsuario();

        return Inversion::create([
            'usuario_id' => $u->id, 'nombre' => 'CDT', 'tipo' => 'CDT', 'moneda' => 'COP',
            'fecha_apertura' => '2026-09-24', 'monto_inicial' => '5000000.00', 'estado' => $estado,
            'liquidada_en' => $estado === 'LIQUIDADA' ? '2026-11-20' : null,
        ]);
    }

    private function abono(Inversion $i, string $fecha, string $monto, string $creado): void
    {
        DB::table('movimientos_inversion')->insert([
            'id' => (string) Str::uuid(), 'usuario_id' => $i->usuario_id, 'inversion_id' => $i->id, 'tipo' => 'ABONO_INTERES',
            'fecha' => $fecha, 'monto' => $monto, 'monto_cop' => $monto, 'tasa_cambio_usada' => '1.0000',
            'creado_en' => $creado, 'actualizado_en' => $creado,
        ]);
    }

    private function valoracion(Inversion $i, string $fecha, string $valor, string $creado, ?string $actualizado = null): string
    {
        $id = (string) Str::uuid();
        DB::table('valoraciones_inversion')->insert([
            'id' => $id, 'usuario_id' => $i->usuario_id, 'inversion_id' => $i->id, 'fecha' => $fecha, 'valor' => $valor,
            'origen' => 'MANUAL', 'creado_en' => $creado, 'actualizado_en' => $actualizado ?? $creado,
        ]);

        return $id;
    }

    private function valor(string $id): string
    {
        return (string) DB::table('valoraciones_inversion')->where('id', $id)->value('valor');
    }

    private function migrar(): void
    {
        (require base_path('database/migrations/2026_11_25_000002_corregir_valoracion_cdt_con_abono_interes.php'))->up();
    }

    public function test_baja_la_valoracion_inflada_del_dia_del_abono_y_la_anterior_propagada(): void
    {
        $i = $this->cdt();
        $previa = $this->valoracion($i, '2026-11-20', '5043137.98', '2026-11-01 08:00:00-05');   // existía al registrar el abono
        $this->abono($i, '2026-10-24', '43137.98', '2026-11-10 09:00:00-05');
        $mismoDia = $this->valoracion($i, '2026-10-24', '5043137.98', '2026-11-10 09:00:00-05');

        $this->migrar();

        $this->assertSame('5000000.00', $this->valor($mismoDia));
        $this->assertSame('5000000.00', $this->valor($previa));
    }

    public function test_respeta_el_valor_digitado_por_el_usuario_despues_del_abono(): void
    {
        $i = $this->cdt();
        $this->abono($i, '2026-10-24', '43137.98', '2026-11-10 09:00:00-05');
        $digitada = $this->valoracion($i, '2026-11-20', '5043000.00', '2026-11-21 09:00:00-05');
        $mismoDiaEditada = $this->valoracion($i, '2026-10-24', '5010000.00', '2026-11-10 09:00:00-05', '2026-11-22 09:00:00-05');

        $this->migrar();

        $this->assertSame('5043000.00', $this->valor($digitada));
        $this->assertSame('5010000.00', $this->valor($mismoDiaEditada));
    }

    public function test_omite_la_fila_que_quedaria_negativa(): void
    {
        $i = $this->cdt();
        $this->abono($i, '2026-10-24', '43137.98', '2026-11-10 09:00:00-05');
        $pequena = $this->valoracion($i, '2026-10-24', '1000.00', '2026-11-10 09:00:00-05');

        $this->migrar();

        $this->assertSame('1000.00', $this->valor($pequena));
    }

    public function test_no_toca_un_cdt_liquidado(): void
    {
        $i = $this->cdt('LIQUIDADA');
        $this->abono($i, '2026-10-24', '43137.98', '2026-11-10 09:00:00-05');
        $v = $this->valoracion($i, '2026-10-24', '5043137.98', '2026-11-10 09:00:00-05');

        $this->migrar();

        $this->assertSame('5043137.98', $this->valor($v));
    }

    public function test_suma_los_abonos_con_precision_decimal(): void
    {
        $i = $this->cdt();
        $this->abono($i, '2026-10-01', '0.10', '2026-11-10 09:00:00-05');
        $this->abono($i, '2026-10-02', '0.20', '2026-11-10 09:00:01-05');
        $v = $this->valoracion($i, '2026-11-20', '5000000.30', '2026-11-01 08:00:00-05');

        $this->migrar();

        $this->assertSame('5000000.00', $this->valor($v));
    }
}
