<?php

namespace App\Http\Controllers;

use App\Models\IngresoInversion;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\ValorDeducibleRegistro;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RF-REP-02 / RF-REP-03 / RF-REP-04:
 * Control de Reportes Tributarios Anuales de Inversiones (Retenciones, Utilidades y Exportación CSV).
 */
class ReporteInversionController extends Controller
{
    /**
     * RF-REP-02: Reporte anual de retenciones practicadas por producto y entidad emisora.
     */
    public function retenciones(Request $request): View|JsonResponse
    {
        $anio = $this->resolverAnio($request);
        $aniosDisponibles = $this->aniosDisponibles();

        $movimientos = MovimientoInversion::with(['inversion.entidadFinanciera'])
            ->where('usuario_id', Auth::id())
            ->whereYear('fecha', $anio)
            ->where(function ($query) {
                $query->where('retencion_aplicada', '>', 0)
                    ->orWhere('gmf_aplicado', '>', 0);
            })
            ->orderBy('fecha', 'asc')
            ->get();

        // Agrupación por producto y entidad
        $resumenPorProducto = $movimientos->groupBy(fn ($m) => $m->inversion?->tipo ?? 'OTRO')
            ->map(function ($grupo, $tipo) {
                return [
                    'tipo' => $tipo,
                    'total_bruto' => $grupo->sum('monto_bruto'),
                    'total_retencion' => $grupo->sum('retencion_aplicada'),
                    'total_gmf' => $grupo->sum('gmf_aplicado'),
                    'total_neto' => $grupo->sum('monto'),
                    'transacciones_count' => $grupo->count(),
                ];
            });

        $resumenPorEntidad = $movimientos->groupBy(fn ($m) => $m->inversion?->entidadFinanciera?->nombre ?? $m->inversion?->entidad ?? 'Sin entidad')
            ->map(function ($grupo, $entidad) {
                return [
                    'entidad' => $entidad,
                    'total_bruto' => $grupo->sum('monto_bruto'),
                    'total_retencion' => $grupo->sum('retencion_aplicada'),
                    'total_gmf' => $grupo->sum('gmf_aplicado'),
                    'total_neto' => $grupo->sum('monto'),
                    'transacciones_count' => $grupo->count(),
                ];
            });

        $totales = [
            'total_bruto' => $movimientos->sum('monto_bruto'),
            'total_retencion' => $movimientos->sum('retencion_aplicada'),
            'total_gmf' => $movimientos->sum('gmf_aplicado'),
            'total_neto' => $movimientos->sum('monto'),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'anio' => $anio,
                'anios_disponibles' => $aniosDisponibles,
                'totales' => $totales,
                'resumen_producto' => $resumenPorProducto,
                'resumen_entidad' => $resumenPorEntidad,
                'detalle' => $movimientos,
            ]);
        }

        return view('inversiones.reportes.retenciones', [
            'anio' => $anio,
            'aniosDisponibles' => $aniosDisponibles,
            'totales' => $totales,
            'resumenPorProducto' => $resumenPorProducto,
            'resumenPorEntidad' => $resumenPorEntidad,
            'movimientos' => $movimientos,
        ]);
    }

    /**
     * RF-REP-03: Reporte de utilidad realizada del año por producto con clasificación fiscal.
     */
    public function utilidades(Request $request): View|JsonResponse
    {
        $anio = $this->resolverAnio($request);
        $aniosDisponibles = $this->aniosDisponibles();

        // Ingresos generados registrados en el módulo puente
        $ingresosPuente = IngresoInversion::with('inversion')
            ->where('usuario_id', Auth::id())
            ->where('anio', $anio)
            ->get();

        // Deducibles generados
        $deduciblesPuente = ValorDeducibleRegistro::with('inversion')
            ->where('usuario_id', Auth::id())
            ->where('anio', $anio)
            ->get();

        // Agrupación por clasificación fiscal
        $ventasAcciones = MovimientoInversion::with('inversion')
            ->where('usuario_id', Auth::id())
            ->whereYear('fecha', $anio)
            ->where('tipo', 'VENTA')
            ->get();

        $totales = [
            'utilidad_total' => $ingresosPuente->sum('valor_bruto'),
            'retencion_total' => $ingresosPuente->sum('retencion'),
            'deducibles_total' => $deduciblesPuente->sum('valor'),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'anio' => $anio,
                'anios_disponibles' => $aniosDisponibles,
                'totales' => $totales,
                'ingresos' => $ingresosPuente,
                'deducibles' => $deduciblesPuente,
                'ventas' => $ventasAcciones,
            ]);
        }

        return view('inversiones.reportes.utilidades', [
            'anio' => $anio,
            'aniosDisponibles' => $aniosDisponibles,
            'totales' => $totales,
            'ingresos' => $ingresosPuente,
            'deducibles' => $deduciblesPuente,
            'ventas' => $ventasAcciones,
        ]);
    }

    /**
     * RF-REP-04: Exportar reporte de retenciones a CSV.
     */
    public function exportarRetencionesCsv(Request $request): StreamedResponse
    {
        $anio = $this->resolverAnio($request);

        $movimientos = MovimientoInversion::with(['inversion.entidadFinanciera'])
            ->where('usuario_id', Auth::id())
            ->whereYear('fecha', $anio)
            ->where(function ($query) {
                $query->where('retencion_aplicada', '>', 0)
                    ->orWhere('gmf_aplicado', '>', 0);
            })
            ->orderBy('fecha', 'asc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"reporte_retenciones_{$anio}.csv\"",
        ];

        return response()->stream(function () use ($movimientos) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            fputcsv($handle, [
                'Fecha',
                'Inversión',
                'Tipo Producto',
                'Entidad Emisora',
                'Tipo Transacción',
                'Monto Bruto (COP)',
                'Retención Fuente (COP)',
                'GMF 4x1000 (COP)',
                'Neto Desembolsado (COP)',
                'Nota',
            ]);

            foreach ($movimientos as $m) {
                fputcsv($handle, [
                    $m->fecha ? $m->fecha->toDateString() : '',
                    $m->inversion?->nombre ?? '',
                    $m->inversion?->tipo ?? '',
                    $m->inversion?->entidadFinanciera?->nombre ?? $m->inversion?->entidad ?? 'Sin entidad',
                    $m->tipo,
                    number_format((float) ($m->monto_bruto ?? $m->monto), 2, '.', ''),
                    number_format((float) $m->retencion_aplicada, 2, '.', ''),
                    number_format((float) $m->gmf_aplicado, 2, '.', ''),
                    number_format((float) $m->monto, 2, '.', ''),
                    $m->nota ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * RF-REP-04: Exportar reporte de utilidades a CSV.
     */
    public function exportarUtilidadesCsv(Request $request): StreamedResponse
    {
        $anio = $this->resolverAnio($request);

        $ingresos = IngresoInversion::with('inversion')
            ->where('usuario_id', Auth::id())
            ->where('anio', $anio)
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"reporte_utilidades_{$anio}.csv\"",
        ];

        return response()->stream(function () use ($ingresos) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            fputcsv($handle, [
                'Inversión',
                'Tipo Producto',
                'Concepto de Ingreso',
                'Año Gravable',
                'Valor Bruto (COP)',
                'Retención en la Fuente (COP)',
                'Neto Recibido (COP)',
            ]);

            foreach ($ingresos as $ing) {
                fputcsv($handle, [
                    $ing->inversion?->nombre ?? '',
                    $ing->inversion?->tipo ?? '',
                    $ing->tipo,
                    $ing->anio,
                    number_format((float) $ing->valor_bruto, 2, '.', ''),
                    number_format((float) $ing->retencion, 2, '.', ''),
                    number_format((float) bcsub((string) $ing->valor_bruto, (string) $ing->retencion, 2), 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function resolverAnio(Request $request): int
    {
        if ($request->filled('anio')) {
            return (int) $request->query('anio');
        }

        $userId = Auth::id();
        $anioActual = CarbonImmutable::today()->year;

        // Verificar si el año actual tiene registros en retenciones o ingresos
        $tieneEnAnioActual = MovimientoInversion::where('usuario_id', $userId)
            ->whereYear('fecha', $anioActual)
            ->where(fn ($q) => $q->where('retencion_aplicada', '>', 0)->orWhere('gmf_aplicado', '>', 0))
            ->exists()
            || IngresoInversion::where('usuario_id', $userId)->where('anio', $anioActual)->exists();

        if ($tieneEnAnioActual) {
            return $anioActual;
        }

        // Si el año actual está vacío de retenciones/ingresos, buscar el año más reciente con registros
        $anioConRegistros = MovimientoInversion::where('usuario_id', $userId)
            ->where(fn ($q) => $q->where('retencion_aplicada', '>', 0)->orWhere('gmf_aplicado', '>', 0))
            ->selectRaw('EXTRACT(YEAR FROM fecha)::int AS anio')
            ->orderByDesc('anio')
            ->value('anio');

        if (! $anioConRegistros) {
            $anioConRegistros = IngresoInversion::where('usuario_id', $userId)
                ->orderByDesc('anio')
                ->value('anio');
        }

        return $anioConRegistros ? (int) $anioConRegistros : $anioActual;
    }

    private function aniosDisponibles(): array
    {
        $userId = Auth::id();
        $aniosMovs = MovimientoInversion::where('usuario_id', $userId)
            ->where(fn ($q) => $q->where('retencion_aplicada', '>', 0)->orWhere('gmf_aplicado', '>', 0))
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM fecha)::int AS anio')
            ->pluck('anio');

        $aniosIngresos = IngresoInversion::where('usuario_id', $userId)
            ->selectRaw('DISTINCT anio')
            ->pluck('anio');

        $todos = $aniosMovs->merge($aniosIngresos)
            ->push(CarbonImmutable::today()->year)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $todos;
    }
}
