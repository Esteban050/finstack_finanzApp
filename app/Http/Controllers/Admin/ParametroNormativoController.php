<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParametroNormativo;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ParametroNormativoController extends Controller
{
    /**
     * RF-013: Listado de vigencias normativas
     */
    public function index(): View
    {
        $parametros = ParametroNormativo::orderByDesc('anio_vigencia')
            ->orderByDesc('version')
            ->paginate(15);

        return view('admin.parametros.index', compact('parametros'));
    }

    public function create(): View
    {
        return view('admin.parametros.create');
    }

    /**
     * RF-013 RN-013.1, RN-013.2, RN-013.3: Registro y versionamiento de vigencias
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'anio_vigencia' => ['required', 'integer', 'between:2000,2100'],
            'acto_administrativo' => ['required', 'string', 'max:120'],
            'fecha_acto' => ['required', 'date'],
            'valor_uvt' => ['required', 'numeric', 'min:0.01'],
            'valor_smmlv' => ['required', 'numeric', 'min:0.01'],
            'tarifa_salud_indep' => ['required', 'numeric', 'between:0,100'],
            'tarifa_pension_indep' => ['required', 'numeric', 'between:0,100'],
            'tarifa_salud_emp' => ['required', 'numeric', 'between:0,100'],
            'tarifa_pension_emp' => ['required', 'numeric', 'between:0,100'],
            'porc_ibc_indep' => ['required', 'numeric', 'between:0,100'],
            'porc_ibc_integral' => ['required', 'numeric', 'between:0,100'],
            'ibc_min_smmlv' => ['required', 'numeric', 'min:0.01'],
            'ibc_max_smmlv' => ['required', 'numeric', 'gte:ibc_min_smmlv'],
            'tope_patrimonio_uvt' => ['required', 'integer', 'min:1'],
            'tope_ingresos_uvt' => ['required', 'integer', 'min:1'],
            'tope_tc_uvt' => ['required', 'integer', 'min:1'],
            'tope_compras_uvt' => ['required', 'integer', 'min:1'],
            'tope_consignaciones_uvt' => ['required', 'integer', 'min:1'],
            'limite_renta_exenta_25_uvt' => ['required', 'integer', 'min:1'],
            'porc_limite_global_exentas' => ['required', 'numeric', 'between:0,100'],
            'limite_global_exentas_uvt' => ['required', 'integer', 'min:1'],
            'tarifa_gmf' => ['required', 'numeric', 'between:0,100'],
            'exencion_gmf_uvt' => ['required', 'integer', 'min:0'],
            'ipc_anual' => ['required', 'numeric', 'between:0,100'],
            'justificacion_cambio' => ['required', 'string', 'min:20'],
        ], [
            'justificacion_cambio.min' => 'La justificación del cambio es obligatoria y debe tener mínimo 20 caracteres.',
            'ibc_max_smmlv.gte' => 'El IBC máximo debe ser mayor o igual al IBC mínimo.',
        ]);

        DB::beginTransaction();
        try {
            $anio = $validated['anio_vigencia'];

            // RN-013.2: Solo una vigencia activa por año. Desactivar versión anterior si existe
            $ultimaVersion = ParametroNormativo::where('anio_vigencia', $anio)
                ->orderByDesc('version')
                ->first();

            $nuevaVersion = $ultimaVersion ? ($ultimaVersion->version + 1) : 1;

            if ($ultimaVersion) {
                $ultimaVersion->update(['es_activa' => false]);
            }

            $parametro = ParametroNormativo::create(array_merge($validated, [
                'version' => $nuevaVersion,
                'es_activa' => true,
                'creado_por' => Auth::id(),
                'creado_en' => now(),
            ]));

            // RN-013.3: Registrar en bitácora
            BitacoraService::registrar(
                accion: 'CREAR_PARAMETROS_NORMATIVOS',
                entidadAfectada: 'parametros_normativos',
                entidadId: $parametro->id,
                valorAnterior: $ultimaVersion?->toArray(),
                valorNuevo: $parametro->toArray(),
                justificacion: $validated['justificacion_cambio'],
                usuarioId: Auth::id()
            );

            DB::commit();

            return redirect()->route('admin.parametros.index')
                ->with('status', "Vigencia {$anio} (Versión {$nuevaVersion}) registrada y activada exitosamente.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'error' => 'Error al guardar parámetros normativos: ' . $e->getMessage(),
            ]);
        }
    }

    public function edit(string $id): View
    {
        $parametro = ParametroNormativo::with('tarifasEscalonadas')->findOrFail($id);
        return view('admin.parametros.edit', compact('parametro'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $parametroActual = ParametroNormativo::findOrFail($id);

        $validated = $request->validate([
            'acto_administrativo' => ['required', 'string', 'max:120'],
            'fecha_acto' => ['required', 'date'],
            'valor_uvt' => ['required', 'numeric', 'min:0.01'],
            'valor_smmlv' => ['required', 'numeric', 'min:0.01'],
            'tarifa_salud_indep' => ['required', 'numeric', 'between:0,100'],
            'tarifa_pension_indep' => ['required', 'numeric', 'between:0,100'],
            'tarifa_salud_emp' => ['required', 'numeric', 'between:0,100'],
            'tarifa_pension_emp' => ['required', 'numeric', 'between:0,100'],
            'porc_ibc_indep' => ['required', 'numeric', 'between:0,100'],
            'porc_ibc_integral' => ['required', 'numeric', 'between:0,100'],
            'ibc_min_smmlv' => ['required', 'numeric', 'min:0.01'],
            'ibc_max_smmlv' => ['required', 'numeric', 'gte:ibc_min_smmlv'],
            'tope_patrimonio_uvt' => ['required', 'integer', 'min:1'],
            'tope_ingresos_uvt' => ['required', 'integer', 'min:1'],
            'tope_tc_uvt' => ['required', 'integer', 'min:1'],
            'tope_compras_uvt' => ['required', 'integer', 'min:1'],
            'tope_consignaciones_uvt' => ['required', 'integer', 'min:1'],
            'limite_renta_exenta_25_uvt' => ['required', 'integer', 'min:1'],
            'porc_limite_global_exentas' => ['required', 'numeric', 'between:0,100'],
            'limite_global_exentas_uvt' => ['required', 'integer', 'min:1'],
            'tarifa_gmf' => ['required', 'numeric', 'between:0,100'],
            'exencion_gmf_uvt' => ['required', 'integer', 'min:0'],
            'ipc_anual' => ['required', 'numeric', 'between:0,100'],
            'justificacion_cambio' => ['required', 'string', 'min:20'],
        ], [
            'justificacion_cambio.min' => 'La justificación del cambio es obligatoria y debe tener mínimo 20 caracteres.',
        ]);

        DB::beginTransaction();
        try {
            $valorAnterior = $parametroActual->toArray();

            // RN-013.2: Reemplazar creando nueva versión para mantener histórico
            $parametroActual->update(['es_activa' => false]);

            $nuevaVersion = ParametroNormativo::create(array_merge($validated, [
                'anio_vigencia' => $parametroActual->anio_vigencia,
                'version' => $parametroActual->version + 1,
                'es_activa' => true,
                'creado_por' => Auth::id(),
                'creado_en' => now(),
            ]));

            // Clonar tarifas escalonadas si existían
            foreach ($parametroActual->tarifasEscalonadas as $tarifa) {
                $nuevaVersion->tarifasEscalonadas()->create([
                    'tipo_tabla' => $tarifa->tipo_tabla,
                    'rango_desde' => $tarifa->rango_desde,
                    'rango_hasta' => $tarifa->rango_hasta,
                    'tarifa_porc' => $tarifa->tarifa_porc,
                    'impuesto_base_uvt' => $tarifa->impuesto_base_uvt,
                ]);
            }

            BitacoraService::registrar(
                accion: 'ACTUALIZAR_PARAMETROS_NORMATIVOS',
                entidadAfectada: 'parametros_normativos',
                entidadId: $nuevaVersion->id,
                valorAnterior: $valorAnterior,
                valorNuevo: $nuevaVersion->toArray(),
                justificacion: $validated['justificacion_cambio'],
                usuarioId: Auth::id()
            );

            DB::commit();

            return redirect()->route('admin.parametros.index')
                ->with('status', "Parámetros actualizados mediante nueva versión {$nuevaVersion->version}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'error' => 'Error al actualizar parámetros normativos: ' . $e->getMessage(),
            ]);
        }
    }
}
