<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\LineaPresupuesto;
use App\Models\PlantillaRecurrencia;
use App\Models\Presupuesto;
use App\Models\Scopes\DelUsuarioAutenticado;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MOD-09 — RF-055 / RF-056: presupuesto mensual por categoría y su ejecución.
 *
 * Entrega los datos del cálculo, no su presentación: el estado de cada línea
 * (RN-056.2), los colores y los textos los maneja el frontend.
 *
 * Todas las consultas filtran por usuario_id explícito y sin el global scope,
 * para que sirvan igual desde una petición web y desde el comando programado.
 */
class PresupuestoService
{
    /** Meses de historia para el promedio (RN-055.3) y el gasto habitual (RF-056). */
    public const MESES_HISTORIA = 3;

    public const METODO_HISTORICO = 'HISTORICO';

    public const METODO_LINEAL = 'LINEAL';

    public const ADV_TOTAL_SUPERA_INGRESO = 'TOTAL_SUPERA_INGRESO';

    public const ADV_LINEA_BAJO_PROMEDIO = 'LINEA_BAJO_PROMEDIO';

    /*
    |--------------------------------------------------------------------------
    | Mes y presupuesto
    |--------------------------------------------------------------------------
    */

    /** "2026-11" → 01/11/2026. */
    public static function parsearMes(string $mes): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $mes)->startOfMonth();
    }

    public static function mesActual(?CarbonImmutable $hoy = null): CarbonImmutable
    {
        return ($hoy ?? CarbonImmutable::today())->startOfMonth();
    }

    public static function delMes(string $usuarioId, CarbonImmutable $mes): ?Presupuesto
    {
        return Presupuesto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('mes', $mes->toDateString())
            ->first();
    }

    /**
     * RN-055.4 / H09: ingreso promedio único (EndeudamientoService::ingresoPromedio: 3 meses cerrados con ingresos, o el mes en curso si no bastan);
     * el mismo del indicador de endeudamiento (RF-053), Notificaciones, Reportes y Créditos.
     */
    public static function ingresoBase(string $usuarioId, CarbonImmutable $mes): string
    {
        return EndeudamientoService::ingresoPromedio($usuarioId, $mes);
    }

    /** Monto de una línea en % del ingreso: porcentaje × base / 100, al peso. */
    public static function montoPorcentaje(string $porcentaje, string $base): string
    {
        return CreditoService::alPeso(bcdiv(bcmul($porcentaje, $base, 10), '100', 10));
    }

    /**
     * RN-055.1: presupuesto del mes actual. Si no existe y el mes
     * inmediatamente anterior pide repetirse, se genera copiándolo. Solo se
     * mira el mes anterior: si el Usuario borró ese presupuesto, la repetición
     * se corta y no se revive uno más antiguo.
     *
     * Si existe pero se creó antes de empezar el mes, fija su base una vez.
     */
    public static function asegurarMes(string $usuarioId, ?CarbonImmutable $hoy = null): ?Presupuesto
    {
        $mes = self::mesActual($hoy);
        $actual = self::delMes($usuarioId, $mes);

        if ($actual !== null) {
            if (! $actual->base_fijada) {
                self::fijarBase($actual);
            }

            return $actual;
        }

        $anterior = self::delMes($usuarioId, $mes->subMonthNoOverflow());

        if ($anterior === null || ! $anterior->repetir_cada_mes) {
            return null;
        }

        try {
            return self::generarDesde($anterior, $mes);
        } catch (UniqueConstraintViolationException) {
            // Otra petición (o el comando) lo generó al mismo tiempo.
            return self::delMes($usuarioId, $mes);
        }
    }

    /**
     * RN-055.1 / RN-055.5: copia las líneas del presupuesto anterior al mes
     * indicado. Las líneas en % se recalculan con la base nueva (si no hubo
     * ingresos conservan el monto anterior); con traslado, el saldo no usado
     * (asignado + trasladado − gastado, si es positivo) pasa a la misma línea.
     * Las categorías inactivas no se copian.
     */
    public static function generarDesde(Presupuesto $anterior, CarbonImmutable $mes): Presupuesto
    {
        return DB::transaction(function () use ($anterior, $mes) {
            $usuarioId = $anterior->usuario_id;
            $base = self::ingresoBase($usuarioId, $mes);
            $lineasAnteriores = self::lineasDe($anterior);
            $gastadoAnterior = $anterior->trasladar_saldo
                ? self::agrupar($usuarioId, $anterior->mes->toImmutable(), $anterior->mes->toImmutable()->endOfMonth())
                : [];

            $nuevo = Presupuesto::withoutGlobalScope(DelUsuarioAutenticado::class)->create([
                'usuario_id' => $usuarioId,
                'mes' => $mes->toDateString(),
                'repetir_cada_mes' => $anterior->repetir_cada_mes,
                'trasladar_saldo' => $anterior->trasladar_saldo,
                'ingreso_base' => $base,
                'base_fijada' => true,
            ]);

            foreach ($lineasAnteriores as $linea) {
                if (! $linea['categoria']->activa) {
                    continue;
                }

                $modelo = $linea['modelo'];
                $monto = self::montoDeLinea($modelo->modo_asignacion, $modelo->porcentaje_ingreso, (string) $modelo->monto_asignado, $base);

                $saldo = '0';
                if ($anterior->trasladar_saldo) {
                    [$gastado] = self::sumarLinea($gastadoAnterior, $linea);
                    $disponible = bcsub(bcadd((string) $modelo->monto_asignado, (string) $modelo->saldo_trasladado, 2), $gastado, 2);
                    $saldo = bccomp($disponible, '0', 2) === 1 ? $disponible : '0';
                }

                LineaPresupuesto::create([
                    'presupuesto_id' => $nuevo->id,
                    'categoria_id' => $modelo->categoria_id,
                    'modo_asignacion' => $modelo->modo_asignacion,
                    'porcentaje_ingreso' => $modelo->porcentaje_ingreso,
                    'monto_asignado' => $monto,
                    'saldo_trasladado' => $saldo,
                ]);
            }

            BitacoraService::registrar(
                accion: 'GENERAR_PRESUPUESTO',
                entidadAfectada: 'presupuestos',
                entidadId: $nuevo->id,
                valorNuevo: ['mes' => $mes->format('Y-m'), 'copiado_de' => $anterior->mes->format('Y-m')],
                justificacion: 'RN-055.1: repetición del presupuesto del mes anterior.',
                usuarioId: $usuarioId
            );

            return $nuevo;
        });
    }

    /**
     * Un presupuesto creado antes de empezar su mes tenía una base provisional
     * (con un mes incompleto). Al empezar el mes se recalculan la base y las
     * líneas en %, una sola vez: desde ahí quedan fijas todo el mes.
     */
    public static function fijarBase(Presupuesto $presupuesto): void
    {
        DB::transaction(function () use ($presupuesto) {
            $base = self::ingresoBase($presupuesto->usuario_id, $presupuesto->mes->toImmutable());

            foreach ($presupuesto->lineas()->where('modo_asignacion', LineaPresupuesto::PORC_INGRESO)->get() as $linea) {
                $linea->update([
                    'monto_asignado' => self::montoDeLinea($linea->modo_asignacion, $linea->porcentaje_ingreso, (string) $linea->monto_asignado, $base),
                ]);
            }

            $presupuesto->update(['ingreso_base' => $base, 'base_fijada' => true]);
        });
    }

    /**
     * Comando diario (RN-055.1): genera el mes de quienes repiten y fija la
     * base de los presupuestos creados por adelantado. Idempotente.
     *
     * @return array{generados: int, bases_fijadas: int}
     */
    public static function procesarInicioDeMes(?CarbonImmutable $hoy = null): array
    {
        $mes = self::mesActual($hoy);
        $generados = 0;
        $fijadas = 0;

        $sinFijar = Presupuesto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('mes', $mes->toDateString())
            ->where('base_fijada', false)
            ->get();

        foreach ($sinFijar as $presupuesto) {
            self::fijarBase($presupuesto);
            $fijadas++;
        }

        $aRepetir = Presupuesto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('mes', $mes->subMonthNoOverflow()->toDateString())
            ->where('repetir_cada_mes', true)
            ->whereNotExists(function ($query) use ($mes) {
                $query->selectRaw('1')
                    ->from('presupuestos AS p2')
                    ->whereColumn('p2.usuario_id', 'presupuestos.usuario_id')
                    ->where('p2.mes', $mes->toDateString());
            })
            ->get();

        foreach ($aRepetir as $anterior) {
            try {
                self::generarDesde($anterior, $mes);
                $generados++;
            } catch (UniqueConstraintViolationException) {
                // Lo generó una petición del Usuario mientras corría el comando.
            }
        }

        return ['generados' => $generados, 'bases_fijadas' => $fijadas];
    }

    /*
    |--------------------------------------------------------------------------
    | RF-055: configurar el presupuesto
    |--------------------------------------------------------------------------
    */

    /**
     * RF-055: crea o reemplaza el presupuesto del mes con sus líneas.
     *
     * `$lineas` ya viene validado en forma por el controlador; aquí se validan
     * las reglas que dependen de la base: categoría usable, CA-055.2 (una
     * línea por categoría) y RN-055.2 (padre e hija no a la vez). El saldo
     * trasladado de una categoría que sigue en el presupuesto se conserva.
     *
     * @param  array<int, array{categoria_id: string, modo_asignacion: string, monto_asignado?: string|null, porcentaje_ingreso?: string|null}>  $lineas
     * @return array{presupuesto: Presupuesto, creado: bool, advertencias: array<int, array<string, mixed>>}
     */
    public static function guardar(string $usuarioId, CarbonImmutable $mes, bool $repetir, bool $trasladar, array $lineas, ?CarbonImmutable $hoy = null): array
    {
        $categorias = self::validarLineas($usuarioId, $lineas);
        $mesActual = self::mesActual($hoy);

        return DB::transaction(function () use ($usuarioId, $mes, $repetir, $trasladar, $lineas, $categorias, $mesActual) {
            $existente = self::delMes($usuarioId, $mes);
            $anterior = $existente ? self::resumenParaBitacora($existente) : null;

            // RN-055.4: la base se fija al empezar el mes. Mientras el mes no
            // empieza se recalcula en cada guardado.
            $base = ($existente && $existente->base_fijada)
                ? (string) $existente->ingreso_base
                : self::ingresoBase($usuarioId, $mes);

            foreach ($lineas as $i => $linea) {
                if ($linea['modo_asignacion'] === LineaPresupuesto::PORC_INGRESO && bccomp($base, '0', 2) !== 1) {
                    throw ValidationException::withMessages([
                        "lineas.{$i}.porcentaje_ingreso" => 'No hay ingresos en los 3 meses anteriores: asigne esta línea en pesos.',
                    ]);
                }
            }

            $saldos = $existente
                ? $existente->lineas()->pluck('saldo_trasladado', 'categoria_id')->all()
                : [];

            $atributos = [
                'repetir_cada_mes' => $repetir,
                'trasladar_saldo' => $trasladar,
                'ingreso_base' => $base,
                'base_fijada' => $mes->lessThanOrEqualTo($mesActual),
            ];

            if ($existente) {
                $existente->update($atributos);
                $existente->lineas()->delete();
                $presupuesto = $existente;
            } else {
                $presupuesto = Presupuesto::withoutGlobalScope(DelUsuarioAutenticado::class)->create([
                    'usuario_id' => $usuarioId,
                    'mes' => $mes->toDateString(),
                    ...$atributos,
                ]);
            }

            foreach ($lineas as $linea) {
                $porcentaje = $linea['modo_asignacion'] === LineaPresupuesto::PORC_INGRESO
                    ? (string) $linea['porcentaje_ingreso']
                    : null;

                LineaPresupuesto::create([
                    'presupuesto_id' => $presupuesto->id,
                    'categoria_id' => $linea['categoria_id'],
                    'modo_asignacion' => $linea['modo_asignacion'],
                    'porcentaje_ingreso' => $porcentaje,
                    'monto_asignado' => self::montoDeLinea($linea['modo_asignacion'], $porcentaje, (string) ($linea['monto_asignado'] ?? '0'), $base),
                    'saldo_trasladado' => (string) ($saldos[$linea['categoria_id']] ?? '0'),
                ]);
            }

            $presupuesto->refresh();

            BitacoraService::registrar(
                accion: $existente ? 'EDITAR_PRESUPUESTO' : 'CREAR_PRESUPUESTO',
                entidadAfectada: 'presupuestos',
                entidadId: $presupuesto->id,
                valorAnterior: $anterior,
                valorNuevo: self::resumenParaBitacora($presupuesto),
                justificacion: 'RF-055: configuración del presupuesto del mes.',
                usuarioId: $usuarioId
            );

            return [
                'presupuesto' => $presupuesto,
                'creado' => $existente === null,
                'advertencias' => self::advertencias($presupuesto, $categorias),
            ];
        });
    }

    /** RF-055: borra el presupuesto del mes (es un plan, no un registro financiero). */
    public static function eliminar(Presupuesto $presupuesto): void
    {
        DB::transaction(function () use ($presupuesto) {
            $anterior = self::resumenParaBitacora($presupuesto);
            $presupuesto->delete();

            BitacoraService::registrar(
                accion: 'ELIMINAR_PRESUPUESTO',
                entidadAfectada: 'presupuestos',
                entidadId: $presupuesto->id,
                valorAnterior: $anterior,
                justificacion: 'RF-055: el Usuario eliminó el presupuesto del mes.',
                usuarioId: $presupuesto->usuario_id
            );
        });
    }

    /**
     * RF-027 / CA-055.2 / RN-055.2: cada categoría es de gasto, activa y del
     * Usuario o del catálogo; no se repite; y una subcategoría no convive con
     * su categoría padre.
     *
     * @return array<string, Categoria> categorías por id
     */
    public static function validarLineas(string $usuarioId, array $lineas): array
    {
        $categorias = [];
        $errores = [];

        foreach ($lineas as $i => $linea) {
            $id = $linea['categoria_id'];

            if (isset($categorias[$id])) {
                $errores["lineas.{$i}.categoria_id"] = "{$categorias[$id]->nombre} ya tiene una línea en este periodo.";

                continue;
            }

            $categoria = GastoService::categoriaUsable($id, $usuarioId);

            if ($categoria === null) {
                $errores["lineas.{$i}.categoria_id"] = 'La categoría no existe, no es de gasto o está inactiva.';

                continue;
            }

            $categorias[$id] = $categoria;
        }

        foreach ($lineas as $i => $linea) {
            $categoria = $categorias[$linea['categoria_id']] ?? null;

            if ($categoria?->categoria_padre_id !== null && isset($categorias[$categoria->categoria_padre_id])) {
                $errores["lineas.{$i}.categoria_id"] ??= "No se puede presupuestar {$categoria->nombre} porque su categoría padre ({$categorias[$categoria->categoria_padre_id]->nombre}) ya tiene línea (RN-055.2).";
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return $categorias;
    }

    /**
     * RN-055.4 y aviso de línea bajo el promedio. Códigos con sus cifras, sin
     * textos: el frontend arma el mensaje.
     *
     * @param  array<string, Categoria>  $categorias
     * @return array<int, array<string, mixed>>
     */
    private static function advertencias(Presupuesto $presupuesto, array $categorias): array
    {
        $advertencias = [];
        $total = (string) $presupuesto->lineas()->sum('monto_asignado');
        $base = (string) $presupuesto->ingreso_base;

        if (bccomp($base, '0', 2) === 1 && bccomp($total, $base, 2) === 1) {
            $advertencias[] = [
                'codigo' => self::ADV_TOTAL_SUPERA_INGRESO,
                'total_asignado' => CreditoService::alPeso($total),
                'ingreso_base' => CreditoService::alPeso($base),
                'diferencia' => CreditoService::alPeso(bcsub($total, $base, 2)),
            ];
        }

        $umbral = (string) config('scfp.presupuestos.aviso_linea_bajo_promedio_porc');
        $promedios = self::promediosHistoricos($presupuesto->usuario_id, $presupuesto->mes->toImmutable());

        foreach (self::lineasDe($presupuesto) as $linea) {
            $clave = $linea['es_sub'] ? 'sub:'.$linea['categoria_id'] : 'cat:'.$linea['categoria_id'];
            $promedio = $promedios[$clave]['promedio'] ?? '0';

            if (bccomp($promedio, '0', 0) !== 1) {
                continue;
            }

            $asignado = (string) $linea['modelo']->monto_asignado;
            // asignado < promedio × (1 − umbral / 100)
            $limite = bcmul($promedio, bcsub('1', bcdiv($umbral, '100', 10), 10), 10);

            if (bccomp($asignado, $limite, 10) === -1) {
                $advertencias[] = [
                    'codigo' => self::ADV_LINEA_BAJO_PROMEDIO,
                    'categoria_id' => $linea['categoria_id'],
                    'monto_asignado' => CreditoService::alPeso($asignado),
                    'promedio_3_meses' => $promedio,
                    'diferencia' => CreditoService::alPeso(bcsub($promedio, $asignado, 2)),
                    'diferencia_porc' => self::porcentaje(bcsub($promedio, $asignado, 2), $promedio),
                ];
            }
        }

        return $advertencias;
    }

    /*
    |--------------------------------------------------------------------------
    | RF-056: ejecución y proyección
    |--------------------------------------------------------------------------
    */

    /**
     * RF-056 / RN-056.1: ejecución del presupuesto por línea y total, con la
     * proyección al cierre y el gasto en categorías sin línea.
     *
     * Proyección (acordada con el cliente, reemplaza la lineal de RN-056.1):
     * gastado + recurrentes pendientes + gasto habitual del resto del mes.
     * El habitual es el promedio, en los meses de historia, de lo gastado
     * después del día de corte sin contar los gastos de plantilla (esos ya
     * están en "pendientes"). Sin historia se extrapola lo gastado (lineal).
     *
     * @return array<string, mixed>
     */
    public static function ejecucion(Presupuesto $presupuesto, ?CarbonImmutable $hoy = null): array
    {
        $usuarioId = $presupuesto->usuario_id;
        $ctx = self::contexto($presupuesto->mes->toImmutable(), $hoy);
        $lineas = self::lineasDe($presupuesto);
        $calculo = self::calcularLineas($usuarioId, $ctx, $lineas);

        $filas = [];
        $totales = array_fill_keys(['monto_asignado', 'saldo_trasladado', 'gastado', 'pendientes_recurrentes', 'gasto_habitual', 'proyeccion'], '0');
        $cantidadTotal = 0;

        foreach ($lineas as $linea) {
            $fila = self::filaLinea($linea, $calculo[$linea['modelo']->id]);
            $filas[] = $fila;
            $cantidadTotal += $fila['cantidad_gastos'];

            foreach ($totales as $campo => $valor) {
                $totales[$campo] = bcadd($valor, $fila[$campo], 0);
            }
        }

        $asignadoTotal = bcadd($totales['monto_asignado'], $totales['saldo_trasladado'], 0);
        usort($filas, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return [
            'presupuesto_id' => $presupuesto->id,
            ...self::datosMes($presupuesto, $ctx),
            'metodo_proyeccion' => $calculo['_metodo'],
            'meses_historia' => $calculo['_meses_historia'],
            'totales' => [
                ...$totales,
                'cantidad_gastos' => $cantidadTotal,
                'disponible' => bcsub($asignadoTotal, $totales['gastado'], 0),
                'ejecucion_porc' => self::porcentaje($totales['gastado'], $asignadoTotal),
                'proyeccion_porc' => self::porcentaje($totales['proyeccion'], $asignadoTotal),
            ],
            'lineas' => $filas,
            'fuera_del_presupuesto' => [
                'mes' => self::fueraDelPresupuesto($usuarioId, $ctx['inicio'], $lineas),
                'mes_anterior' => self::fueraDelPresupuesto($usuarioId, $ctx['inicio']->subMonthNoOverflow(), $lineas),
            ],
        ];
    }

    /**
     * RF-056: detalle de una línea con la serie diaria del gasto acumulado
     * (real hasta el día de corte y proyectado desde ahí) y sus últimos gastos.
     *
     * @return array<string, mixed>
     */
    public static function detalleLinea(Presupuesto $presupuesto, LineaPresupuesto $modelo, ?CarbonImmutable $hoy = null): array
    {
        $usuarioId = $presupuesto->usuario_id;
        $ctx = self::contexto($presupuesto->mes->toImmutable(), $hoy);
        $linea = self::describirLinea($modelo);
        $calculo = self::calcularLineas($usuarioId, $ctx, [$linea]);
        $fila = self::filaLinea($linea, $calculo[$modelo->id]);

        $recientes = self::filtrarLinea(
            Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->with('subcategoria:id,nombre')
                ->where('usuario_id', $usuarioId)
                ->where('estado', 'CONFIRMADO')
                ->whereBetween('fecha', [$ctx['inicio']->toDateString(), $ctx['fin']->toDateString()]),
            $linea
        )
            ->orderByDesc('fecha')
            ->orderByDesc('creado_en')
            ->limit(5)
            ->get()
            ->map(fn (Gasto $g) => [
                'gasto_id' => $g->id,
                'fecha' => $g->fecha->toDateString(),
                'subcategoria_id' => $g->subcategoria_id,
                'subcategoria' => $g->subcategoria?->nombre,
                'comercio_beneficiario' => $g->comercio_beneficiario,
                'medio_pago' => $g->medio_pago,
                'monto_cop' => CreditoService::alPeso((string) $g->monto_cop),
            ])
            ->all();

        return [
            'presupuesto_id' => $presupuesto->id,
            ...self::datosMes($presupuesto, $ctx),
            'metodo_proyeccion' => $calculo['_metodo'],
            'meses_historia' => $calculo['_meses_historia'],
            'linea' => $fila,
            'serie' => self::serieDiaria($usuarioId, $ctx, $linea, $calculo['_meses_historia']),
            'gastos_recientes' => $recientes,
        ];
    }

    /**
     * RF-057 (gasto proyectado alto): proyección al cierre del gasto total del
     * mes actual, en todas las categorías y haya o no presupuesto, con el
     * mismo método de las líneas (gastado + pendientes + habitual).
     *
     * @return array{mes: string, dias_mes: int, dia_corte: int, metodo_proyeccion: string, gastado: string, pendientes_recurrentes: string, gasto_habitual: string, proyeccion: string}
     */
    public static function proyeccionGlobal(string $usuarioId, ?CarbonImmutable $hoy = null): array
    {
        $ctx = self::contexto(self::mesActual($hoy), $hoy);

        // Una "línea" que cruza con todos los gastos.
        $modelo = new LineaPresupuesto;
        $modelo->id = '_global';
        $linea = ['modelo' => $modelo, 'categoria' => null, 'categoria_id' => null, 'es_sub' => false, 'todo' => true];

        $calculo = self::calcularLineas($usuarioId, $ctx, [$linea]);
        $fila = $calculo['_global'];
        $gastado = CreditoService::alPeso($fila['gastado']);
        $pendientes = CreditoService::alPeso($fila['pendientes']);
        $habitual = CreditoService::alPeso($fila['habitual']);

        return [
            'mes' => $ctx['inicio']->format('Y-m'),
            'dias_mes' => $ctx['dias'],
            'dia_corte' => $ctx['corte'],
            'metodo_proyeccion' => $calculo['_metodo'],
            'gastado' => $gastado,
            'pendientes_recurrentes' => $pendientes,
            'gasto_habitual' => $habitual,
            'proyeccion' => bcadd(bcadd($gastado, $pendientes, 0), $habitual, 0),
        ];
    }

    /**
     * RF-055 / RN-055.3: base para configurar el presupuesto del mes. Por
     * categoría (y sus subcategorías): lo gastado el mes anterior, el promedio
     * de los 3 meses anteriores, el sugerido (promedio redondeado al múltiplo
     * superior de $ 10.000) y lo asignado el mes anterior.
     *
     * @return array<string, mixed>
     */
    public static function base(string $usuarioId, CarbonImmutable $mes): array
    {
        $promedios = self::promediosHistoricos($usuarioId, $mes);
        $mesAnterior = $mes->subMonthNoOverflow();
        $presupuestoAnterior = self::delMes($usuarioId, $mesAnterior);
        $asignadoAnterior = $presupuestoAnterior
            ? $presupuestoAnterior->lineas()->pluck('monto_asignado', 'categoria_id')->map(fn ($m) => CreditoService::alPeso((string) $m))->all()
            : [];
        $presupuestoMes = self::delMes($usuarioId, $mes);
        $asignadoMes = $presupuestoMes
            ? $presupuestoMes->lineas()->pluck('monto_asignado', 'categoria_id')->map(fn ($m) => CreditoService::alPeso((string) $m))->all()
            : [];

        // Categorías con gasto en los 3 meses o con línea en este mes o el anterior.
        $ids = collect(array_keys($promedios))
            ->map(fn ($clave) => substr($clave, 4))
            ->merge(array_keys($asignadoAnterior))
            ->merge(array_keys($asignadoMes))
            ->unique()
            ->values();

        $nombres = Categoria::whereIn('id', $ids)->get(['id', 'nombre', 'categoria_padre_id'])->keyBy('id');
        $raices = [];

        foreach ($nombres as $categoria) {
            $raizId = $categoria->categoria_padre_id ?? $categoria->id;
            $raices[$raizId] ??= null;
        }

        $raizNombres = Categoria::whereIn('id', array_keys($raices))->pluck('nombre', 'id');

        $fila = function (string $id, string $nombre, bool $esSub) use ($promedios, $asignadoAnterior, $asignadoMes) {
            $datos = $promedios[($esSub ? 'sub:' : 'cat:').$id] ?? null;
            $promedio = $datos['promedio'] ?? '0';

            return [
                'categoria_id' => $id,
                'nombre' => $nombre,
                'gastado_mes_anterior' => $datos['mes_anterior'] ?? '0',
                'promedio_3_meses' => $promedio,
                'sugerido' => self::redondearArriba($promedio, (string) config('scfp.presupuestos.redondeo_sugerido')),
                'asignado_mes_anterior' => $asignadoAnterior[$id] ?? null,
                'asignado_mes' => $asignadoMes[$id] ?? null,
            ];
        };

        $categorias = [];
        foreach ($raizNombres as $raizId => $raizNombre) {
            $subcategorias = $nombres
                ->filter(fn ($c) => $c->categoria_padre_id === $raizId)
                ->map(fn ($c) => $fila($c->id, $c->nombre, true))
                ->sortBy('nombre')
                ->values()
                ->all();

            $categorias[] = [...$fila($raizId, $raizNombre, false), 'subcategorias' => $subcategorias];
        }

        usort($categorias, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        $desde = $mes->subMonthsNoOverflow(self::MESES_HISTORIA);
        $gastoTotal = (string) Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'CONFIRMADO')
            ->whereBetween('fecha', [$desde->toDateString(), $mes->subDay()->toDateString()])
            ->sum('monto_cop');

        $meses = [];
        for ($k = 0; $k < self::MESES_HISTORIA; $k++) {
            $meses[] = $desde->addMonthsNoOverflow($k)->format('Y-m');
        }

        return [
            'mes' => $mes->format('Y-m'),
            'meses_promedio' => $meses,
            'ingreso_promedio' => self::ingresoBase($usuarioId, $mes),
            'ingreso_base_tipo' => EndeudamientoService::baseIngresos($usuarioId, $mes)['base'],
            'gasto_promedio_total' => CreditoService::alPeso(bcdiv($gastoTotal, (string) self::MESES_HISTORIA, 10)),
            'categorias' => $categorias,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Cálculo interno
    |--------------------------------------------------------------------------
    */

    /**
     * Día de corte del mes: hoy si es el mes actual, el último día si ya
     * pasó (la proyección es lo gastado) y 0 si es futuro (todo es "resto").
     *
     * @return array{inicio: CarbonImmutable, fin: CarbonImmutable, dias: int, corte: int, pasado: bool}
     */
    private static function contexto(CarbonImmutable $mes, ?CarbonImmutable $hoy): array
    {
        $hoy ??= CarbonImmutable::today();
        $actual = self::mesActual($hoy);
        $dias = $mes->daysInMonth;

        $corte = match (true) {
            $mes->equalTo($actual) => $hoy->day,
            $mes->lessThan($actual) => $dias,
            default => 0,
        };

        return [
            'inicio' => $mes,
            'fin' => $mes->endOfMonth()->startOfDay(),
            'dias' => $dias,
            'corte' => $corte,
            'pasado' => $mes->lessThan($actual),
        ];
    }

    /** @return array<string, mixed> */
    private static function datosMes(Presupuesto $presupuesto, array $ctx): array
    {
        return [
            'mes' => $ctx['inicio']->format('Y-m'),
            'dias_mes' => $ctx['dias'],
            'dia_corte' => $ctx['corte'],
            'repetir_cada_mes' => $presupuesto->repetir_cada_mes,
            'trasladar_saldo' => $presupuesto->trasladar_saldo,
            'ingreso_base' => CreditoService::alPeso((string) $presupuesto->ingreso_base),
            'ingreso_base_tipo' => EndeudamientoService::baseIngresos($presupuesto->usuario_id, $ctx['inicio'])['base'],
        ];
    }

    /**
     * Líneas con su categoría y si son subcategoría (el gasto se cruza por
     * subcategoria_id) o categoría de primer nivel (por categoria_id).
     *
     * @return array<int, array{modelo: LineaPresupuesto, categoria: Categoria, categoria_id: string, es_sub: bool}>
     */
    private static function lineasDe(Presupuesto $presupuesto): array
    {
        return $presupuesto->lineas()->with('categoria')->get()
            ->map(fn (LineaPresupuesto $l) => self::describirLinea($l))
            ->all();
    }

    private static function describirLinea(LineaPresupuesto $linea): array
    {
        $categoria = $linea->relationLoaded('categoria') ? $linea->categoria : $linea->categoria()->first();

        return [
            'modelo' => $linea,
            'categoria' => $categoria,
            'categoria_id' => $linea->categoria_id,
            'es_sub' => $categoria->categoria_padre_id !== null,
        ];
    }

    private static function montoDeLinea(string $modo, mixed $porcentaje, string $montoPesos, string $base): string
    {
        if ($modo !== LineaPresupuesto::PORC_INGRESO) {
            return $montoPesos;
        }

        $monto = self::montoPorcentaje((string) $porcentaje, $base);

        // Sin ingresos en la base, la línea conserva su monto anterior.
        return bccomp($monto, '0', 0) === 1 ? $monto : $montoPesos;
    }

    /**
     * Gastado, pendientes y habitual de cada línea, en pesos sin redondear.
     *
     * @return array<string, mixed> por id de línea, más _metodo y _meses_historia
     */
    private static function calcularLineas(string $usuarioId, array $ctx, array $lineas): array
    {
        $gastado = self::agrupar($usuarioId, $ctx['inicio'], $ctx['fin']);
        $pendientes = $ctx['pasado'] ? [] : self::pendientes($usuarioId, $ctx);
        $mesesHistoria = self::mesesHistoria($usuarioId, $ctx['inicio']);
        $metodo = $mesesHistoria > 0 ? self::METODO_HISTORICO : self::METODO_LINEAL;
        $quedanDias = $ctx['dias'] - $ctx['corte'];

        $habitual = ($metodo === self::METODO_HISTORICO && $quedanDias > 0)
            ? self::agrupar(
                $usuarioId,
                $ctx['inicio']->subMonthsNoOverflow($mesesHistoria),
                $ctx['inicio']->subDay(),
                sinPlantilla: true,
                diaMayorQue: $ctx['corte']
            )
            : [];

        $resultado = ['_metodo' => $metodo, '_meses_historia' => $mesesHistoria];

        foreach ($lineas as $linea) {
            [$montoGastado, $cantidad] = self::sumarLinea($gastado, $linea);
            [$montoPendiente] = self::sumarLinea($pendientes, $linea);

            if ($quedanDias <= 0) {
                $montoHabitual = '0';
            } elseif ($metodo === self::METODO_HISTORICO) {
                [$suma] = self::sumarLinea($habitual, $linea);
                $montoHabitual = bcdiv($suma, (string) $mesesHistoria, 10);
            } else {
                // RN-056.1: gastado / días transcurridos × días que faltan.
                $montoHabitual = $ctx['corte'] > 0
                    ? bcdiv(bcmul($montoGastado, (string) $quedanDias, 10), (string) $ctx['corte'], 10)
                    : '0';
            }

            // H21: una línea fija sin gasto proyecta su monto asignado (una sola regla para todas):
            // lo pendiente (recurrentes) cuenta dentro del asignado, no encima.
            if ($quedanDias > 0 && ($linea['categoria']->naturaleza_sugerida ?? null) === 'FIJO' && bccomp($montoGastado, '0', 2) <= 0) {
                $asignado = CreditoService::alPeso((string) $linea['modelo']->monto_asignado);
                $montoHabitual = bccomp($asignado, $montoPendiente, 10) === 1 ? bcsub($asignado, $montoPendiente, 10) : '0';
            }

            $resultado[$linea['modelo']->id] = [
                'gastado' => $montoGastado,
                'cantidad' => $cantidad,
                'pendientes' => $montoPendiente,
                'habitual' => $montoHabitual,
            ];
        }

        return $resultado;
    }

    /** @return array<string, mixed> */
    private static function filaLinea(array $linea, array $calculo): array
    {
        $modelo = $linea['modelo'];
        $asignado = CreditoService::alPeso((string) $modelo->monto_asignado);
        $saldo = CreditoService::alPeso((string) $modelo->saldo_trasladado);
        $gastado = CreditoService::alPeso($calculo['gastado']);
        $pendientes = CreditoService::alPeso($calculo['pendientes']);
        $habitual = CreditoService::alPeso($calculo['habitual']);
        $proyeccion = bcadd(bcadd($gastado, $pendientes, 0), $habitual, 0);
        $base = bcadd($asignado, $saldo, 0);

        return [
            'linea_id' => $modelo->id,
            'categoria_id' => $modelo->categoria_id,
            'nombre' => $linea['categoria']->nombre,
            'categoria_padre_id' => $linea['categoria']->categoria_padre_id,
            'modo_asignacion' => $modelo->modo_asignacion,
            'porcentaje_ingreso' => $modelo->porcentaje_ingreso === null ? null : (string) $modelo->porcentaje_ingreso,
            'monto_asignado' => $asignado,
            'saldo_trasladado' => $saldo,
            'gastado' => $gastado,
            'cantidad_gastos' => $calculo['cantidad'],
            'disponible' => bcsub($base, $gastado, 0),
            'ejecucion_porc' => self::porcentaje($gastado, $base),
            'pendientes_recurrentes' => $pendientes,
            'gasto_habitual' => $habitual,
            'proyeccion' => $proyeccion,
            'proyeccion_porc' => self::porcentaje($proyeccion, $base),
        ];
    }

    /**
     * Meses de historia (hasta 3) antes del mes: los que van desde el mes del
     * primer gasto confirmado del Usuario. 0 = sin historia (proyección lineal).
     */
    private static function mesesHistoria(string $usuarioId, CarbonImmutable $mes): int
    {
        $primero = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'CONFIRMADO')
            ->where('fecha', '<', $mes->toDateString())
            ->min('fecha');

        if ($primero === null) {
            return 0;
        }

        $inicio = CarbonImmutable::parse($primero);
        $meses = ($mes->year - $inicio->year) * 12 + ($mes->month - $inicio->month);

        return max(0, min(self::MESES_HISTORIA, $meses));
    }

    /**
     * Sumas de gastos agrupadas por categoría y subcategoría.
     *
     * @return array<int, array{categoria_id: string, subcategoria_id: string, monto: string, cantidad: int, dia?: int}>
     */
    private static function agrupar(
        string $usuarioId,
        CarbonImmutable $desde,
        CarbonImmutable $hasta,
        string $estado = 'CONFIRMADO',
        bool $sinPlantilla = false,
        ?int $diaMayorQue = null,
        bool $porDia = false,
        bool $porMes = false
    ): array {
        $query = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('estado', $estado)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->when($sinPlantilla, fn ($q) => $q->whereNull('plantilla_recurrencia_id'))
            ->when($diaMayorQue !== null, fn ($q) => $q->whereRaw('EXTRACT(DAY FROM fecha) > ?', [$diaMayorQue]))
            ->groupBy('categoria_id', 'subcategoria_id')
            ->selectRaw('categoria_id, subcategoria_id, SUM(monto_cop) AS monto, COUNT(*) AS cantidad');

        if ($porDia) {
            $query->selectRaw('CAST(EXTRACT(DAY FROM fecha) AS integer) AS dia')->groupByRaw('EXTRACT(DAY FROM fecha)');
        }

        if ($porMes) {
            $query->selectRaw("to_char(fecha, 'YYYY-MM') AS mes")->groupByRaw("to_char(fecha, 'YYYY-MM')");
        }

        return $query->toBase()->get()->map(fn ($f) => [
            'categoria_id' => $f->categoria_id,
            'subcategoria_id' => $f->subcategoria_id,
            'monto' => (string) $f->monto,
            'cantidad' => (int) $f->cantidad,
            'dia' => isset($f->dia) ? (int) $f->dia : null,
            'mes' => $f->mes ?? null,
        ])->all();
    }

    /**
     * Recurrentes pendientes del mes: gastos PENDIENTE (por confirmar o con
     * monto estimado, RN-028.2) y ocurrencias aún no generadas de las
     * plantillas de gasto activas que caen en el mes. Los PENDIENTE ya
     * generados y con fecha pasada se ubican en el día siguiente al corte.
     *
     * @return array<int, array{categoria_id: string, subcategoria_id: string, monto: string, cantidad: int, dia: int}>
     */
    private static function pendientes(string $usuarioId, array $ctx): array
    {
        $filas = self::agrupar($usuarioId, $ctx['inicio'], $ctx['fin'], estado: 'PENDIENTE', porDia: true);

        $plantillas = PlantillaRecurrencia::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('tipo_movimiento', 'GASTO')
            ->where('activa', true)
            ->get();

        foreach ($plantillas as $plantilla) {
            $datos = $plantilla->datos_plantilla;

            foreach (RecurrenciaService::ocurrenciasPendientes($plantilla, $ctx['inicio'], $ctx['fin']) as $fecha) {
                $filas[] = [
                    'categoria_id' => $datos['categoria_id'],
                    'subcategoria_id' => $datos['subcategoria_id'],
                    'monto' => (string) $datos['monto_cop'],
                    'cantidad' => 1,
                    'dia' => $fecha->day,
                    'mes' => null,
                ];
            }
        }

        return $filas;
    }

    /** @return array{0: string, 1: int} monto y cantidad de las filas que caen en la línea */
    private static function sumarLinea(array $filas, array $linea): array
    {
        $monto = '0';
        $cantidad = 0;
        $columna = $linea['es_sub'] ? 'subcategoria_id' : 'categoria_id';
        $todo = $linea['todo'] ?? false;

        foreach ($filas as $fila) {
            if ($todo || $fila[$columna] === $linea['categoria_id']) {
                $monto = bcadd($monto, $fila['monto'], 2);
                $cantidad += $fila['cantidad'];
            }
        }

        return [$monto, $cantidad];
    }

    private static function filtrarLinea($query, array $linea)
    {
        return $query->where($linea['es_sub'] ? 'subcategoria_id' : 'categoria_id', $linea['categoria_id']);
    }

    /**
     * Gasto confirmado del mes en categorías sin línea, por categoría de
     * primer nivel. Así el presupuesto no esconde gasto real.
     *
     * @return array<int, array{categoria_id: string, nombre: string, monto: string, cantidad_gastos: int}>
     */
    private static function fueraDelPresupuesto(string $usuarioId, CarbonImmutable $mes, array $lineas): array
    {
        $raices = [];
        $subs = [];
        foreach ($lineas as $linea) {
            $linea['es_sub'] ? $subs[$linea['categoria_id']] = true : $raices[$linea['categoria_id']] = true;
        }

        $porCategoria = [];
        foreach (self::agrupar($usuarioId, $mes, $mes->endOfMonth()->startOfDay()) as $fila) {
            if (isset($raices[$fila['categoria_id']]) || isset($subs[$fila['subcategoria_id']])) {
                continue;
            }

            $id = $fila['categoria_id'];
            $porCategoria[$id] ??= ['monto' => '0', 'cantidad' => 0];
            $porCategoria[$id]['monto'] = bcadd($porCategoria[$id]['monto'], $fila['monto'], 2);
            $porCategoria[$id]['cantidad'] += $fila['cantidad'];
        }

        $nombres = Categoria::whereIn('id', array_keys($porCategoria))->pluck('nombre', 'id');
        $resultado = [];

        foreach ($porCategoria as $id => $datos) {
            $resultado[] = [
                'categoria_id' => $id,
                'nombre' => $nombres[$id] ?? null,
                'monto' => CreditoService::alPeso($datos['monto']),
                'cantidad_gastos' => $datos['cantidad'],
            ];
        }

        usort($resultado, fn ($a, $b) => bccomp($b['monto'], $a['monto'], 0));

        return $resultado;
    }

    /**
     * Serie diaria de la línea: `real` es el acumulado hasta el día de corte;
     * `proyectado`, desde el corte, suma pendientes y habitual día a día
     * siguiendo la forma de los meses anteriores. El último punto proyectado
     * es la proyección de la línea.
     *
     * @return array<int, array{dia: int, real: ?string, proyectado: ?string}>
     */
    private static function serieDiaria(string $usuarioId, array $ctx, array $linea, int $mesesHistoria): array
    {
        $corte = $ctx['corte'];
        $dias = $ctx['dias'];

        $realPorDia = [];
        foreach (self::agrupar($usuarioId, $ctx['inicio'], $ctx['fin'], porDia: true) as $fila) {
            if ($fila[$linea['es_sub'] ? 'subcategoria_id' : 'categoria_id'] === $linea['categoria_id']) {
                $realPorDia[$fila['dia']] = bcadd($realPorDia[$fila['dia']] ?? '0', $fila['monto'], 2);
            }
        }

        $proyectadoPorDia = [];
        $agregar = function (int $dia, string $monto) use (&$proyectadoPorDia, $corte, $dias) {
            $dia = min($dias, max($dia, $corte + 1));
            $proyectadoPorDia[$dia] = bcadd($proyectadoPorDia[$dia] ?? '0', $monto, 10);
        };

        if (! $ctx['pasado']) {
            foreach (self::pendientes($usuarioId, $ctx) as $fila) {
                if ($fila[$linea['es_sub'] ? 'subcategoria_id' : 'categoria_id'] === $linea['categoria_id']) {
                    $agregar($fila['dia'], $fila['monto']);
                }
            }
        }

        if ($corte < $dias) {
            if ($mesesHistoria > 0) {
                $historia = self::agrupar(
                    $usuarioId,
                    $ctx['inicio']->subMonthsNoOverflow($mesesHistoria),
                    $ctx['inicio']->subDay(),
                    sinPlantilla: true,
                    diaMayorQue: $corte,
                    porDia: true
                );

                foreach ($historia as $fila) {
                    if ($fila[$linea['es_sub'] ? 'subcategoria_id' : 'categoria_id'] === $linea['categoria_id'] && $fila['dia'] <= $dias) {
                        $agregar($fila['dia'], bcdiv($fila['monto'], (string) $mesesHistoria, 10));
                    }
                }

                // Días 29–31 de meses anteriores que este mes no tiene: van al último día.
                foreach ($historia as $fila) {
                    if ($fila[$linea['es_sub'] ? 'subcategoria_id' : 'categoria_id'] === $linea['categoria_id'] && $fila['dia'] > $dias) {
                        $agregar($dias, bcdiv($fila['monto'], (string) $mesesHistoria, 10));
                    }
                }
            } elseif ($corte > 0) {
                // RN-056.1: el ritmo lineal reparte lo gastado por día.
                $gastado = array_reduce($realPorDia, fn ($c, $m) => bcadd($c, $m, 2), '0');
                $porDia = bcdiv($gastado, (string) $corte, 10);
                for ($d = $corte + 1; $d <= $dias; $d++) {
                    $agregar($d, $porDia);
                }
            }
        }

        $serie = [];
        $real = '0';
        $acumuladoReal = '0';

        for ($d = 1; $d <= $dias; $d++) {
            $real = bcadd($real, $realPorDia[$d] ?? '0', 2);
            if ($d === $corte) {
                $acumuladoReal = $real;
            }
        }

        $proyectado = $corte > 0 ? CreditoService::alPeso($acumuladoReal) : '0';
        $acumuladoProyectado = $proyectado;
        $real = '0';

        for ($d = 1; $d <= $dias; $d++) {
            $real = bcadd($real, $realPorDia[$d] ?? '0', 2);

            $puntoProyectado = null;
            if ($d >= $corte && ! $ctx['pasado']) {
                $acumuladoProyectado = bcadd($acumuladoProyectado, $proyectadoPorDia[$d] ?? '0', 10);
                $puntoProyectado = CreditoService::alPeso($acumuladoProyectado);
            }

            $serie[] = [
                'dia' => $d,
                'real' => $d <= $corte ? CreditoService::alPeso($real) : null,
                'proyectado' => $puntoProyectado,
            ];
        }

        return $serie;
    }

    /**
     * RN-055.3: por categoría (cat:id) y subcategoría (sub:id), lo gastado el
     * mes anterior y el promedio de los 3 meses anteriores, al peso. Un mes
     * sin gasto vale 0 y cuenta en el promedio.
     *
     * @return array<string, array{mes_anterior: string, promedio: string}>
     */
    private static function promediosHistoricos(string $usuarioId, CarbonImmutable $mes): array
    {
        $desde = $mes->subMonthsNoOverflow(self::MESES_HISTORIA);
        $claveAnterior = $mes->subMonthNoOverflow()->format('Y-m');
        $acumulado = [];

        foreach (self::agrupar($usuarioId, $desde, $mes->subDay(), porMes: true) as $fila) {
            foreach (['cat:'.$fila['categoria_id'], 'sub:'.$fila['subcategoria_id']] as $clave) {
                $acumulado[$clave] ??= ['total' => '0', 'mes_anterior' => '0'];
                $acumulado[$clave]['total'] = bcadd($acumulado[$clave]['total'], $fila['monto'], 2);

                if ($fila['mes'] === $claveAnterior) {
                    $acumulado[$clave]['mes_anterior'] = bcadd($acumulado[$clave]['mes_anterior'], $fila['monto'], 2);
                }
            }
        }

        return array_map(fn ($a) => [
            'mes_anterior' => CreditoService::alPeso($a['mes_anterior']),
            'promedio' => CreditoService::alPeso(bcdiv($a['total'], (string) self::MESES_HISTORIA, 10)),
        ], $acumulado);
    }

    /** RN-055.3: múltiplo superior de $paso (0 se queda en 0). */
    private static function redondearArriba(string $valor, string $paso): string
    {
        if (bccomp($valor, '0', 2) !== 1) {
            return '0';
        }

        $cociente = bcdiv($valor, $paso, 0);
        if (bccomp(bcmul($cociente, $paso, 2), $valor, 2) === -1) {
            $cociente = bcadd($cociente, '1', 0);
        }

        return bcmul($cociente, $paso, 0);
    }

    /** num / den × 100 con 2 decimales (mitad hacia arriba); null si den = 0. */
    private static function porcentaje(string $numerador, string $denominador): ?string
    {
        if (bccomp($denominador, '0', 2) !== 1) {
            return null;
        }

        $valor = bcdiv(bcmul($numerador, '100', 10), $denominador, 10);
        $signo = bccomp($valor, '0', 10) === -1 ? '-' : '';

        return $signo === ''
            ? bcadd($valor, '0.005', 2)
            : bcsub($valor, '0.005', 2);
    }

    /** @return array<string, mixed> */
    private static function resumenParaBitacora(Presupuesto $presupuesto): array
    {
        return [
            'mes' => $presupuesto->mes->format('Y-m'),
            'repetir_cada_mes' => $presupuesto->repetir_cada_mes,
            'trasladar_saldo' => $presupuesto->trasladar_saldo,
            'ingreso_base' => (string) $presupuesto->ingreso_base,
            'lineas' => $presupuesto->lineas()->get(['categoria_id', 'modo_asignacion', 'porcentaje_ingreso', 'monto_asignado', 'saldo_trasladado'])->toArray(),
        ];
    }
}
