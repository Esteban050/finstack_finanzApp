<?php

namespace App\Services;

use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\PlantillaRecurrencia;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * RF-022 — Generación automática de las ocurrencias de una recurrencia.
 *
 * RN-028.1: los gastos recurrentes (RF-028) se rigen por las mismas reglas de
 * periodicidad, generación, confirmación y edición que los ingresos, así que
 * comparten este servicio y la tabla `plantillas_recurrencia`. La única regla
 * propia de los gastos es RN-028.2, el monto estimado.
 */
class RecurrenciaService
{
    /**
     * RN-022.1: Periodicidad expresada como desplazamiento desde la fecha de inicio.
     * Las periodicidades en días son exactas; las mensuales se calculan en meses
     * para poder respetar el anclaje del día (ver calcularOcurrencia).
     */
    private const DIAS = [
        'SEMANAL' => 7,
        'QUINCENAL' => 15,
    ];

    private const MESES = [
        'MENSUAL' => 1,
        'BIMESTRAL' => 2,
        'TRIMESTRAL' => 3,
        'SEMESTRAL' => 6,
        'ANUAL' => 12,
    ];

    /**
     * RN-022.2: Fecha de la ocurrencia número $indice (0 = la primera, que cae en
     * la fecha de inicio). "Si el día no existe en el mes (por ejemplo, el 31), se
     * usa el último día del mes".
     *
     * El cálculo se hace siempre desde la fecha de inicio, nunca acumulando sobre
     * la ocurrencia anterior: así el día queda anclado al original y un 30/01 da
     * 28/02 pero vuelve a 30/03, en lugar de quedarse pegado al 28.
     *
     * CA-022.1: mensual desde el 30/01/2026 → la ocurrencia de febrero es 28/02/2026.
     */
    public static function calcularOcurrencia(CarbonImmutable $fechaInicio, string $periodicidad, int $indice): CarbonImmutable
    {
        if ($indice === 0) {
            return $fechaInicio;
        }

        if (isset(self::DIAS[$periodicidad])) {
            return $fechaInicio->addDays(self::DIAS[$periodicidad] * $indice);
        }

        if (isset(self::MESES[$periodicidad])) {
            return $fechaInicio->addMonthsNoOverflow(self::MESES[$periodicidad] * $indice);
        }

        throw new \InvalidArgumentException("Periodicidad no soportada: {$periodicidad}");
    }

    /**
     * RN-022.2: Fecha de la siguiente ocurrencia pendiente de generar.
     */
    public static function proximaFecha(PlantillaRecurrencia $plantilla): CarbonImmutable
    {
        return self::calcularOcurrencia(
            CarbonImmutable::parse($plantilla->fecha_inicio),
            $plantilla->periodicidad,
            $plantilla->ocurrencias_generadas
        );
    }

    /**
     * CA-022.2: Al reanudar una recurrencia pausada el calendario no retrocede.
     * Las ocurrencias cuya fecha pasó durante la pausa no se generan: ese ingreso
     * no ocurrió, y crearlo con fecha retroactiva falsearía los totales del
     * periodo (RF-024).
     *
     * Devuelve el índice de la primera ocurrencia que aún no ha llegado,
     * conservando el anclaje del día (RN-022.2). Las ocurrencias saltadas
     * consumen cupo de max_ocurrencias, porque el calendario sí avanzó.
     *
     * No confundir con el recupero del comando programado: si el job no corrió
     * algún día, generarPara sí crea las ocurrencias atrasadas. Ahí la plantilla
     * estuvo activa todo el tiempo y el ingreso sí ocurrió.
     */
    public static function adelantarCursor(PlantillaRecurrencia $plantilla, ?CarbonImmutable $desde = null): int
    {
        $desde = $desde ?? CarbonImmutable::today();
        $inicio = CarbonImmutable::parse($plantilla->fecha_inicio);
        $indice = $plantilla->ocurrencias_generadas;

        while (self::calcularOcurrencia($inicio, $plantilla->periodicidad, $indice)->lessThan($desde)) {
            if ($plantilla->max_ocurrencias !== null && $indice >= $plantilla->max_ocurrencias) {
                break;
            }

            $indice++;
        }

        return $indice;
    }

    /**
     * RN-022.2: Generar las ocurrencias vencidas de todas las plantillas de ingreso
     * activas. Devuelve el número de ingresos creados.
     *
     * Se ejecuta a diario (00:05) desde routes/console.php. Si el job no corrió
     * algún día, la plantilla genera también las ocurrencias atrasadas.
     */
    public static function generarIngresosPendientes(?CarbonImmutable $hasta = null): int
    {
        $hasta = $hasta ?? CarbonImmutable::today();
        $generados = 0;

        $plantillas = PlantillaRecurrencia::deIngresos()
            ->where('activa', true)
            ->where('proxima_ejecucion', '<=', $hasta->toDateString())
            ->get();

        foreach ($plantillas as $plantilla) {
            $generados += self::generarPara($plantilla, $hasta);
        }

        return $generados;
    }

    /**
     * RN-028.1: Generar las ocurrencias vencidas de todas las plantillas de gasto
     * activas. Devuelve el número de gastos creados.
     *
     * Corre junto con las de ingreso en `scfp:generar-recurrencias` (00:05).
     */
    public static function generarGastosPendientes(?CarbonImmutable $hasta = null): int
    {
        $hasta = $hasta ?? CarbonImmutable::today();
        $generados = 0;

        $plantillas = PlantillaRecurrencia::deGastos()
            ->where('activa', true)
            ->where('proxima_ejecucion', '<=', $hasta->toDateString())
            ->get();

        foreach ($plantillas as $plantilla) {
            $generados += self::generarPara($plantilla, $hasta);
        }

        return $generados;
    }

