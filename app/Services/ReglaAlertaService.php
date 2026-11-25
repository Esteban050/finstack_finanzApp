<?php

namespace App\Services;

use App\Models\ReglaAlerta;
use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MOD-09 — RF-057 / RF-059: reglas de alerta del Usuario.
 *
 * Cada tipo tiene valores sugeridos en config('scfp.alertas.reglas'). El
 * Usuario solo guarda los tipos que cambia; los demás siguen con el valor
 * sugerido, así que una regla nueva aparece sin migrar datos.
 */
class ReglaAlertaService
{
    // Presupuesto
    public const UMBRAL_LINEA = 'UMBRAL_LINEA';

    public const PRESUPUESTO_TOTAL = 'PRESUPUESTO_TOTAL';

    public const PROYECCION_LINEA = 'PROYECCION_LINEA';

    // Ingresos frente a gastos
    public const INGRESOS_VS_GASTOS = 'INGRESOS_VS_GASTOS';

    public const GASTO_PROYECTADO_ALTO = 'GASTO_PROYECTADO_ALTO';

    public const LIMITE_GASTO_MENSUAL = 'LIMITE_GASTO_MENSUAL';

    // Hábitos de gasto
    public const GASTO_INUSUAL = 'GASTO_INUSUAL';

    public const GASTOS_HORMIGA = 'GASTOS_HORMIGA';

    public const GASTO_DISCRECIONAL_ALTO = 'GASTO_DISCRECIONAL_ALTO';

    // Topes de la DIAN
    public const TOPE_DIAN_TARJETA = 'TOPE_DIAN_TARJETA';

    public const TOPE_DIAN_COMPRAS = 'TOPE_DIAN_COMPRAS';

    // Cupo de la tarjeta de crédito (no es el tope de la DIAN)
    public const USO_CUPO_TARJETA = 'USO_CUPO_TARJETA';

    // Obligaciones y cuenta
    public const ENDEUDAMIENTO = 'ENDEUDAMIENTO';

    public const VENCIMIENTOS = 'VENCIMIENTOS';

    public const SEGURIDAD_CUENTA = 'SEGURIDAD_CUENTA';

    /** @return array<int, string> */
    public static function tipos(): array
    {
        return array_keys(config('scfp.alertas.reglas'));
    }

    /**
     * Reglas vigentes del Usuario: las suyas sobre los valores sugeridos.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function vigentes(string $usuarioId): array
    {
        $propias = ReglaAlerta::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->get()
            ->keyBy('tipo');

        $reglas = [];
        foreach (config('scfp.alertas.reglas') as $tipo => $defecto) {
            $editable = $defecto['editable'] ?? true;
            // RN-059.4: una regla no editable siempre usa su valor fijo.
            $propia = $editable ? ($propias[$tipo] ?? null) : null;

            $parametros = $propia ? $propia->parametros : $defecto['parametros'];
            if ($tipo === self::VENCIMIENTOS) {
                $parametros['dias_antes'] = min(7, (int) ($parametros['dias_antes'] ?? $defecto['parametros']['dias_antes']));
            }

            $reglas[$tipo] = array_filter([
                'tipo' => $tipo,
                'activa' => $propia ? $propia->activa : $defecto['activa'],
                'enviar_correo' => $propia ? $propia->enviar_correo : $defecto['enviar_correo'],
                'parametros' => $parametros,
                'personalizada' => $propia !== null,
                'editable' => $editable,
                'prioridad' => $defecto['prioridad'],
                'prioridad_desde_100' => $defecto['prioridad_desde_100'] ?? null,
            ], fn ($v) => $v !== null);
        }

        return $reglas;
    }

    /**
     * Prioridad de una alerta (RN-058.3): fija por tipo; en las reglas por
     * umbral, un umbral ≥ 100 % usa la prioridad mayor.
     */
    public static function prioridad(array $regla, ?int $umbral = null): string
    {
        if ($umbral !== null && $umbral >= 100 && isset($regla['prioridad_desde_100'])) {
            return $regla['prioridad_desde_100'];
        }

        return $regla['prioridad'];
    }

