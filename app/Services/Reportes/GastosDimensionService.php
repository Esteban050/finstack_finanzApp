<?php

namespace App\Services\Reportes;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\Usuario;
use Carbon\CarbonImmutable;

/**
 * MOD-11 — Reportes y Dashboards Dinámicos
 * HU-H05: Servicio de Gastos por Dimensión y Top de Comercios (RF-067).
 *
 * Soporta cruce dinámico bidimensional (tabla de contingencia),
 * advertencia de no sumabilidad para etiquetas (CA-067.2) y ranking de comercios (RN-067.3).
 */
class GastosDimensionService
{
    public const DIMENSIONES = [
        'categoria' => 'Categoría',
        'subcategoria' => 'Subcategoría',
        'naturaleza' => 'Naturaleza',
        'necesidad' => 'Necesidad',
        'medio_pago' => 'Medio de pago',
        'relevancia_tributaria' => 'Relevancia tributaria',
        'etiqueta' => 'Etiqueta',
        'mes' => 'Mes',
    ];

    public const AVISO_ETIQUETAS = 'Un gasto puede tener múltiples etiquetas; los totales por etiqueta no son sumables entre sí.';

    /**
     * Resuelve y valida las dimensiones seleccionadas y fechas.
     */
    public function resolverParametros(
        ?string $dimensionFila = 'categoria',
        ?string $dimensionColumna = 'medio_pago',
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        ?CarbonImmutable $referencia = null
    ): array {
        $hoy = $referencia ?? CarbonImmutable::today();

        $dimFila = array_key_exists($dimensionFila ?? '', self::DIMENSIONES) ? $dimensionFila : 'categoria';
        $dimCol = array_key_exists($dimensionColumna ?? '', self::DIMENSIONES) ? $dimensionColumna : 'medio_pago';

        if ($dimFila === $dimCol) {
            $dimCol = ($dimFila === 'categoria') ? 'medio_pago' : 'categoria';
        }

        if ($fechaDesde && $fechaHasta) {
            $desde = CarbonImmutable::parse($fechaDesde)->startOfDay();
            $hasta = CarbonImmutable::parse($fechaHasta)->endOfDay();
        } else {
            // Por defecto mes actual
            $desde = $hoy->startOfMonth();
            $hasta = $hoy->endOfMonth();
        }

        return [
            'dimension_fila' => $dimFila,
            'dimension_columna' => $dimCol,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ];
    }

    /**
     * Extrae el valor o lista de valores de un gasto según la dimensión indicada.
     *
     * @return array<int, string>
     */
    public function extraerValoresDimension(Gasto $gasto, string $dimension, array $catalogoNombres): array
    {
        switch ($dimension) {
            case 'categoria':
                $catId = $gasto->categoria_id ?? 'sin_categoria';
                return [$catalogoNombres['categorias'][$catId] ?? 'Sin categoría'];

            case 'subcategoria':
                $subId = $gasto->subcategoria_id ?? 'sin_subcategoria';
                return [$catalogoNombres['subcategorias'][$subId] ?? 'Sin subcategoría'];

            case 'naturaleza':
                return [$gasto->naturaleza ? ucfirst(strtolower($gasto->naturaleza)) : 'No especificada'];

            case 'necesidad':
                return [$gasto->necesidad ? ucfirst(strtolower($gasto->necesidad)) : 'No especificada'];

            case 'medio_pago':
                $mapMedios = [
                    'EFECTIVO' => 'Efectivo',
                    'TARJETA_DEBITO' => 'Tarjeta Débito',
                    'TARJETA_CREDITO' => 'Tarjeta Crédito',
                    'TRANSFERENCIA' => 'Transferencia',
                    'BILLETERA_DIGITAL' => 'Billetera Digital',
                    'OTRO' => 'Otro',
                ];
                return [$mapMedios[$gasto->medio_pago] ?? ($gasto->medio_pago ?? 'Otro')];

            case 'relevancia_tributaria':
                return [
                    $gasto->relevancia_tributaria === 'POTENCIALMENTE_DEDUCIBLE'
                        ? 'Deducible'
                        : 'No deducible'
                ];

            case 'etiqueta':
                $etiquetas = $gasto->etiquetas;
                if (empty($etiquetas) || !is_array($etiquetas)) {
                    return ['Sin etiqueta'];
                }
                return array_map('trim', $etiquetas);

            case 'mes':
                return [ucfirst($gasto->fecha->translatedFormat('F Y'))];

            default:
                return ['General'];
        }
    }

