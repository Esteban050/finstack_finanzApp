<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Gasto;
use Illuminate\Validation\ValidationException;

/**
 * MOD-04 — Reglas de cálculo y clasificación de gastos (RF-025, RF-026).
 *
 * Toda la aritmética se hace con bcmath sobre cadenas: RNG-02 prohíbe el punto
 * flotante para montos y exige precisión decimal de 2 posiciones. Los tres
 * helpers de abajo son los mismos de IngresoService: implementan reglas
 * generales (RNG-02, RNG-09), no reglas de ingresos.
 */
class GastoService
{
    /** RN-026.1 (6): De 0 a 10 etiquetas por gasto, cada una de 2 a 30 caracteres. */
    public const MAX_ETIQUETAS = 10;
    public const MIN_LONGITUD_ETIQUETA = 2;
    public const MAX_LONGITUD_ETIQUETA = 30;

    /**
     * RN-025.1: Si la moneda no es COP se exige la tasa usada y se guarda el
     * monto convertido, que es el que alimenta todos los cálculos.
     *
     * @throws ValidationException
     */
    public static function prepararMontos(array $validated): array
    {
        $moneda = strtoupper($validated['moneda']);
        $montoOriginal = (string) $validated['monto_original'];

        if ($moneda === 'COP') {
            $tasa = '1.0000';
        } else {
            if (($validated['tasa_cambio_usada'] ?? null) === null) {
                throw ValidationException::withMessages([
                    'tasa_cambio_usada' => 'Indique la tasa de cambio usada para convertir a pesos colombianos.',
                ]);
            }
            $tasa = (string) $validated['tasa_cambio_usada'];
        }

        $montoCop = self::convertirACop($montoOriginal, $tasa);

        // RNG-09: el monto convertido también debe caber en el rango permitido.
        if (! self::montoEnRango($montoCop)) {
            throw ValidationException::withMessages([
                'monto_original' => 'El monto convertido a pesos supera el máximo permitido de $ 999.999.999.999,99.',
            ]);
        }

        return [
            'moneda' => $moneda,
            'monto_original' => $montoOriginal,
            'tasa_cambio_usada' => $tasa,
            'monto_cop' => $montoCop,
        ];
    }

    /**
     * RN-026.1 (1): "Categoría / subcategoría (jerarquía de 2 niveles,
     * obligatoria)". Verifica que la subcategoría sea hija de la categoría, que
     * ambas sean de gasto, que estén activas y que el Usuario pueda usarlas:
     * propias o predeterminadas del catálogo (RF-015).
     *
     * RNG-01: `categorias` no tiene global scope, porque las predeterminadas no
     * tienen dueño. El filtro por usuario_id va explícito acá.
     *
     * @throws ValidationException
     */
    public static function validarJerarquia(string $categoriaId, string $subcategoriaId, string $usuarioId): Categoria
    {
        $categoria = self::categoriaUsable($categoriaId, $usuarioId);

        if ($categoria === null || $categoria->categoria_padre_id !== null) {
            throw ValidationException::withMessages([
                'categoria_id' => 'La categoría no existe, está inactiva o no es una categoría de gasto de primer nivel.',
            ]);
        }

        $subcategoria = self::categoriaUsable($subcategoriaId, $usuarioId);

        if ($subcategoria === null || $subcategoria->categoria_padre_id !== $categoria->id) {
            throw ValidationException::withMessages([
                'subcategoria_id' => 'La subcategoría no existe, está inactiva o no pertenece a la categoría seleccionada.',
            ]);
        }

        return $subcategoria;
    }

    /**
     * RN-026.2: "Cada subcategoría tiene valores sugeridos para Naturaleza y
     * Necesidad, que el usuario puede cambiar en cada gasto."
     *
     * CA-026.1: al elegir "Arriendo", Naturaleza = Fijo y Necesidad = Esencial
     * se precargan. Si la peticion no las trae, se toma la sugerencia; si la
     * subcategoría no tiene sugerencia, se exige el dato porque RN-026.1 las
     * declara obligatorias.
     *
     * @throws ValidationException
     */
    public static function aplicarSugerencias(Categoria $subcategoria, array $validated): array
    {
        $naturaleza = $validated['naturaleza'] ?? $subcategoria->naturaleza_sugerida;
        $necesidad = $validated['necesidad'] ?? $subcategoria->necesidad_sugerida;

        if ($naturaleza === null) {
            throw ValidationException::withMessages([
                'naturaleza' => 'Indique la naturaleza del gasto: FIJO o VARIABLE. La subcategoría seleccionada no tiene un valor sugerido.',
            ]);
        }

        if ($necesidad === null) {
            throw ValidationException::withMessages([
                'necesidad' => 'Indique la necesidad del gasto: ESENCIAL o DISCRECIONAL. La subcategoría seleccionada no tiene un valor sugerido.',
            ]);
        }

        return [
            'naturaleza' => $naturaleza,
            'necesidad' => $necesidad,
        ];
    }