    /**
     * RF-057: guarda los tipos enviados. Los campos que no vengan conservan su
     * valor vigente. RN-059.4: las reglas no editables no se cambian.
     *
     * @param  array<int, array{tipo: string, activa?: bool, enviar_correo?: bool, parametros?: array}>  $cambios
     * @return array<string, array<string, mixed>> reglas vigentes después del cambio
     */
    public static function guardar(string $usuarioId, array $cambios): array
    {
        $vigentes = self::vigentes($usuarioId);
        $nuevas = [];
        $errores = [];

        foreach ($cambios as $i => $cambio) {
            $tipo = $cambio['tipo'];

            if (! $vigentes[$tipo]['editable']) {
                $errores["reglas.{$i}.tipo"] = 'Las alertas de seguridad de la cuenta siempre están activas y van por correo: no se pueden modificar (RN-059.4).';

                continue;
            }

            $parametros = array_key_exists('parametros', $cambio)
                ? self::normalizarParametros($tipo, (array) $cambio['parametros'], "reglas.{$i}.parametros", $errores)
                : $vigentes[$tipo]['parametros'];
            $activa = array_key_exists('activa', $cambio) ? (bool) $cambio['activa'] : $vigentes[$tipo]['activa'];

            // El tope mensual no tiene valor sugerido: activarla exige el monto.
            if ($tipo === self::LIMITE_GASTO_MENSUAL && $activa && ($parametros['monto'] ?? null) === null && ! isset($errores["reglas.{$i}.parametros.monto"])) {
                $errores["reglas.{$i}.parametros.monto"] = 'Indique el tope de gasto mensual para activar esta alerta.';
            }

            $nuevas[$tipo] = [
                'activa' => $activa,
                'enviar_correo' => array_key_exists('enviar_correo', $cambio) ? (bool) $cambio['enviar_correo'] : $vigentes[$tipo]['enviar_correo'],
                'parametros' => $parametros,
            ];
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        DB::transaction(function () use ($usuarioId, $nuevas, $vigentes) {
            foreach ($nuevas as $tipo => $valores) {
                ReglaAlerta::withoutGlobalScope(DelUsuarioAutenticado::class)->updateOrCreate(
                    ['usuario_id' => $usuarioId, 'tipo' => $tipo],
                    $valores
                );
            }

            BitacoraService::registrar(
                accion: 'EDITAR_REGLAS_ALERTA',
                entidadAfectada: 'reglas_alerta',
                valorAnterior: array_intersect_key($vigentes, $nuevas),
                valorNuevo: $nuevas,
                justificacion: 'RF-057: el Usuario ajustó sus reglas de alerta.',
                usuarioId: $usuarioId
            );
        });

        return self::vigentes($usuarioId);
    }

    /** RF-057: vuelve a los valores sugeridos borrando las reglas propias. */
    public static function restablecer(string $usuarioId): array
    {
        DB::transaction(function () use ($usuarioId) {
            $borradas = ReglaAlerta::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('usuario_id', $usuarioId)
                ->delete();

            BitacoraService::registrar(
                accion: 'RESTABLECER_REGLAS_ALERTA',
                entidadAfectada: 'reglas_alerta',
                valorNuevo: ['reglas_borradas' => $borradas],
                justificacion: 'RF-057: el Usuario restableció los valores sugeridos.',
                usuarioId: $usuarioId
            );
        });

        return self::vigentes($usuarioId);
    }

    /**
     * Valida y normaliza los parámetros de cada tipo.
     *
     * @param  array<string, string>  $errores
     */
    private static function normalizarParametros(string $tipo, array $parametros, string $campo, array &$errores): array
    {
        $cfg = config('scfp.alertas');

        return match ($tipo) {
            // RN-057.1: de 1 a 3 umbrales entre 50 % y 150 %.
            self::UMBRAL_LINEA, self::PRESUPUESTO_TOTAL => self::umbrales($parametros, "{$campo}.umbrales", $cfg['umbral_min'], $cfg['umbral_max'], $cfg['umbrales_max'], $errores),
            // RN-053.2: hasta 2 cortes del indicador, entre 10 % y 80 %.
            self::ENDEUDAMIENTO => self::umbrales($parametros, "{$campo}.umbrales", $cfg['endeudamiento_umbral_min'], $cfg['endeudamiento_umbral_max'], 2, $errores),
            // RN-057.3
            self::PROYECCION_LINEA => self::unEntero($parametros, 'umbral', $campo, $cfg['proyeccion_min'], $cfg['proyeccion_max'], '%', $errores),
            self::GASTO_PROYECTADO_ALTO => self::unEntero($parametros, 'umbral', $campo, 1, 200, '%', $errores),
            self::GASTO_DISCRECIONAL_ALTO, self::TOPE_DIAN_TARJETA, self::TOPE_DIAN_COMPRAS, self::USO_CUPO_TARJETA => self::unEntero($parametros, 'umbral', $campo, 1, 100, '%', $errores),
            self::GASTO_INUSUAL => self::unEntero($parametros, 'veces', $campo, 2, 20, 'veces', $errores),
            self::VENCIMIENTOS => self::unEntero($parametros, 'dias_antes', $campo, 0, 7, 'días', $errores),
            self::LIMITE_GASTO_MENSUAL => self::limiteMensual($parametros, $campo, $errores),
            self::GASTOS_HORMIGA => self::hormiga($parametros, $campo, $errores),
            default => [],
        };
    }

    private static function umbrales(array $parametros, string $campo, int $min, int $max, int $cantidadMax, array &$errores): array
    {
        $umbrales = $parametros['umbrales'] ?? null;

        if (! is_array($umbrales) || $umbrales === [] || count($umbrales) > $cantidadMax) {
            $errores[$campo] = "Indique de 1 a {$cantidadMax} umbrales.";

            return [];
        }

        $limpios = [];
        foreach ($umbrales as $umbral) {
            $entero = self::entero($umbral);

            if ($entero === null || $entero < $min || $entero > $max) {
                $errores[$campo] = "Cada umbral es un entero entre {$min} % y {$max} %.";

                return [];
            }
            $limpios[] = $entero;
        }

        if (count(array_unique($limpios)) !== count($limpios)) {
            $errores[$campo] = 'Los umbrales no se pueden repetir.';

            return [];
        }

        sort($limpios);

        return ['umbrales' => $limpios];
    }

    private static function unEntero(array $parametros, string $clave, string $campo, int $min, int $max, string $unidad, array &$errores): array
    {
        $entero = self::entero($parametros[$clave] ?? null);

        if ($entero === null || $entero < $min || $entero > $max) {
            $errores["{$campo}.{$clave}"] = "Indique un entero entre {$min} y {$max} ({$unidad}).";

            return [];
        }

        return [$clave => $entero];
    }

    private static function limiteMensual(array $parametros, string $campo, array &$errores): array
    {
        $monto = $parametros['monto'] ?? null;

        if ($monto === null) {
            return ['monto' => null];
        }

        if (! self::pesos($monto)) {
            $errores["{$campo}.monto"] = 'El tope va en pesos enteros mayores que $ 0, sin decimales ni separadores.';

            return [];
        }

        return ['monto' => (string) $monto];
    }

    private static function hormiga(array $parametros, string $campo, array &$errores): array
    {
        foreach (['monto_max', 'tope'] as $clave) {
            if (! self::pesos($parametros[$clave] ?? null)) {
                $errores["{$campo}.{$clave}"] = 'Va en pesos enteros mayores que $ 0, sin decimales ni separadores.';
            }
        }

        if (isset($errores["{$campo}.monto_max"]) || isset($errores["{$campo}.tope"])) {
            return [];
        }

        if (bccomp((string) $parametros['monto_max'], (string) $parametros['tope'], 0) >= 0) {
            $errores["{$campo}.monto_max"] = 'El monto de un gasto pequeño debe ser menor que el tope del mes.';

            return [];
        }

        return ['monto_max' => (int) $parametros['monto_max'], 'tope' => (int) $parametros['tope']];
    }

    private static function entero(mixed $valor): ?int
    {
        if (is_int($valor)) {
            return $valor;
        }

        return is_string($valor) && preg_match('/^\d{1,3}$/', $valor) ? (int) $valor : null;
    }

    private static function pesos(mixed $valor): bool
    {
        $texto = is_int($valor) ? (string) $valor : $valor;

        return is_string($texto) && preg_match('/^\d{1,12}$/', $texto) === 1 && ! preg_match('/^0+$/', $texto);
    }
}
