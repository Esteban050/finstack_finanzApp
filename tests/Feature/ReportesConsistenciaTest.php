<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * MOD-11 — Reportes: las dos pruebas de código que pide el Mapa de pruebas.
 *
 * - Resumen = listado con el mismo filtro (CA-065.1): los totales del
 *   resumen son los del listado de gastos e ingresos en el mismo rango.
 * - Exportación = pantalla (RF-069): cada XLSX trae los totales que la
 *   pantalla muestra para el mismo periodo.
 *
 * Hoy es el 23/11/2026. Los datos incluyen bordes de mes, un pendiente, un
 * eliminado y movimientos de otro usuario, que ningún total debe contar.
 */
class ReportesConsistenciaTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    private Categoria $vivienda;

    private Categoria $mercado;

    private string $fuente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iniciarBaseLocal('2026-11-23 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);

        $this->vivienda = $this->categoriaPropiaDe($this->usuario, padre: $this->categoriaPropiaDe($this->usuario));
        $this->mercado = $this->categoriaPropiaDe($this->usuario, padre: $this->categoriaPropiaDe($this->usuario));
        $this->fuente = $this->crearFuente($this->usuario);

        $this->movimientos();
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Resumen = listado con el mismo filtro (CA-065.1)
    |--------------------------------------------------------------------------
    */

    /** Gastos: el total y la cantidad del resumen son los del listado, en cada periodo. */
    public function test_resumen_igual_al_listado_de_gastos_en_cada_periodo(): void
    {
        foreach ($this->periodos() as $nombre => [$consulta, $desde, $hasta, $esperado, $cantidad]) {
            $resumen = $this->getJson("/resumen?{$consulta}")->assertOk()->json('data');
            $this->assertSame([$desde, $hasta], $this->limites($resumen), "{$nombre}: rango del resumen");

            $listado = $this->getJson("/gastos?fecha_desde={$desde}&fecha_hasta={$hasta}")->assertOk()->json('data.totales');

            $this->assertSame(0, bccomp($resumen['totales']['gastos'], (string) $listado['monto'], 2), "{$nombre}: resumen vs listado");
            $this->assertSame(0, bccomp($resumen['totales']['gastos'], $esperado['gastos'], 2), "{$nombre}: total esperado");
            $this->assertSame($cantidad['gastos'], $resumen['totales']['conteo_gastos'], "{$nombre}: cantidad del resumen");
            $this->assertSame($cantidad['gastos'], (int) $listado['cantidad'], "{$nombre}: cantidad del listado");
            $this->assertSame(0, bccomp($this->kpi($resumen, 'gastos')['valor'], (string) $listado['monto'], 2), "{$nombre}: tarjeta de gastos");
        }
    }

    /** Ingresos: el neto del resumen es el neto del listado, en cada periodo. */
    public function test_resumen_igual_al_listado_de_ingresos_en_cada_periodo(): void
    {
        foreach ($this->periodos() as $nombre => [$consulta, $desde, $hasta, $esperado, $cantidad]) {
            $resumen = $this->getJson("/resumen?{$consulta}")->assertOk()->json('data');
            $listado = $this->getJson("/ingresos?fecha_desde={$desde}&fecha_hasta={$hasta}")->assertOk()->json('data.totales');

            $this->assertSame(0, bccomp($resumen['totales']['ingresos_netos'], (string) $listado['neto'], 2), "{$nombre}: resumen vs listado");
            $this->assertSame(0, bccomp($resumen['totales']['ingresos_netos'], $esperado['ingresos'], 2), "{$nombre}: total esperado");
            $this->assertSame($cantidad['ingresos'], $resumen['totales']['conteo_ingresos'], "{$nombre}: cantidad del resumen");
            $this->assertSame($cantidad['ingresos'], (int) $listado['cantidad'], "{$nombre}: cantidad del listado");
            $this->assertSame(
                0,
                bccomp($resumen['totales']['balance'], bcsub((string) $listado['neto'], $esperado['gastos'], 2), 2),
                "{$nombre}: balance = ingresos − gastos"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Exportación = pantalla (RF-069)
    |--------------------------------------------------------------------------
    */

    /** Flujo de caja: la fila TOTAL GENERAL del XLSX es la de la pantalla. */
    public function test_exportacion_del_flujo_de_caja_igual_a_la_pantalla(): void
    {
        $periodo = 'fecha_desde=2026-10-01&fecha_hasta=2026-11-30&agrupacion=mensual';
        $pantalla = $this->getJson("/reportes/flujo-de-caja?{$periodo}")->assertOk()->json('data.totales');
        $this->assertSame(0, bccomp((string) $pantalla['total_gastos'], '1380000', 2));

        $hoja = $this->hojaExportada("/reportes/flujo-de-caja/exportar/xlsx?{$periodo}");
        $fila = $this->filaCon($hoja, 'TOTAL GENERAL');

        foreach (['D' => 'total_ingresos', 'E' => 'total_gastos', 'F' => 'balance_neto', 'G' => 'flujo_acumulado_final'] as $columna => $clave) {
            $this->assertEqualsWithDelta((float) $pantalla[$clave], $hoja->getCell("{$columna}{$fila}")->getValue(), 0.001, $clave);
        }
    }

    /** Gastos por dimensión: total general y cantidad del XLSX son los de la pantalla. */
    public function test_exportacion_de_gastos_por_dimension_igual_a_la_pantalla(): void
    {
        $periodo = 'dimension_fila=categoria&dimension_columna=medio_pago&fecha_desde=2026-11-01&fecha_hasta=2026-11-30';
        $pantalla = $this->getJson("/reportes/gastos?{$periodo}")->assertOk()->json('data');
        $this->assertSame(0, bccomp((string) $pantalla['total_general'], '1030000', 2));

        $hoja = $this->hojaExportada("/reportes/gastos/exportar/xlsx?{$periodo}");
        $encabezado = $this->filaCon($hoja, $pantalla['parametros']['dimension_fila_nombre']);
        $total = $this->filaCon($hoja, 'TOTAL GENERAL');
        $columnaTotal = $this->columnaCon($hoja, $encabezado, 'Total COP');

        $this->assertEqualsWithDelta((float) $pantalla['total_general'], $hoja->getCell([$columnaTotal, $total])->getValue(), 0.001);
        $this->assertSame($pantalla['conteo_total_gastos'], (int) $hoja->getCell([$columnaTotal + 2, $total])->getValue());

        foreach ($pantalla['totales_columnas'] as $nombre => $monto) {
            $columna = $this->columnaCon($hoja, $encabezado, (string) $nombre);
            $this->assertEqualsWithDelta((float) $monto, $hoja->getCell([$columna, $total])->getValue(), 0.001, "columna {$nombre}");
        }
    }

    /** Comparativo: ingresos, gastos y balance del XLSX son los de la pantalla. */
    public function test_exportacion_del_comparativo_igual_a_la_pantalla(): void
    {
        $periodos = 'desde_a=2026-10-01&hasta_a=2026-10-31&desde_b=2026-11-01&hasta_b=2026-11-30';
        $resumen = $this->getJson("/reportes/comparativo?{$periodos}")->assertOk()->json('data.resumen');
        $hoja = $this->hojaExportada("/reportes/comparativo/exportar/xlsx?{$periodos}");

        foreach (['Ingresos' => 'ingresos', 'Gastos totales' => 'gastos', 'Balance' => 'balance'] as $etiqueta => $clave) {
            $fila = $this->filaCon($hoja, $etiqueta);

            foreach (['B' => 'monto_a', 'C' => 'monto_b', 'D' => 'variacion_pesos'] as $columna => $campo) {
                $this->assertEqualsWithDelta((float) $resumen[$clave][$campo], $hoja->getCell("{$columna}{$fila}")->getValue(), 0.001, "{$etiqueta} {$campo}");
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Datos y apoyo
    |--------------------------------------------------------------------------
    */

    /**
     * Movimientos de septiembre a diciembre. Confirmados del usuario:
     * - Gastos: oct $ 350.000 (2), nov $ 1.030.000 (3), dic $ 80.000 (1).
     * - Ingresos netos: oct $ 1.800.000 (1), nov $ 2.400.000 (2).
     */
    private function movimientos(): void
    {
        $u = $this->usuario;

        $this->gastoDe($u, $this->vivienda, '2026-09-30', '45000.00');
        $this->gastoDe($u, $this->vivienda, '2026-10-01', '150000.00');
        $this->gastoDe($u, $this->mercado, '2026-10-31', '200000.00', medioPago: 'TARJETA_DEBITO');
        $this->gastoDe($u, $this->vivienda, '2026-11-01', '640000.00', medioPago: 'TRANSFERENCIA');
        $this->gastoDe($u, $this->mercado, '2026-11-15', '185000.00');
        $this->gastoDe($u, $this->mercado, '2026-11-30', '205000.00', medioPago: 'TARJETA_DEBITO');
        $this->gastoDe($u, $this->vivienda, '2026-12-05', '80000.00');

        // No cuentan en ningún total.
        $this->gastoDe($u, $this->mercado, '2026-11-20', '999999.00', estado: 'PENDIENTE');
        $this->gastoDe($u, $this->mercado, '2026-11-21', '77777.00')->delete();
        $otro = $this->crearUsuario();
        $this->gastoDe($otro, $this->categoriaPropiaDe($otro, padre: $this->categoriaPropiaDe($otro)), '2026-11-10', '500000.00');
        $this->ingresoDe($otro, $this->crearFuente($otro), '2026-11-10', '9000000.00');

        $this->ingresoDe($u, $this->fuente, '2026-10-05', '2000000.00', retencionCop: '200000.00');
        $this->ingresoDe($u, $this->fuente, '2026-11-01', '1500000.00');
        $this->ingresoDe($u, $this->fuente, '2026-11-30', '1000000.00', aportesCop: '100000.00');
        $this->ingresoDe($u, $this->fuente, '2026-11-25', '700000.00', estado: 'PENDIENTE');
    }

    /**
     * Periodos del resumen con su rango y lo esperado.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: array{gastos: string, ingresos: string}, 4: array{gastos: int, ingresos: int}}>
     */
    private function periodos(): array
    {
        return [
            'mes actual' => ['periodo=mes-actual', '2026-11-01', '2026-11-30', ['gastos' => '1030000', 'ingresos' => '2400000'], ['gastos' => 3, 'ingresos' => 2]],
            'mes anterior' => ['periodo=mes-anterior', '2026-10-01', '2026-10-31', ['gastos' => '350000', 'ingresos' => '1800000'], ['gastos' => 2, 'ingresos' => 1]],
            'trimestre' => ['periodo=trimestre', '2026-10-01', '2026-12-31', ['gastos' => '1460000', 'ingresos' => '4200000'], ['gastos' => 6, 'ingresos' => 3]],
            'personalizado' => ['periodo=personalizado&fecha_desde=2026-10-31&fecha_hasta=2026-11-15', '2026-10-31', '2026-11-15', ['gastos' => '1025000', 'ingresos' => '1500000'], ['gastos' => 3, 'ingresos' => 1]],
        ];
    }

    private function kpi(array $resumen, string $clave): array
    {
        return collect($resumen['kpis'])->firstWhere('clave', $clave)
            ?? $this->fail("El resumen no trae la tarjeta {$clave}.");
    }

    /** @return array{0: string, 1: string} días civiles del periodo del resumen */
    private function limites(array $resumen): array
    {
        // El JSON trae los instantes en UTC; el día civil es el de la zona de la app.
        $dia = fn (string $instante) => CarbonImmutable::parse($instante)->setTimezone(config('app.timezone'))->toDateString();

        return [$dia($resumen['periodo']['desde']), $dia($resumen['periodo']['hasta'])];
    }

    /** Descarga el XLSX y devuelve su primera hoja. */
    private function hojaExportada(string $url): Worksheet
    {
        $respuesta = $this->get($url)->assertOk();
        $archivo = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        file_put_contents($archivo, $respuesta->streamedContent());

        try {
            return IOFactory::load($archivo)->getSheet(0);
        } finally {
            @unlink($archivo);
        }
    }

    /** Primera fila cuya columna A empieza por el texto. */
    private function filaCon(Worksheet $hoja, string $texto): int
    {
        foreach ($hoja->getRowIterator() as $fila) {
            if (str_starts_with((string) $hoja->getCell('A'.$fila->getRowIndex())->getValue(), $texto)) {
                return $fila->getRowIndex();
            }
        }

        $this->fail("La hoja exportada no tiene la fila \"{$texto}\".");
    }

    /** Índice (desde 1) de la columna de una fila cuyo valor es el texto. */
    private function columnaCon(Worksheet $hoja, int $fila, string $texto): int
    {
        foreach ($hoja->getRowIterator($fila, $fila)->current()->getCellIterator() as $celda) {
            if ((string) $celda->getValue() === $texto) {
                return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($celda->getColumn());
            }
        }

        $this->fail("La fila {$fila} no tiene la columna \"{$texto}\".");
    }

    /** Categoría de gasto propia; con padre es subcategoría (RN-026.2). */
    private function categoriaPropiaDe(Usuario $usuario, ?Categoria $padre = null): Categoria
    {
        $categoria = new Categoria;
        $categoria->forceFill([
            'usuario_id' => $usuario->id,
            'categoria_padre_id' => $padre?->id,
            'tipo' => 'GASTO',
            'nombre' => 'Prueba '.Str::random(8),
            'naturaleza_sugerida' => $padre === null ? null : 'VARIABLE',
            'necesidad_sugerida' => $padre === null ? null : 'DISCRECIONAL',
            'es_predeterminada' => false,
            'activa' => true,
            'creado_en' => now(),
        ])->save();

        return $categoria;
    }

    private function gastoDe(Usuario $usuario, Categoria $subcategoria, string $fecha, string $monto, string $medioPago = 'EFECTIVO', string $estado = 'CONFIRMADO'): Gasto
    {
        $gasto = new Gasto;
        $gasto->forceFill([
            'usuario_id' => $usuario->id,
            'categoria_id' => $subcategoria->categoria_padre_id,
            'subcategoria_id' => $subcategoria->id,
            'fecha' => $fecha,
            'moneda' => 'COP',
            'monto_original' => $monto,
            'tasa_cambio_usada' => '1.0000',
            'monto_cop' => $monto,
            'naturaleza' => 'VARIABLE',
            'necesidad' => 'DISCRECIONAL',
            'medio_pago' => $medioPago,
            'relevancia_tributaria' => 'NO_DEDUCIBLE',
            'etiquetas' => [],
            'origen' => 'MANUAL',
            'estado' => $estado,
        ])->save();

        return $gasto;
    }

    /** Ingreso en COP; el neto lo calcula Postgres (RN-021.2). */
    private function ingresoDe(Usuario $usuario, string $fuente, string $fecha, string $bruto, string $retencionCop = '0.00', string $aportesCop = '0.00', string $estado = 'CONFIRMADO'): void
    {
        (new Ingreso)->forceFill([
            'usuario_id' => $usuario->id,
            'fuente_id' => $fuente,
            'fecha_recepcion' => $fecha,
            'clasificacion_tributaria' => 'RENTAS_TRABAJO',
            'moneda' => 'COP',
            'monto_original' => $bruto,
            'tasa_cambio_usada' => '1.0000',
            'monto_bruto_cop' => $bruto,
            'retencion_fuente_cop' => $retencionCop,
            'aportes_descontados_cop' => $aportesCop,
            'origen' => 'MANUAL',
            'estado' => $estado,
        ])->save();
    }
}