    /**
     * RN-022.2 / CA-022.2: Generar las ocurrencias de una plantilla hasta la fecha
     * dada, deteniéndose en el tope de fecha de fin o de número de ocurrencias.
     */
    public static function generarPara(PlantillaRecurrencia $plantilla, CarbonImmutable $hasta): int
    {
        $generados = 0;

        DB::transaction(function () use ($plantilla, $hasta, &$generados) {
            // Se recarga con bloqueo para que dos ejecuciones simultáneas del job
            // no generen la misma ocurrencia dos veces.
            $plantilla = PlantillaRecurrencia::where('id', $plantilla->id)
                ->lockForUpdate()
                ->first();

            if ($plantilla === null || ! $plantilla->activa) {
                return;
            }

            while (true) {
                if ($plantilla->max_ocurrencias !== null
                    && $plantilla->ocurrencias_generadas >= $plantilla->max_ocurrencias) {
                    $plantilla->activa = false;
                    break;
                }

                $fecha = self::proximaFecha($plantilla);

                if ($plantilla->fecha_fin !== null
                    && $fecha->greaterThan(CarbonImmutable::parse($plantilla->fecha_fin))) {
                    $plantilla->activa = false;
                    break;
                }

                if ($fecha->greaterThan($hasta)) {
                    $plantilla->proxima_ejecucion = $fecha->toDateString();
                    break;
                }

                self::crearOcurrencia($plantilla, $fecha);

                $plantilla->ocurrencias_generadas++;
                $generados++;

                $plantilla->proxima_ejecucion = self::proximaFecha($plantilla)->toDateString();
            }

            $plantilla->save();
        });

        return $generados;
    }

    /**
     * La plantilla sabe qué movimiento genera (RF-022 ingreso o RF-028 gasto).
     * El calendario, los topes y el modo de confirmación son los mismos
     * (RN-028.1); lo único que cambia es la tabla en la que aterriza.
     */
    private static function crearOcurrencia(PlantillaRecurrencia $plantilla, CarbonImmutable $fecha): Ingreso|Gasto
    {
        return $plantilla->tipo_movimiento === 'GASTO'
            ? self::crearGasto($plantilla, $fecha)
            : self::crearIngreso($plantilla, $fecha);
    }

    /**
     * RN-028.1 / RN-028.2: La ocurrencia de gasto nace con origen RECURRENTE y
     * con la clasificación de RF-026 que guardó la plantilla.
     *
     * El estado lo decide `estadoOcurrencia()`: PENDIENTE si la plantilla
     * requiere confirmación (RN-022.3) o si el monto es estimado (RN-028.2),
     * caso en el que el usuario debe confirmar el monto real antes de que el
     * gasto entre en los cálculos (CA-028.2).
     */
    private static function crearGasto(PlantillaRecurrencia $plantilla, CarbonImmutable $fecha): Gasto
    {
        $datos = $plantilla->datos_plantilla;

        return Gasto::create([
            'usuario_id' => $plantilla->usuario_id,
            'categoria_id' => $datos['categoria_id'],
            'subcategoria_id' => $datos['subcategoria_id'],
            'plantilla_recurrencia_id' => $plantilla->id,
            'fecha' => $fecha->toDateString(),
            'moneda' => $datos['moneda'] ?? 'COP',
            'monto_original' => $datos['monto_original'],
            'tasa_cambio_usada' => $datos['tasa_cambio_usada'] ?? '1.0000',
            'monto_cop' => $datos['monto_cop'],
            'naturaleza' => $datos['naturaleza'],
            'necesidad' => $datos['necesidad'],
            'medio_pago' => $datos['medio_pago'],
            'relevancia_tributaria' => $datos['relevancia_tributaria'] ?? 'NO_DEDUCIBLE',
            'subtipo_deduccion' => $datos['subtipo_deduccion'] ?? null,
            'etiquetas' => $datos['etiquetas'] ?? [],
            'comercio_beneficiario' => $datos['comercio_beneficiario'] ?? null,
            'origen' => 'RECURRENTE',
            'estado' => $plantilla->estadoOcurrencia(),
            'descripcion' => $datos['descripcion'] ?? null,
        ]);
    }

    /**
     * RN-022.2 / RN-022.3: La ocurrencia nace con origen RECURRENTE y en el estado
     * que fije el modo de confirmación de la plantilla.
     */
    private static function crearIngreso(PlantillaRecurrencia $plantilla, CarbonImmutable $fecha): Ingreso
    {
        $datos = $plantilla->datos_plantilla;

        return Ingreso::create([
            'usuario_id' => $plantilla->usuario_id,
            'fuente_id' => $datos['fuente_id'],
            'plantilla_recurrencia_id' => $plantilla->id,
            'fecha_recepcion' => $fecha->toDateString(),
            'clasificacion_tributaria' => $datos['clasificacion_tributaria'],
            'moneda' => $datos['moneda'] ?? 'COP',
            'monto_original' => $datos['monto_original'],
            'tasa_cambio_usada' => $datos['tasa_cambio_usada'] ?? '1.0000',
            'monto_bruto_cop' => $datos['monto_bruto_cop'],
            'retencion_fuente_cop' => $datos['retencion_fuente_cop'] ?? '0.00',
            'aportes_descontados_cop' => $datos['aportes_descontados_cop'] ?? '0.00',
            'origen' => 'RECURRENTE',
            'estado' => $plantilla->estadoOcurrencia(),
            'descripcion' => $datos['descripcion'] ?? null,
        ]);
    }
}
