<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Gasto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RF-027 — Gestión de categorías y subcategorías de gasto personalizadas.
 *
 * El Usuario administra las suyas; las predeterminadas son del catálogo global
 * del Administrador (RF-015) y aquí solo se leen o se usan como destino de una
 * fusión.
 *
 * RNG-01: la tabla `categorias` no tiene global scope, porque las
 * predeterminadas no tienen dueño (`chk_categoria_propietario`). Todo filtro
 * por usuario_id va explícito en este servicio.
 */
class CategoriaGastoService
{
    /** RN-027.1: "Límites: 50 categorías y 30 subcategorías por categoría como máximo." */
    public const MAX_CATEGORIAS = 50;
    public const MAX_SUBCATEGORIAS = 30;

    /** RN-027.1: "Nombres de 2–40 caracteres, únicos en el mismo nivel." */
    public const MIN_LONGITUD_NOMBRE = 2;
    public const MAX_LONGITUD_NOMBRE = 40;

    /**
     * RN-027.4: "Cada categoría puede tener un ícono y un color (paleta
     * definida), que se usan en los gráficos."
     *
     * La paleta es la que ya usa el catálogo predeterminado de gastos
     * (RF-015 RN-015.1): limitar las categorías propias a esos mismos colores
     * mantiene coherente la leyenda de los gráficos.
     */
    public const PALETA = [
        '#2B6CB0', '#38A169', '#DD6B20', '#E53E3E', '#805AD5', '#D69E2E',
        '#319795', '#D53F8C', '#9F7AEA', '#C53030', '#4A5568', '#2C5282',
        '#285E61', '#B7791F', '#38B2AC', '#718096',
    ];

    /** RN-027.4: Íconos disponibles, los mismos del catálogo predeterminado. */
    public const ICONOS = [
        'home', 'utensils', 'car', 'heartbeat', 'graduation-cap', 'bolt',
        'wifi', 'film', 'tshirt', 'credit-card', 'landmark', 'user-shield',
        'umbrella', 'paw', 'hand-holding-heart', 'ellipsis-h',
    ];

