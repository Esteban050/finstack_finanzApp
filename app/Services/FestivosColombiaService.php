<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Cálculo de días festivos en Colombia según la Ley 51 de 1983 (Ley Emiliani)
 * y cálculo de días hábiles bancarios y tributarios.
 */
class FestivosColombiaService
{
    /** Cache en memoria por año para no recalcular en la misma solicitud. */
    private static array $cache = [];

    /**
     * Retorna todos los festivos oficiales de Colombia para un año determinado
     * en formato 'Y-m-d', ordenados cronológicamente.
     *
     * @return array<string>
     */
    public static function festivos(int $anio): array
    {
        if (isset(self::$cache[$anio])) {
            return self::$cache[$anio];
        }

        $festivos = [];

        // 1. Festivos fijos (no se trasladan)
        $festivos[] = sprintf('%04d-01-01', $anio); // Año Nuevo
        $festivos[] = sprintf('%04d-05-01', $anio); // Día del Trabajo
        $festivos[] = sprintf('%04d-07-20', $anio); // Día de la Independencia
        $festivos[] = sprintf('%04d-08-07', $anio); // Batalla de Boyacá
        $festivos[] = sprintf('%04d-12-08', $anio); // Inmaculada Concepción
        $festivos[] = sprintf('%04d-12-25', $anio); // Navidad

        // 2. Festivos con traslado al siguiente lunes (Ley 51 de 1983 - Ley Emiliani)
        $emiliani = [
            sprintf('%04d-01-06', $anio), // Reyes Magos
            sprintf('%04d-03-19', $anio), // San José
            sprintf('%04d-06-29', $anio), // San Pedro y San Pablo
            sprintf('%04d-08-15', $anio), // Asunción de la Virgen
            sprintf('%04d-10-12', $anio), // Día de la Raza
            sprintf('%04d-11-01', $anio), // Todos los Santos
            sprintf('%04d-11-11', $anio), // Independencia de Cartagena
        ];

        foreach ($emiliani as $f) {
            $fecha = CarbonImmutable::parse($f);
            if ($fecha->isMonday()) {
                $festivos[] = $fecha->toDateString();
            } else {
                $festivos[] = $fecha->next(CarbonImmutable::MONDAY)->toDateString();
            }
        }

        // 3. Festivos móviles calculados a partir del Domingo de Pascua
        $diasDespues21Marzo = easter_days($anio);
        $domingoPascua = CarbonImmutable::create($anio, 3, 21)->addDays($diasDespues21Marzo);

        // Jueves Santo (-3 días) y Viernes Santo (-2 días)
        $festivos[] = $domingoPascua->subDays(3)->toDateString();
        $festivos[] = $domingoPascua->subDays(2)->toDateString();

        // Festivos de Pascua trasladados al lunes:
        // Ascensión del Señor (+40 días -> lunes +43 días)
        $festivos[] = $domingoPascua->addDays(43)->toDateString();
        // Corpus Christi (+60 días -> lunes +64 días)
        $festivos[] = $domingoPascua->addDays(64)->toDateString();
        // Sagrado Corazón de Jesús (+68 días -> lunes +71 días)
        $festivos[] = $domingoPascua->addDays(71)->toDateString();

        sort($festivos);
        $festivos = array_values(array_unique($festivos));

        self::$cache[$anio] = $festivos;

        return $festivos;
    }

    /**
     * Determina si una fecha dada es un festivo en Colombia.
     */
    public static function esFestivo(CarbonImmutable $fecha): bool
    {
        return in_array($fecha->toDateString(), self::festivos($fecha->year), true);
    }

    /**
     * Determina si una fecha es día hábil (lunes a viernes, no festivo).
     */
    public static function esDiaHabil(CarbonImmutable $fecha): bool
    {
        if ($fecha->isWeekend()) {
            return false;
        }

        return ! self::esFestivo($fecha);
    }

    /**
     * Calcula la fecha exacta del N-ésimo día hábil de un mes y año determinado.
     * Si el número supera los días hábiles del mes, retorna el último día hábil.
     */
    public static function diaHabilDelMes(int $anio, int $mes, int $numeroDiaHabil): CarbonImmutable
    {
        $inicio = CarbonImmutable::create($anio, $mes, 1);
        $diasEnMes = $inicio->daysInMonth;
        $habilesContados = 0;
        $ultimoHabil = $inicio;

        for ($d = 1; $d <= $diasEnMes; $d++) {
            $actual = $inicio->setDay($d);
            if (self::esDiaHabil($actual)) {
                $habilesContados++;
                $ultimoHabil = $actual;
                if ($habilesContados === $numeroDiaHabil) {
                    return $actual;
                }
            }
        }

        return $ultimoHabil;
    }
}

