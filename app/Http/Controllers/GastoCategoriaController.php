<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Services\BitacoraService;
use App\Services\CategoriaGastoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * RF-027 — Gestión de categorías y subcategorías personalizadas (MOD-04).
 *
 * El Usuario administra las propias. Las predeterminadas son del catálogo global
 * del Administrador (RF-015): aquí se leen y sirven como destino de una fusión,
 * pero no se renombran, inactivan ni borran desde este módulo.
 */
class GastoCategoriaController extends Controller
{
    /**
     * RF-027: Árbol de categorías de gasto visibles para el Usuario.
     *
     * RN-026.2: cada subcategoría viaja con sus valores sugeridos de naturaleza
     * y necesidad, que son los que precarga el formulario de gasto (CA-026.1).
     */
    public function index(Request $request): View|JsonResponse
    {
        $categorias = CategoriaGastoService::catalogo(Auth::id());

        $datos = [
            'categorias' => $categorias,
            'paleta' => CategoriaGastoService::PALETA,
            'iconos' => CategoriaGastoService::ICONOS,
            // RN-027.1: los topes, para que la interfaz pueda anticiparlos.
            'limites' => [
                'categorias' => CategoriaGastoService::MAX_CATEGORIAS,
                'subcategorias_por_categoria' => CategoriaGastoService::MAX_SUBCATEGORIAS,
                'propias' => $categorias->where('es_predeterminada', false)->count(),
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Categorías de gasto consultadas.',
                'data' => $datos,
            ]);
        }