    /**
     * RF-027: Categorías de gasto que ve el Usuario: las predeterminadas del
     * catálogo global más las propias, con sus subcategorías.
     *
     * RN-026.2: cada subcategoría viaja con sus valores sugeridos de naturaleza
     * y necesidad, que son los que precarga el formulario (CA-026.1).
     */
    public static function catalogo(string $usuarioId, bool $soloActivas = false): Collection
    {
        return Categoria::query()
            ->where('tipo', 'GASTO')
            ->whereNull('categoria_padre_id')
            ->when($soloActivas, fn ($q) => $q->where('activa', true))
            ->where(fn ($q) => $q->where('usuario_id', $usuarioId)->orWhere('es_predeterminada', true))
            ->with(['subcategorias' => function ($query) use ($usuarioId, $soloActivas) {
                $query->where('tipo', 'GASTO')
                    ->when($soloActivas, fn ($q) => $q->where('activa', true))
                    ->where(fn ($q) => $q->where('usuario_id', $usuarioId)->orWhere('es_predeterminada', true))
                    ->orderBy('nombre');
            }])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * RN-027.1 / CA-027.2: "Al intentar crear una categoría número 51, el
     * sistema muestra 'Alcanzaste el máximo de 50 categorías'."
     *
     * El cupo cuenta solo las categorías propias del Usuario. Las
     * predeterminadas son del catálogo del Administrador y el Usuario no puede
     * borrarlas: si consumieran cupo, el límite dependería de cuántas haya
     * sembradas y nunca sería el que anuncia el criterio.
     *
     * @throws ValidationException
     */
    public static function validarCupoCategoria(string $usuarioId): void
    {
        $propias = Categoria::where('usuario_id', $usuarioId)
            ->where('tipo', 'GASTO')
            ->whereNull('categoria_padre_id')
            ->count();

        if ($propias >= self::MAX_CATEGORIAS) {
            throw ValidationException::withMessages([
                'nombre' => 'Alcanzaste el máximo de '.self::MAX_CATEGORIAS.' categorías.',
            ]);
        }
    }

    /**
     * RN-027.1: 30 subcategorías por categoría como máximo. Igual que arriba,
     * el cupo cuenta solo las propias dentro de esa categoría.
     *
     * @throws ValidationException
     */
    public static function validarCupoSubcategoria(string $usuarioId, string $padreId): void
    {
        $propias = Categoria::where('usuario_id', $usuarioId)
            ->where('tipo', 'GASTO')
            ->where('categoria_padre_id', $padreId)
            ->count();

        if ($propias >= self::MAX_SUBCATEGORIAS) {
            throw ValidationException::withMessages([
                'nombre' => 'Alcanzaste el máximo de '.self::MAX_SUBCATEGORIAS.' subcategorías en esta categoría.',
            ]);
        }
    }

    /**
     * RN-027.1: "Nombres [...] únicos en el mismo nivel."
     *
     * Se compara contra lo que el Usuario ve en ese nivel —sus categorías y las
     * predeterminadas—, no solo contra las propias: el índice
     * uq_categorias_nombre_nivel solo cubre las propias, y permitir una
     * "Vivienda" propia junto a la "Vivienda" del catálogo dejaría dos entradas
     * indistinguibles en el mismo selector.
     *
     * @throws ValidationException
     */
    public static function validarNombreUnico(
        string $nombre,
        ?string $padreId,
        string $usuarioId,
        ?string $excluirId = null
    ): void {
        $existe = Categoria::where('tipo', 'GASTO')
            ->when($padreId === null,
                fn ($q) => $q->whereNull('categoria_padre_id'),
                fn ($q) => $q->where('categoria_padre_id', $padreId)
            )
            ->where(fn ($q) => $q->where('usuario_id', $usuarioId)->orWhere('es_predeterminada', true))
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($nombre))])
            ->when($excluirId !== null, fn ($q) => $q->whereKeyNot($excluirId))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'nombre' => $padreId === null
                    ? 'Ya existe una categoría de gasto con ese nombre.'
                    : 'Ya existe una subcategoría con ese nombre en esta categoría.',
            ]);
        }
    }

    /**
     * RN-027.2: "Una categoría o subcategoría con gastos asociados no se puede
     * borrar." Cuenta también los gastos eliminados lógicamente (RNG-07): el
     * registro sigue en la tabla para auditoría y la llave foránea
     * gastos_categoria_id_fkey es RESTRICT, así que el borrado fallaría igual.
     */
    public static function tieneGastos(Categoria $categoria): bool
    {
        $consulta = Gasto::withoutGlobalScopes()->withTrashed();

        if ($categoria->categoria_padre_id === null) {
            return $consulta->where('categoria_id', $categoria->id)->exists();
        }

        return $consulta->where('subcategoria_id', $categoria->id)->exists();
    }

    /**
     * RN-027.2 / CA-027.1: "Se puede inactivar o fusionar con otra; al fusionar,
     * el usuario elige el destino y todos los gastos pasan a él."
     *
     * "Al fusionar la subcategoría 'Café' en 'Restaurantes', todos los gastos de
     * 'Café' quedan en 'Restaurantes' y 'Café' desaparece."
     *
     * Fusionar una categoría de primer nivel arrastra sus subcategorías al
     * destino. Si el destino ya tiene una subcategoría con el mismo nombre, las
     * dos se fusionan entre sí en lugar de duplicarse: el índice
     * uq_categorias_nombre_nivel no admite dos iguales bajo el mismo padre.
     *
     * @return int Gastos reclasificados.
     *
     * @throws ValidationException
     */
    public static function fusionar(Categoria $origen, Categoria $destino): int
    {
        if ($origen->id === $destino->id) {
            throw ValidationException::withMessages([
                'destino_id' => 'El destino de la fusión debe ser una categoría distinta.',
            ]);
        }

        $mismoNivel = ($origen->categoria_padre_id === null) === ($destino->categoria_padre_id === null);

        if ($destino->tipo !== 'GASTO' || ! $mismoNivel) {
            throw ValidationException::withMessages([
                'destino_id' => 'El destino debe ser una categoría de gasto del mismo nivel que la de origen.',
            ]);
        }

        if (! $destino->activa) {
            throw ValidationException::withMessages([
                'destino_id' => 'El destino de la fusión debe estar activo.',
            ]);
        }

        return DB::transaction(function () use ($origen, $destino) {
            $reclasificados = $origen->categoria_padre_id === null
                ? self::fusionarRaiz($origen, $destino)
                : self::moverGastosDeSubcategoria($origen, $destino);

            $origen->delete();

            return $reclasificados;
        });
    }

    /**
     * RN-027.2: Fusión entre categorías de primer nivel. Las subcategorías de
     * origen pasan al destino; las que ya existen allí con el mismo nombre se
     * fusionan con su homónima.
     */
    private static function fusionarRaiz(Categoria $origen, Categoria $destino): int
    {
        $reclasificados = 0;

        $subcategoriasDestino = Categoria::where('categoria_padre_id', $destino->id)
            ->where('tipo', 'GASTO')
            ->get()
            ->keyBy(fn (Categoria $sub) => mb_strtolower($sub->nombre));

        $subcategoriasOrigen = Categoria::where('categoria_padre_id', $origen->id)
            ->where('tipo', 'GASTO')
            ->get();

        foreach ($subcategoriasOrigen as $sub) {
            $homonima = $subcategoriasDestino->get(mb_strtolower($sub->nombre));

            if ($homonima !== null) {
                $reclasificados += self::moverGastosDeSubcategoria($sub, $homonima);
                $sub->delete();

                continue;
            }

            // Se reparenta: los gastos ya apuntan a esta subcategoría, solo
            // cambia la categoría de primer nivel que los agrupa.
            $reclasificados += Gasto::withoutGlobalScopes()->withTrashed()
                ->where('subcategoria_id', $sub->id)
                ->update(['categoria_id' => $destino->id]);

            $sub->update(['categoria_padre_id' => $destino->id]);
        }

        // Gastos que apuntaban a la categoría de origen por una subcategoría ya
        // movida arriba quedan cubiertos; este update recoge cualquier resto.
        Gasto::withoutGlobalScopes()->withTrashed()
            ->where('categoria_id', $origen->id)
            ->update(['categoria_id' => $destino->id]);

        return $reclasificados;
    }

    /**
     * CA-027.1: Todos los gastos de la subcategoría de origen quedan en la de
     * destino, incluida la categoría de primer nivel que la contiene: el
     * destino puede estar bajo otro padre.
     */
    private static function moverGastosDeSubcategoria(Categoria $origen, Categoria $destino): int
    {
        return Gasto::withoutGlobalScopes()->withTrashed()
            ->where('subcategoria_id', $origen->id)
            ->update([
                'subcategoria_id' => $destino->id,
                'categoria_id' => $destino->categoria_padre_id,
            ]);
    }
}