    /**
     * RN-026.1 (5): "Relevancia tributaria: No deducible / Potencialmente
     * deducible, con subtipo: [...] (por defecto 'No deducible')".
     *
     * El subtipo solo tiene sentido en un gasto potencialmente deducible. La
     * base lo exige con el constraint chk_gasto_deduccion_coherencia; acá se
     * valida antes para dar un mensaje en lugar de un error de Postgres.
     *
     * @throws ValidationException
     */
    public static function validarRelevancia(array $validated): array
    {
        $relevancia = $validated['relevancia_tributaria'] ?? 'NO_DEDUCIBLE';
        $subtipo = $validated['subtipo_deduccion'] ?? null;

        if ($relevancia === 'POTENCIALMENTE_DEDUCIBLE' && $subtipo === null) {
            throw ValidationException::withMessages([
                'subtipo_deduccion' => 'Indique el subtipo de deducción: intereses de vivienda, medicina prepagada, dependientes, aportes voluntarios a pensión o AFC, GMF u otros.',
            ]);
        }

        if ($relevancia === 'NO_DEDUCIBLE' && $subtipo !== null) {
            throw ValidationException::withMessages([
                'subtipo_deduccion' => 'Un gasto no deducible no lleva subtipo de deducción.',
            ]);
        }

        return [
            'relevancia_tributaria' => $relevancia,
            'subtipo_deduccion' => $subtipo,
        ];
    }

    /**
     * RN-026.1 (6): "Etiquetas libres: de 0 a 10 por gasto, cada una de 2–30
     * caracteres, reutilizables (por ejemplo, 'viaje-cartagena')."
     *
     * CA-026.3: una etiqueta de más de 30 caracteres, o una undécima etiqueta,
     * se rechaza con un mensaje que indica el límite.
     *
     * Se normalizan a minúsculas, sin espacios al borde y sin repetidas: así
     * "Viaje-Cartagena" y "viaje-cartagena" son la misma etiqueta y se pueden
     * reutilizar de verdad entre gastos.
     *
     * @throws ValidationException
     */
    public static function normalizarEtiquetas(mixed $etiquetas): array
    {
        if ($etiquetas === null || $etiquetas === '') {
            return [];
        }

        if (! is_array($etiquetas)) {
            throw ValidationException::withMessages([
                'etiquetas' => 'Las etiquetas deben enviarse como una lista.',
            ]);
        }

        $normalizadas = [];

        foreach ($etiquetas as $etiqueta) {
            if (! is_string($etiqueta) && ! is_numeric($etiqueta)) {
                throw ValidationException::withMessages([
                    'etiquetas' => 'Cada etiqueta debe ser texto.',
                ]);
            }

            $limpia = mb_strtolower(trim((string) $etiqueta));

            if ($limpia === '') {
                continue;
            }

            $longitud = mb_strlen($limpia);

            if ($longitud < self::MIN_LONGITUD_ETIQUETA || $longitud > self::MAX_LONGITUD_ETIQUETA) {
                throw ValidationException::withMessages([
                    'etiquetas' => sprintf(
                        'La etiqueta "%s" tiene %d caracteres. Cada etiqueta debe tener entre %d y %d caracteres.',
                        $limpia,
                        $longitud,
                        self::MIN_LONGITUD_ETIQUETA,
                        self::MAX_LONGITUD_ETIQUETA
                    ),
                ]);
            }

            if (! in_array($limpia, $normalizadas, true)) {
                $normalizadas[] = $limpia;
            }
        }

        if (count($normalizadas) > self::MAX_ETIQUETAS) {
            throw ValidationException::withMessages([
                'etiquetas' => sprintf(
                    'Un gasto admite como máximo %d etiquetas y se enviaron %d.',
                    self::MAX_ETIQUETAS,
                    count($normalizadas)
                ),
            ]);
        }

        return $normalizadas;
    }

