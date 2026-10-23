<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CategoriaCatalogoController extends Controller
{
    /**
     * RF-015: Listado del catálogo global de categorías predeterminadas
     */
    public function index(Request $request): View
    {
        $tipo = $request->query('tipo', 'GASTO');

        $categorias = Categoria::where('es_predeterminada', true)
            ->whereNull('categoria_padre_id')
            ->where('tipo', $tipo)
            ->with(['subcategorias' => fn ($q) => $q->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        return view('admin.categorias.index', compact('categorias', 'tipo'));
    }

    /**
     * RF-015 RN-015.4: Crear categoría o subcategoría global
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:40'],
            'tipo' => ['required', 'in:INGRESO,GASTO'],
            'categoria_padre_id' => ['nullable', 'exists:categorias,id'],
            'naturaleza_sugerida' => ['nullable', 'in:FIJO,VARIABLE'],
            'necesidad_sugerida' => ['nullable', 'in:ESENCIAL,DISCRECIONAL'],
            'icono' => ['nullable', 'string', 'max:40'],
            'color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $padreId = $validated['categoria_padre_id'] ?? null;
        $tipo = $validated['tipo'];
        $nombreLower = strtolower(trim($validated['nombre']));

        // Validar unicidad dentro del mismo nivel y tipo
        $existe = Categoria::where('es_predeterminada', true)
            ->where('tipo', $tipo)
            ->where(function ($q) use ($padreId) {
                if ($padreId) {
                    $q->where('categoria_padre_id', $padreId);
                } else {
                    $q->whereNull('categoria_padre_id');
                }
            })
            ->whereRaw('LOWER(nombre) = ?', [$nombreLower])
            ->exists();

        if ($existe) {
            return back()->withInput()->withErrors([
                'nombre' => 'Ya existe una categoría predeterminada con este nombre en este nivel.',
            ]);
        }

        $categoria = Categoria::create([
            'usuario_id' => null,
            'categoria_padre_id' => $padreId,
            'tipo' => $tipo,
            'nombre' => trim($validated['nombre']),
            'naturaleza_sugerida' => $validated['naturaleza_sugerida'] ?? null,
            'necesidad_sugerida' => $validated['necesidad_sugerida'] ?? null,
            'icono' => $validated['icono'] ?? null,
            'color_hex' => $validated['color_hex'] ?? null,
            'es_predeterminada' => true,
            'activa' => true,
            'creado_en' => now(),
        ]);

        BitacoraService::registrar(
            accion: 'CREAR_CATEGORIA_PREDETERMINADA',
            entidadAfectada: 'categorias',
            entidadId: $categoria->id,
            valorNuevo: $categoria->toArray(),
            usuarioId: Auth::id()
        );

        return back()->with('status', 'Categoría predeterminada agregada exitosamente.');
    }

    /**
     * RF-015 RN-015.3: Inactivar o activar categoría predeterminada (no se borra físicamente)
     */
    public function toggleActiva(string $id): RedirectResponse
    {
        $categoria = Categoria::where('es_predeterminada', true)->findOrFail($id);
        $nuevoEstado = !$categoria->activa;

        $categoria->update(['activa' => $nuevoEstado]);

        BitacoraService::registrar(
            accion: $nuevoEstado ? 'ACTIVAR_CATEGORIA_PREDETERMINADA' : 'INACTIVAR_CATEGORIA_PREDETERMINADA',
            entidadAfectada: 'categorias',
            entidadId: $categoria->id,
            valorNuevo: ['activa' => $nuevoEstado],
            usuarioId: Auth::id()
        );

        $msg = $nuevoEstado ? 'Categoría activada' : 'Categoría inactivada (los registros históricos la conservan).';
        return back()->with('status', $msg);
    }
}
