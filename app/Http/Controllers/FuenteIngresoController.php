<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\FuenteIngreso;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Services\BitacoraService;
use App\Services\IngresoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * RF-020 — Gestión de fuentes de ingreso (MOD-03).
 *
 * El aislamiento de datos (RNG-01) lo garantiza el global scope
 * DelUsuarioAutenticado sobre el modelo: toda consulta queda filtrada por el
 * usuario en sesión, incluidos los listados.
 */
class FuenteIngresoController extends Controller
{
    /**
     * RF-020: Listado de fuentes de ingreso del Usuario.
     */
    public function index(Request $request): View|JsonResponse
    {
        $fuentes = FuenteIngreso::with('tipoFuente')
            ->orderBy('activa', 'desc')
            ->orderBy('nombre')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Fuentes de ingreso consultadas.',
                'data' => $fuentes,
            ]);
        }

        return view('ingresos.fuentes.index', [
            'fuentes' => $fuentes,
            'tiposFuente' => $this->catalogoTiposFuente(),
            'clasificaciones' => FuenteIngreso::CLASIFICACIONES,
            'sugerencias' => IngresoService::SUGERENCIAS,
        ]);
    }

    /**
     * RF-020 RN-020.1: Crear una fuente de ingreso.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate($this->reglas(), $this->mensajes());

        $this->validarNombreUnico($validated['nombre'], null);

        $fuente = FuenteIngreso::create([
            'usuario_id' => Auth::id(),
            'tipo_fuente_id' => $validated['tipo_fuente_id'],
            'nombre' => trim($validated['nombre']),
            'clasificacion_tributaria' => $validated['clasificacion_tributaria'],
            'pagador_entidad' => $validated['pagador_entidad'] ?? null,
            'activa' => true,
        ]);

        BitacoraService::registrar(
            accion: 'CREAR_FUENTE_INGRESO',
            entidadAfectada: 'fuentes_ingreso',
            entidadId: $fuente->id,
            valorNuevo: $fuente->toArray(),
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Fuente de ingreso creada exitosamente.',
                'data' => $fuente->load('tipoFuente'),
            ], 201);
        }

        return redirect()->route('ingresos.fuentes.index')
            ->with('status', 'Fuente de ingreso creada exitosamente.');
    }

    /**
     * RF-020 RN-020.1: Editar una fuente de ingreso.
     */
    public function update(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $fuente = $this->fuentePropia($id);
        $validated = $request->validate($this->reglas(), $this->mensajes());

        $this->validarNombreUnico($validated['nombre'], $fuente->id);

        $anterior = $fuente->toArray();

        $fuente->update([
            'tipo_fuente_id' => $validated['tipo_fuente_id'],
            'nombre' => trim($validated['nombre']),
            'clasificacion_tributaria' => $validated['clasificacion_tributaria'],
            'pagador_entidad' => $validated['pagador_entidad'] ?? null,
        ]);

        BitacoraService::registrar(
            accion: 'EDITAR_FUENTE_INGRESO',
            entidadAfectada: 'fuentes_ingreso',
            entidadId: $fuente->id,
            valorAnterior: $anterior,
            valorNuevo: $fuente->fresh()->toArray(),
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Fuente de ingreso actualizada.',
                'data' => $fuente->fresh()->load('tipoFuente'),
            ]);
        }

        return redirect()->route('ingresos.fuentes.index')
            ->with('status', 'Fuente de ingreso actualizada.');
    }

    /**
     * RF-020 RN-020.3: Activar o inactivar. Una fuente inactiva no aparece al
     * registrar ingresos nuevos, pero los históricos la conservan.
     */
    public function toggleActiva(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $fuente = $this->fuentePropia($id);
        $nuevoEstado = ! $fuente->activa;

        $fuente->update(['activa' => $nuevoEstado]);

        BitacoraService::registrar(
            accion: $nuevoEstado ? 'ACTIVAR_FUENTE_INGRESO' : 'INACTIVAR_FUENTE_INGRESO',
            entidadAfectada: 'fuentes_ingreso',
            entidadId: $fuente->id,
            valorNuevo: ['activa' => $nuevoEstado],
            usuarioId: Auth::id()
        );

        $mensaje = $nuevoEstado
            ? 'Fuente activada.'
            : 'Fuente inactivada. No aparecerá al registrar ingresos nuevos.';

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => $mensaje,
                'data' => $fuente->fresh(),
            ]);
        }

        return back()->with('status', $mensaje);
    }

    /**
     * RF-020 RN-020.3 / CA-020.2: Una fuente con ingresos asociados no se puede
     * borrar; el sistema solo ofrece la opción "Inactivar".
     */
    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $fuente = $this->fuentePropia($id);

        if ($fuente->tieneIngresos()) {
            throw ValidationException::withMessages([
                'fuente' => 'Esta fuente tiene ingresos asociados y no se puede borrar. Solo puede inactivarla.',
            ]);
        }

        $anterior = $fuente->toArray();
        $fuente->delete();

        BitacoraService::registrar(
            accion: 'ELIMINAR_FUENTE_INGRESO',
            entidadAfectada: 'fuentes_ingreso',
            entidadId: $id,
            valorAnterior: $anterior,
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => 'Fuente de ingreso eliminada.']);
        }

        return redirect()->route('ingresos.fuentes.index')
            ->with('status', 'Fuente de ingreso eliminada.');
    }

    /**
     * RN-020.1: Reglas de validación comunes a crear y editar.
     */
    private function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:60'],
            'tipo_fuente_id' => [
                'required',
                'uuid',
                // RN-020.1: el tipo proviene del catálogo global de ingresos (RN-015.2).
                Rule::exists('categorias', 'id')->where(function ($query) {
                    $query->where('tipo', 'INGRESO')
                        ->whereNull('categoria_padre_id')
                        ->where('es_predeterminada', true);
                }),
            ],
            'clasificacion_tributaria' => ['required', Rule::in(FuenteIngreso::CLASIFICACIONES)],
            'pagador_entidad' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * RN-020.1: Mensaje propio para cada regla. El proyecto no tiene archivos de
     * traducción, así que una regla sin mensaje acá sale al usuario como la clave
     * cruda (`validation.required`).
     */
    private function mensajes(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre de la fuente de ingreso.',
            'nombre.string' => 'El nombre de la fuente debe ser texto.',
            'nombre.min' => 'El nombre de la fuente debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre de la fuente no puede superar los 60 caracteres.',
            'tipo_fuente_id.required' => 'Seleccione el tipo de fuente de ingreso.',
            'tipo_fuente_id.uuid' => 'El tipo de fuente seleccionado no es válido.',
            'tipo_fuente_id.exists' => 'El tipo de fuente no pertenece al catálogo de ingresos.',
            'clasificacion_tributaria.required' => 'Seleccione la clasificación tributaria de la fuente.',
            'clasificacion_tributaria.in' => 'La clasificación tributaria debe ser una de las cinco cédulas del artículo 330 del Estatuto Tributario.',
            'pagador_entidad.string' => 'El pagador o entidad debe ser texto.',
            'pagador_entidad.max' => 'El pagador o entidad no puede superar los 80 caracteres.',
        ];
    }

    /**
     * RN-020.1 / CA-020.1: El nombre de la fuente es único por usuario. Se valida
     * aquí para devolver el mensaje del criterio en lugar de que estalle el índice
     * único uq_fuentes_ingreso_usuario_nombre.
     */
    private function validarNombreUnico(string $nombre, ?string $excluirId): void
    {
        $existe = FuenteIngreso::whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($nombre))])
            ->when($excluirId !== null, fn ($q) => $q->whereKeyNot($excluirId))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe una fuente con ese nombre.',
            ]);
        }
    }

    /**
     * RN-015.2: Catálogo de tipos de fuente de ingreso disponibles.
     */
    private function catalogoTiposFuente()
    {
        return Categoria::where('tipo', 'INGRESO')
            ->where('es_predeterminada', true)
            ->where('activa', true)
            ->whereNull('categoria_padre_id')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function fuentePropia(string $id): FuenteIngreso
    {
        $fuente = FuenteIngreso::find($id);

        if ($fuente !== null) {
            return $fuente;
        }

        $esDeOtro = FuenteIngreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'fuentes_ingreso',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }
}