    /**
     * RN-025.2: "Si el medio de pago es 'Tarjeta de crédito', el monto se
     * acumula en el indicador 'Consumos con tarjeta de crédito' del año
     * (RF-044). Todo gasto se acumula en 'Compras y consumos totales'."
     *
     * RN-026.3: el acumulado de potencialmente deducibles es lo que consolidaría
     * el resumen tributario anual (RF-043), que no está especificado en la
     * versión 1.0; se expone acá para que el dato exista.
     *
     * CA-028.2: un gasto con monto estimado no entra en los acumulados hasta que
     * se confirma. Los pendientes se informan aparte para que no queden ocultos.
     *
     * RNG-01: la consulta pasa por el global scope del modelo, así que ya viene
     * filtrada por el usuario en sesión.
     */
    public static function indicadoresAnuales(int $anio): array
    {
        $confirmados = Gasto::query()
            ->confirmados()
            ->whereRaw('EXTRACT(YEAR FROM fecha) = ?', [$anio])
            ->selectRaw("
                COUNT(*) AS cantidad,
                COALESCE(SUM(monto_cop), 0) AS compras_y_consumos_totales,
                COALESCE(SUM(CASE WHEN medio_pago = 'TARJETA_CREDITO' THEN monto_cop ELSE 0 END), 0) AS consumos_tarjeta_credito,
                COALESCE(SUM(CASE WHEN relevancia_tributaria = 'POTENCIALMENTE_DEDUCIBLE' THEN monto_cop ELSE 0 END), 0) AS potencialmente_deducible
            ")
            ->first();

        $pendientes = Gasto::query()
            ->where('estado', 'PENDIENTE')
            ->whereRaw('EXTRACT(YEAR FROM fecha) = ?', [$anio])
            ->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(monto_cop), 0) AS monto')
            ->first();

        return [
            'anio' => $anio,
            'cantidad' => (int) $confirmados->cantidad,
            'compras_y_consumos_totales' => (string) $confirmados->compras_y_consumos_totales,
            'consumos_tarjeta_credito' => (string) $confirmados->consumos_tarjeta_credito,
            'potencialmente_deducible' => (string) $confirmados->potencialmente_deducible,
            'pendientes' => [
                'cantidad' => (int) $pendientes->cantidad,
                'monto' => (string) $pendientes->monto,
            ],
        ];
    }

    /**
     * RN-026.1 (6): Etiquetas ya usadas por el Usuario, para reutilizarlas.
     *
     * @return array<int, string>
     */
    public static function etiquetasUsadas(): array
    {
        $filas = Gasto::query()
            ->selectRaw('DISTINCT UNNEST(etiquetas) AS etiqueta')
            ->orderBy('etiqueta')
            ->get();

        return $filas->pluck('etiqueta')->all();
    }

    /**
     * RN-025.1 / RNG-02: Conversión a pesos, redondeada a 2 decimales.
     */
    public static function convertirACop(string $monto, string $tasaCambio): string
    {
        return self::redondear(bcmul($monto, $tasaCambio, 6));
    }

    /**
     * RNG-02: Redondeo a 2 decimales, mitad hacia arriba. Todos los montos del
     * dominio son positivos (CHECK gastos_monto_original_check > 0).
     */
    public static function redondear(string $valor): string
    {
        return bcadd($valor, '0.005', 2);
    }

    /**
     * RNG-09: Un monto debe ser mayor que 0 y menor o igual a 999.999.999.999,99.
     */
    public static function montoEnRango(string $monto): bool
    {
        return bccomp($monto, '0', 2) === 1
            && bccomp($monto, '999999999999.99', 2) <= 0;
    }

    /**
     * RF-027 / RNG-01: Una categoría de gasto activa que el Usuario puede usar:
     * propia o predeterminada del catálogo global (RF-015).
     */
    public static function categoriaUsable(string $id, string $usuarioId): ?Categoria
    {
        return Categoria::where('id', $id)
            ->where('tipo', 'GASTO')
            ->where('activa', true)
            ->where(function ($query) use ($usuarioId) {
                $query->where('usuario_id', $usuarioId)
                    ->orWhere('es_predeterminada', true);
            })
            ->first();
    }
}
