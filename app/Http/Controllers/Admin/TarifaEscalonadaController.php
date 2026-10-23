<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParametroNormativo;
use App\Models\TarifaEscalonada;
use App\Services\BitacoraService;
use App\Services\TarifaCalculoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TarifaEscalonadaController extends Controller
{
    /**
     * RF-014: Ver y gestionar tablas escalonadas para una vigencia
     */
    public function index(string $parametroId): View
    {
        $parametro = ParametroNormativo::findOrFail($parametroId);
        $tarifasRenta = TarifaEscalonada::where('parametro_normativo_id', $parametroId)
            ->where('tipo_tabla', 'RENTA_ART241')
            ->orderBy('rango_desde')
            ->get();

        $tarifasFsp = TarifaEscalonada::where('parametro_normativo_id', $parametroId)
            ->where('tipo_tabla', 'FSP')
            ->orderBy('rango_desde')
            ->get();

        $tarifasArl = TarifaEscalonada::where('parametro_normativo_id', $parametroId)
            ->where('tipo_tabla', 'ARL')
            ->orderBy('rango_desde')
            ->get();

        return view('admin.tarifas.index', compact('parametro', 'tarifasRenta', 'tarifasFsp', 'tarifasArl'));
    }

    /**
     * RF-014 RN-014.4 & CA-014.1: Guardar tabla completa validando huecos y traslapes
     */
    public function guardarTabla(Request $request, string $parametroId): RedirectResponse
    {
        $parametro = ParametroNormativo::findOrFail($parametroId);

        $request->validate([
            'tipo_tabla' => ['required', 'in:RENTA_ART241,FSP,ARL'],
            'tramos' => ['required', 'array', 'min:1'],
            'tramos.*.rango_desde' => ['required', 'numeric', 'min:0'],
            'tramos.*.rango_hasta' => ['nullable', 'numeric'],
            'tramos.*.tarifa_porc' => ['required', 'numeric', 'between:0,100'],
            'tramos.*.impuesto_base_uvt' => ['nullable', 'numeric', 'min:0'],
        ]);

        $tipoTabla = $request->tipo_tabla;
        $tramos = $request->tramos;

        // Validar continuidad
        $validacion = TarifaCalculoService::validarContinuidadRangos($tramos);
        if (!$validacion['valido']) {
            return back()->withInput()->withErrors([
                'tabla_error' => $validacion['error'],
            ]);
        }

        DB::beginTransaction();
        try {
            // Eliminar tramos anteriores del mismo tipo
            TarifaEscalonada::where('parametro_normativo_id', $parametroId)
                ->where('tipo_tabla', $tipoTabla)
                ->delete();

            // Insertar nuevos tramos
            foreach ($tramos as $tramo) {
                TarifaEscalonada::create([
                    'parametro_normativo_id' => $parametroId,
                    'tipo_tabla' => $tipoTabla,
                    'rango_desde' => $tramo['rango_desde'],
                    'rango_hasta' => !empty($tramo['rango_hasta']) ? $tramo['rango_hasta'] : null,
                    'tarifa_porc' => $tramo['tarifa_porc'],
                    'impuesto_base_uvt' => $tramo['impuesto_base_uvt'] ?? 0.0,
                ]);
            }

            BitacoraService::registrar(
                accion: 'ACTUALIZAR_TABLA_TARIFAS_ESCALONADAS',
                entidadAfectada: 'tarifas_escalonadas',
                entidadId: $parametroId,
                valorNuevo: ['tipo_tabla' => $tipoTabla, 'tramos' => $tramos],
                justificacion: "Actualización de tabla {$tipoTabla} para vigencia {$parametro->anio_vigencia}",
                usuarioId: Auth::id()
            );

            DB::commit();

            return back()->with('status', "Tabla {$tipoTabla} actualizada correctamente.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'tabla_error' => 'Error al guardar la tabla: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * RF-014 CA-014.2: Endpoint de prueba de cálculo de renta
     */
    public function probarCalculoRenta(Request $request, string $parametroId): \Illuminate\Http\JsonResponse
    {
        $request->validate(['base_uvt' => 'required|numeric|min:0']);
        $parametro = ParametroNormativo::findOrFail($parametroId);

        $base = floatval($request->base_uvt);
        $impuestoUvt = TarifaCalculoService::calcularImpuestoRenta($base, $parametro);
        $impuestoCop = $impuestoUvt * floatval($parametro->valor_uvt);

        return response()->json([
            'base_uvt' => $base,
            'impuesto_uvt' => $impuestoUvt,
            'impuesto_cop' => $impuestoCop,
        ]);
    }
}
