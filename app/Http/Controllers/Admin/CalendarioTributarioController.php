<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarioTributario;
use App\Services\BitacoraService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CalendarioTributarioController extends Controller
{
    /**
     * RF-016: Listado de calendarios tributarios por vigencia y tipo
     */
    public function index(Request $request): View
    {
        $anio = $request->query('anio', date('Y'));
        $tipo = $request->query('tipo', 'RENTA_PN');

        $registros = CalendarioTributario::where('anio_vigencia', $anio)
            ->where('tipo_obligacion', $tipo)
            ->orderBy('digito_desde')
            ->get();

        return view('admin.calendario.index', compact('registros', 'anio', 'tipo'));
    }

    /**
     * RF-016 RN-016.2 & RN-016.3 & CA-016.1: Importar y validar calendario desde CSV
     */
    public function importarCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'anio_vigencia' => ['required', 'integer', 'between:2000,2100'],
            'tipo_obligacion' => ['required', 'in:RENTA_PN,SEGURIDAD_SOCIAL_INDEP'],
            'archivo_csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $anio = $request->anio_vigencia;
        $tipo = $request->tipo_obligacion;
        $file = $request->file('archivo_csv');

        $rows = array_map('str_getcsv', file($file->getRealPath()));
        if (empty($rows)) {
            return back()->withErrors(['archivo_csv' => 'El archivo CSV está vacío.']);
        }

        // Encabezados esperados: digito_desde, digito_hasta, fecha
        $header = array_shift($rows);
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $idxDesde = array_search('digito_desde', $header);
        $idxHasta = array_search('digito_hasta', $header);
        $idxFecha = array_search('fecha', $header);

        if ($idxDesde === false || $idxHasta === false || $idxFecha === false) {
            return back()->withErrors([
                'archivo_csv' => 'El archivo debe contener las columnas: digito_desde, digito_hasta, fecha',
            ]);
        }

        $items = [];
        $cubiertos = array_fill(0, 100, false);

        foreach ($rows as $n => $row) {
            if (count($row) < 3 || empty(trim($row[$idxDesde]))) {
                continue;
            }

            $desde = str_pad(trim($row[$idxDesde]), 2, '0', STR_PAD_LEFT);
            $hasta = str_pad(trim($row[$idxHasta]), 2, '0', STR_PAD_LEFT);
            $fechaRaw = trim($row[$idxFecha]);

            // Validar formato de dígitos
            if (!preg_match('/^[0-9]{2}$/', $desde) || !preg_match('/^[0-9]{2}$/', $hasta)) {
                return back()->withErrors([
                    'archivo_csv' => "Fila " . ($n + 2) . ": Los dígitos deben ser de 2 números (00 a 99).",
                ]);
            }

            $numDesde = intval($desde);
            $numHasta = intval($hasta);

            if ($numHasta < $numDesde) {
                return back()->withErrors([
                    'archivo_csv' => "Fila " . ($n + 2) . ": El dígito hasta ({$hasta}) no puede ser menor a desde ({$desde}).",
                ]);
            }

            try {
                $fecha = Carbon::parse($fechaRaw)->toDateString();
            } catch (\Throwable $e) {
                return back()->withErrors([
                    'archivo_csv' => "Fila " . ($n + 2) . ": Formato de fecha inválido ({$fechaRaw}).",
                ]);
            }

            // Marcar cobertura y validar traslapes
            for ($d = $numDesde; $d <= $numHasta; $d++) {
                if ($cubiertos[$d]) {
                    $dPad = str_pad((string) $d, 2, '0', STR_PAD_LEFT);
                    return back()->withErrors([
                        'archivo_csv' => "Traslape detectado: El dígito {$dPad} ya está cubierto en otro rango.",
                    ]);
                }
                $cubiertos[$d] = true;
            }

            $items[] = [
                'digito_desde' => $desde,
                'digito_hasta' => $hasta,
                'fecha_vencimiento' => $fecha,
            ];
        }

        // CA-016.1: Verificar que cubre del 00 al 99 sin faltantes
        $faltantes = [];
        for ($d = 0; $d <= 99; $d++) {
            if (!$cubiertos[$d]) {
                $faltantes[] = str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            }
        }

        if (!empty($faltantes)) {
            $muestra = array_slice($faltantes, 0, 10);
            $sufijo = count($faltantes) > 10 ? '...' : '';
            return back()->withErrors([
                'archivo_csv' => 'El calendario no cubre todos los dígitos. Dígitos faltantes: ' . implode(', ', $muestra) . $sufijo,
            ]);
        }

        DB::beginTransaction();
        try {
            // Reemplazar calendario previo para esa vigencia y obligación
            CalendarioTributario::where('anio_vigencia', $anio)
                ->where('tipo_obligacion', $tipo)
                ->delete();

            foreach ($items as $item) {
                CalendarioTributario::create([
                    'anio_vigencia' => $anio,
                    'tipo_obligacion' => $tipo,
                    'digito_desde' => $item['digito_desde'],
                    'digito_hasta' => $item['digito_hasta'],
                    'fecha_vencimiento' => $item['fecha_vencimiento'],
                    'creado_por' => Auth::id(),
                ]);
            }

            BitacoraService::registrar(
                accion: 'IMPORTAR_CALENDARIO_TRIBUTARIO',
                entidadAfectada: 'calendario_tributario',
                valorNuevo: ['anio_vigencia' => $anio, 'tipo' => $tipo, 'total_filas' => count($items)],
                justificacion: "Carga masiva de calendario tributario para vigencia {$anio}",
                usuarioId: Auth::id()
            );

            DB::commit();

            return back()->with('status', "Calendario {$tipo} para la vigencia {$anio} cargado exitosamente.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['archivo_csv' => 'Error al guardar el calendario: ' . $e->getMessage()]);
        }
    }

    /**
     * CA-016.2: Consultar fecha de vencimiento por los 2 últimos dígitos del NIT
     */
    public function consultarPorNit(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'digitos' => ['required', 'regex:/^[0-9]{2}$/'],
            'anio' => ['nullable', 'integer'],
            'tipo' => ['nullable', 'in:RENTA_PN,SEGURIDAD_SOCIAL_INDEP'],
        ]);

        $digitos = $request->digitos;
        $anio = $request->anio ?? date('Y');
        $tipo = $request->tipo ?? 'RENTA_PN';

        $registro = CalendarioTributario::where('anio_vigencia', $anio)
            ->where('tipo_obligacion', $tipo)
            ->where('digito_desde', '<=', $digitos)
            ->where('digito_hasta', '>=', $digitos)
            ->first();

        if (!$registro) {
            return response()->json(['encontrado' => false, 'mensaje' => 'No se encontró fecha para los dígitos especificados.'], 404);
        }

        return response()->json([
            'encontrado' => true,
            'digitos' => $digitos,
            'anio' => $anio,
            'tipo_obligacion' => $tipo,
            'fecha_vencimiento' => $registro->fecha_vencimiento->toDateString(),
        ]);
    }
}