    /**
     * Genera la matriz de contingencia bidimensional y el top de comercios.
     */
    public function consultarGastosDimension(
        Usuario $usuario,
        ?string $dimensionFila = 'categoria',
        ?string $dimensionColumna = 'medio_pago',
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        ?CarbonImmutable $referencia = null
    ): array {
        $params = $this->resolverParametros($dimensionFila, $dimensionColumna, $fechaDesde, $fechaHasta, $referencia);
        $dimFila = $params['dimension_fila'];
        $dimCol = $params['dimension_columna'];
        $desde = $params['fecha_desde'];
        $hasta = $params['fecha_hasta'];

        // Consultar todos los gastos confirmados del periodo
        $gastos = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->get();

        $totalGastosPeriodo = '0';
        foreach ($gastos as $g) {
            $totalGastosPeriodo = bcadd($totalGastosPeriodo, (string) $g->monto_cop, 2);
        }

        // Cargar nombres de categorías y subcategorías
        $catIds = $gastos->pluck('categoria_id')->filter()->unique()->all();
        $subCatIds = $gastos->pluck('subcategoria_id')->filter()->unique()->all();
        $todosIds = array_unique(array_merge($catIds, $subCatIds));

        $cats = Categoria::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereIn('id', $todosIds)
            ->pluck('nombre', 'id')
            ->all();

        $catalogoNombres = [
            'categorias' => $cats,
            'subcategorias' => $cats,
        ];

        // Construir celdas cruzadas
        // $matriz[fila][columna] = monto
        $matriz = [];
        $conteoFilas = [];
        $columnasSet = [];

        foreach ($gastos as $gasto) {
            $monto = (string) $gasto->monto_cop;
            $valoresFila = $this->extraerValoresDimension($gasto, $dimFila, $catalogoNombres);
            $valoresCol = $this->extraerValoresDimension($gasto, $dimCol, $catalogoNombres);

            foreach ($valoresFila as $valF) {
                if (!isset($matriz[$valF])) {
                    $matriz[$valF] = [];
                    $conteoFilas[$valF] = 0;
                }
                $conteoFilas[$valF]++;

                foreach ($valoresCol as $valC) {
                    $columnasSet[$valC] = true;
                    $actual = $matriz[$valF][$valC] ?? '0';
                    $matriz[$valF][$valC] = bcadd($actual, $monto, 2);
                }
            }
        }

        $columnas = array_keys($columnasSet);
        sort($columnas);

        // Estructurar filas con métricas agregadas
        $filas = [];
        $totalesColumnas = array_fill_keys($columnas, '0');
        $granTotalFilas = '0';
        $granTotalGastosConteo = 0;

        foreach ($matriz as $nombreFila => $cols) {
            $totalFila = '0';
            $valoresFila = [];

            foreach ($columnas as $col) {
                $val = $cols[$col] ?? '0';
                $valoresFila[$col] = $val;
                $totalFila = bcadd($totalFila, $val, 2);
                $totalesColumnas[$col] = bcadd($totalesColumnas[$col], $val, 2);
            }

            $conteo = $conteoFilas[$nombreFila];
            $granTotalGastosConteo += $conteo;
            $granTotalFilas = bcadd($granTotalFilas, $totalFila, 2);

            $porcentaje = bccomp($totalGastosPeriodo, '0', 2) === 1
                ? round((float) bcdiv(bcmul($totalFila, '100', 6), $totalGastosPeriodo, 4), 2)
                : 0.0;

            $promedioGasto = $conteo > 0
                ? round((float) bcdiv($totalFila, (string) $conteo, 4), 2)
                : 0.0;

            $filas[] = [
                'nombre' => $nombreFila,
                'columnas' => $valoresFila,
                'total' => $totalFila,
                'porcentaje' => $porcentaje,
                'cantidad_gastos' => $conteo,
                'promedio_por_gasto' => $promedioGasto,
            ];
        }

        // Ordenar filas de mayor a menor total
        usort($filas, fn ($a, $b) => bccomp($b['total'], $a['total'], 2));

        // Top 10 Comercios / Beneficiarios (RN-067.3)
        $topComerciosRaw = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->whereNotNull('comercio_beneficiario')
            ->where('comercio_beneficiario', '!=', '')
            ->selectRaw('comercio_beneficiario, SUM(monto_cop) as total, COUNT(*) as conteo')
            ->groupBy('comercio_beneficiario')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $topComercios = [];
        foreach ($topComerciosRaw as $tc) {
            $monto = (string) $tc->total;
            $porc = bccomp($totalGastosPeriodo, '0', 2) === 1
                ? round((float) bcdiv(bcmul($monto, '100', 6), $totalGastosPeriodo, 4), 2)
                : 0.0;

            $topComercios[] = [
                'comercio' => $tc->comercio_beneficiario,
                'monto' => $monto,
                'conteo' => (int) $tc->conteo,
                'porcentaje' => $porc,
            ];
        }

        $tieneEtiquetas = ($dimFila === 'etiqueta' || $dimCol === 'etiqueta');

        return [
            'parametros' => [
                'dimension_fila' => $dimFila,
                'dimension_fila_nombre' => self::DIMENSIONES[$dimFila],
                'dimension_columna' => $dimCol,
                'dimension_columna_nombre' => self::DIMENSIONES[$dimCol],
                'fecha_desde' => $desde->format('Y-m-d'),
                'fecha_hasta' => $hasta->format('Y-m-d'),
                'periodo_texto' => 'Del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y'),
            ],
            'dimensiones_disponibles' => self::DIMENSIONES,
            'columnas' => $columnas,
            'filas' => $filas,
            'totales_columnas' => $totalesColumnas,
            'total_general' => $totalGastosPeriodo,
            'conteo_total_gastos' => count($gastos),
            'aviso_etiquetas' => $tieneEtiquetas ? self::AVISO_ETIQUETAS : null,
            'top_comercios' => $topComercios,
        ];
    }
}