        return view('gastos.categorias.index', $datos);
    }

    /**
     * RF-027 RN-027.1: Crear una categoría propia o una subcategoría dentro de
     * una categoría visible.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate($this->reglas(), $this->mensajes());

        $padre = $this->padreValido($validated['categoria_padre_id'] ?? null);
        $this->validarSugerencias($validated, $padre !== null);

        // RN-027.1 / CA-027.2: cupo y unicidad en el nivel.
        if ($padre === null) {
            CategoriaGastoService::validarCupoCategoria(Auth::id());
        } else {
            CategoriaGastoService::validarCupoSubcategoria(Auth::id(), $padre->id);
        }

        CategoriaGastoService::validarNombreUnico(
            $validated['nombre'],
            $padre?->id,
            Auth::id()
        );

        $categoria = Categoria::create([
            // chk_categoria_propietario: una categoría propia siempre tiene
            // dueño y nunca es predeterminada.
            'usuario_id' => Auth::id(),
            'categoria_padre_id' => $padre?->id,
            'tipo' => 'GASTO',
            'nombre' => trim($validated['nombre']),
            'naturaleza_sugerida' => $validated['naturaleza_sugerida'] ?? null,
            'necesidad_sugerida' => $validated['necesidad_sugerida'] ?? null,
            'icono' => $validated['icono'] ?? null,
            'color_hex' => $validated['color_hex'] ?? null,
            'es_predeterminada' => false,
            'activa' => true,
            'creado_en' => now(),
        ]);

        BitacoraService::registrar(
            accion: $padre === null ? 'CREAR_CATEGORIA_GASTO' : 'CREAR_SUBCATEGORIA_GASTO',
            entidadAfectada: 'categorias',
            entidadId: $categoria->id,
            valorNuevo: $categoria->toArray(),
            usuarioId: Auth::id()
        );

        $mensaje = $padre === null
            ? 'Categoría creada exitosamente.'
            : 'Subcategoría creada exitosamente.';

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje, 'data' => $categoria], 201);
        }

        return redirect()->route('gastos.categorias.index')->with('status', $mensaje);
    }

    /**
     * RF-027 RN-027.3: "Renombrar una categoría actualiza su nombre en todos los
     * registros y reportes, incluidos los históricos."
     *
     * Los gastos guardan la llave foránea, no el nombre, así que el cambio se
     * refleja solo con actualizar la fila: no hay nada que propagar.
     *
     * El nivel no se puede mover: una subcategoría no se convierte en categoría
     * ni al revés, porque los gastos ya asociados dejarían de cumplir la
     * jerarquía de dos niveles de RN-026.1.
     */
    public function update(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $categoria = $this->categoriaPropia($id);

        $reglas = $this->reglas();
        unset($reglas['categoria_padre_id']);

        $validated = $request->validate($reglas, $this->mensajes());
        $esSubcategoria = $categoria->categoria_padre_id !== null;
        $this->validarSugerencias($validated, $esSubcategoria);

        CategoriaGastoService::validarNombreUnico(
            $validated['nombre'],
            $categoria->categoria_padre_id,
            Auth::id(),
            $categoria->id
        );

        $anterior = $categoria->toArray();

        $categoria->update([
            'nombre' => trim($validated['nombre']),
            'naturaleza_sugerida' => $validated['naturaleza_sugerida'] ?? null,
            'necesidad_sugerida' => $validated['necesidad_sugerida'] ?? null,
            'icono' => $validated['icono'] ?? null,
            'color_hex' => $validated['color_hex'] ?? null,
        ]);

        BitacoraService::registrar(
            accion: 'EDITAR_CATEGORIA_GASTO',
            entidadAfectada: 'categorias',
            entidadId: $categoria->id,
            valorAnterior: $anterior,
            valorNuevo: $categoria->fresh()->toArray(),
            justificacion: 'RN-027.3: el nombre queda actualizado en todos los registros históricos.',
            usuarioId: Auth::id()
        );

        $mensaje = 'Categoría actualizada. El cambio aplica a todos los registros históricos.';

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje, 'data' => $categoria->fresh()]);
        }

        return redirect()->route('gastos.categorias.index')->with('status', $mensaje);
    }

    /**
     * RF-027 RN-027.2: Una categoría con gastos asociados no se puede borrar,
     * pero sí inactivar. Una categoría inactiva no aparece al registrar gastos
     * nuevos; los históricos la conservan.
     */
    public function toggleActiva(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $categoria = $this->categoriaPropia($id);
        $nuevoEstado = ! $categoria->activa;

        $categoria->update(['activa' => $nuevoEstado]);

        BitacoraService::registrar(
            accion: $nuevoEstado ? 'ACTIVAR_CATEGORIA_GASTO' : 'INACTIVAR_CATEGORIA_GASTO',
            entidadAfectada: 'categorias',
            entidadId: $categoria->id,
            valorNuevo: ['activa' => $nuevoEstado],
            usuarioId: Auth::id()
        );

        $mensaje = $nuevoEstado
            ? 'Categoría activada.'
            : 'Categoría inactivada. No aparecerá al registrar gastos nuevos.';

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje, 'data' => $categoria->fresh()]);
        }

        return back()->with('status', $mensaje);
    }

    /**
     * RF-027 RN-027.2 / CA-027.1: "Al fusionar, el usuario elige el destino y
     * todos los gastos pasan a él." El origen desaparece.
     */
    public function fusionar(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $origen = $this->categoriaPropia($id);

        $validated = $request->validate([
            'destino_id' => ['required', 'uuid', Rule::notIn([$id])],
        ], [
            'destino_id.required' => 'Seleccione la categoría destino de la fusión.',
            'destino_id.uuid' => 'La categoría destino no es válida.',
            'destino_id.not_in' => 'El destino de la fusión debe ser una categoría distinta.',
        ]);

        // El destino puede ser propio o predeterminado, pero siempre visible
        // para el Usuario (RNG-01).
        $destino = Categoria::where('id', $validated['destino_id'])
            ->where('tipo', 'GASTO')
            ->where(fn ($q) => $q->where('usuario_id', Auth::id())->orWhere('es_predeterminada', true))
            ->first();

        if ($destino === null) {
            throw ValidationException::withMessages([
                'destino_id' => 'La categoría destino no existe o no está disponible.',
            ]);
        }

        $anterior = $origen->toArray();
        $reclasificados = CategoriaGastoService::fusionar($origen, $destino);

        BitacoraService::registrar(
            accion: 'FUSIONAR_CATEGORIA_GASTO',
            entidadAfectada: 'categorias',
            entidadId: $id,
            valorAnterior: $anterior,
            valorNuevo: [
                'destino_id' => $destino->id,
                'destino_nombre' => $destino->nombre,
                'gastos_reclasificados' => $reclasificados,
            ],
            justificacion: 'RN-027.2: fusión de categorías; los gastos pasan al destino elegido.',
            usuarioId: Auth::id()
        );

        $mensaje = sprintf(
            '«%s» se fusionó en «%s». %d gasto(s) quedaron reclasificados.',
            $anterior['nombre'],
            $destino->nombre,
            $reclasificados
        );

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => $mensaje,
                'data' => [
                    'destino' => $destino->fresh(),
                    'gastos_reclasificados' => $reclasificados,
                ],
            ]);
        }

        return redirect()->route('gastos.categorias.index')->with('status', $mensaje);
    }

    /**
     * RF-027 RN-027.2: "Una categoría o subcategoría con gastos asociados no se
     * puede borrar. Se puede inactivar o fusionar con otra."
     */
    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $categoria = $this->categoriaPropia($id);

        if (CategoriaGastoService::tieneGastos($categoria)) {
            throw ValidationException::withMessages([
                'categoria' => 'Esta categoría tiene gastos asociados y no se puede borrar. Puede inactivarla o fusionarla con otra.',
            ]);
        }

        $anterior = $categoria->toArray();
        $categoria->delete();

        BitacoraService::registrar(
            accion: 'ELIMINAR_CATEGORIA_GASTO',
            entidadAfectada: 'categorias',
            entidadId: $id,
            valorAnterior: $anterior,
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => 'Categoría eliminada.']);
        }

        return redirect()->route('gastos.categorias.index')->with('status', 'Categoría eliminada.');
    }

    /**
     * RN-027.1 / RN-027.4: Reglas de validación comunes a crear y editar.
     */
    private function reglas(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'min:'.CategoriaGastoService::MIN_LONGITUD_NOMBRE,
                'max:'.CategoriaGastoService::MAX_LONGITUD_NOMBRE,
            ],
            'categoria_padre_id' => ['nullable', 'uuid'],
            // RN-026.2: los valores sugeridos viven en la subcategoría.
            'naturaleza_sugerida' => ['nullable', Rule::in(Gasto::NATURALEZAS)],
            'necesidad_sugerida' => ['nullable', Rule::in(Gasto::NECESIDADES)],
            // RN-027.4: ícono y color de la paleta definida.
            'icono' => ['nullable', Rule::in(CategoriaGastoService::ICONOS)],
            'color_hex' => ['nullable', Rule::in(CategoriaGastoService::PALETA)],
        ];
    }

    /**
     * RN-027.1: Mensaje propio para cada regla. El proyecto no tiene archivos de
     * traducción, así que una regla sin mensaje acá sale al usuario como la clave
     * cruda (`validation.required`).
     */
    private function mensajes(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre de la categoría.',
            'nombre.string' => 'El nombre de la categoría debe ser texto.',
            'nombre.min' => 'El nombre debe tener entre '.CategoriaGastoService::MIN_LONGITUD_NOMBRE.' y '.CategoriaGastoService::MAX_LONGITUD_NOMBRE.' caracteres.',
            'nombre.max' => 'El nombre debe tener entre '.CategoriaGastoService::MIN_LONGITUD_NOMBRE.' y '.CategoriaGastoService::MAX_LONGITUD_NOMBRE.' caracteres.',
            'categoria_padre_id.uuid' => 'La categoría superior seleccionada no es válida.',
            'naturaleza_sugerida.in' => 'La naturaleza sugerida debe ser FIJO o VARIABLE.',
            'necesidad_sugerida.in' => 'La necesidad sugerida debe ser ESENCIAL o DISCRECIONAL.',
            'icono.in' => 'El ícono no pertenece al catálogo de íconos disponibles.',
            'color_hex.in' => 'El color no pertenece a la paleta definida.',
        ];
    }

    /**
     * RN-026.1 (1): Jerarquía de dos niveles. El padre debe ser una categoría de
     * gasto de primer nivel visible para el Usuario; si no se envía, lo que se
     * crea es una categoría de primer nivel.
     */
    private function padreValido(?string $padreId): ?Categoria
    {
        if ($padreId === null) {
            return null;
        }

        $padre = Categoria::where('id', $padreId)
            ->where('tipo', 'GASTO')
            ->whereNull('categoria_padre_id')
            ->where(fn ($q) => $q->where('usuario_id', Auth::id())->orWhere('es_predeterminada', true))
            ->first();

        if ($padre === null) {
            throw ValidationException::withMessages([
                'categoria_padre_id' => 'La categoría superior no existe o no está disponible.',
            ]);
        }

        return $padre;
    }

    /**
     * RN-026.2: "Cada subcategoría tiene valores sugeridos para Naturaleza y
     * Necesidad". Se exigen al crear o editar una subcategoría, para que
     * CA-026.1 se cumpla con cualquier subcategoría y no solo con las del
     * catálogo. En una categoría de primer nivel no aplican: la clasificación
     * de un gasto la determina su subcategoría.
     */
    private function validarSugerencias(array $validated, bool $esSubcategoria): void
    {
        $naturaleza = $validated['naturaleza_sugerida'] ?? null;
        $necesidad = $validated['necesidad_sugerida'] ?? null;

        if ($esSubcategoria) {
            if ($naturaleza === null || $necesidad === null) {
                throw ValidationException::withMessages([
                    'naturaleza_sugerida' => 'Indique la naturaleza y la necesidad sugeridas de la subcategoría: son las que precarga el formulario de gasto.',
                ]);
            }

            return;
        }

        if ($naturaleza !== null || $necesidad !== null) {
            throw ValidationException::withMessages([
                'naturaleza_sugerida' => 'Los valores sugeridos de naturaleza y necesidad se definen en la subcategoría, no en la categoría.',
            ]);
        }
    }

    /**
     * RF-027 / RNG-01: Solo se administran las categorías propias.
     *
     * Una categoría de otro usuario responde 404 y queda registrada en la
     * bitácora (RF-019). Una predeterminada existe y es visible, pero la
     * administra el Administrador (RF-015), así que se rechaza con un mensaje.
     */
    private function categoriaPropia(string $id): Categoria
    {
        $categoria = Categoria::where('id', $id)
            ->where('tipo', 'GASTO')
            ->where('usuario_id', Auth::id())
            ->first();

        if ($categoria !== null) {
            return $categoria;
        }

        $esPredeterminada = Categoria::where('id', $id)
            ->where('es_predeterminada', true)
            ->exists();

        if ($esPredeterminada) {
            throw ValidationException::withMessages([
                'categoria' => 'Las categorías predeterminadas las administra el Administrador (RF-015). Puede crear una categoría propia o fusionar la suya con esta.',
            ]);
        }

        $esDeOtro = Categoria::where('id', $id)->whereNotNull('usuario_id')->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'categorias',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }
}
