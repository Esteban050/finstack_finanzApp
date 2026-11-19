<?php

namespace App\Services\Reportes;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Presupuesto;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\Usuario;
use App\Services\PresupuestoService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * MOD-11 — Reportes y Dashboards Dinámicos
 * HU-H06: Servicio de Comparativo entre Periodos (RF-070).
 *
 * Implementa validación estricta de duración equivalente (± 3 días, RN-070.3),
 * cálculo de variaciones, ordenamiento por pesos (RN-070.2) y manejo de división por cero (CA-070.2).
 */
class ComparativoPeriodosService
{
    public const TOLERANCIA_DIAS = 3;

    /**
     * Valida la equivalencia de duración entre el Periodo A y el Periodo B (RN-070.3).
     *
     * @throws ValidationException
     */
    public function validarDuracionEquivalente(
        CarbonImmutable $desdeA,
        CarbonImmutable $hastaA,
        CarbonImmutable $desdeB,
        CarbonImmutable $hastaB
    ): array {
        if ($desdeA->gt($hastaA)) {
            throw ValidationException::withMessages([
                'desde_a' => 'La fecha inicial del Periodo A no puede ser posterior a la fecha final.',
            ]);
        }

        if ($desdeB->gt($hastaB)) {
            throw ValidationException::withMessages([
                'desde_b' => 'La fecha inicial del Periodo B no puede ser posterior a la fecha final.',
            ]);
        }

        $diasA = (int) $desdeA->diffInDays($hastaA) + 1;
        $diasB = (int) $desdeB->diffInDays($hastaB) + 1;
        $diferencia = abs($diasB - $diasA);

        if ($diferencia > self::TOLERANCIA_DIAS) {
            throw ValidationException::withMessages([
                'periodo_b' => "Ambos periodos deben tener una duración equivalente (tolerancia de ± " . self::TOLERANCIA_DIAS . " días). El Periodo A tiene {$diasA} días y el Periodo B tiene {$diasB} días (diferencia de {$diferencia} días).",
            ]);
        }

        return [
            'dias_a' => $diasA,
            'dias_b' => $diasB,
            'diferencia' => $diferencia,
        ];
    }

    /**
     * Resuelve los periodos por defecto si no se especifican (ej. mes anterior vs mes actual).
     */
    public function resolverPeriodosPorDefecto(?CarbonImmutable $referencia = null): array
    {
        $hoy = $referencia ?? CarbonImmutable::today();

        // Periodo B: Mes actual
        $desdeB = $hoy->startOfMonth();
        $hastaB = $hoy->endOfMonth();

        // Periodo A: Mes anterior
        $desdeA = $hoy->subMonthNoOverflow()->startOfMonth();
        $hastaA = $hoy->subMonthNoOverflow()->endOfMonth();

        return [
            'desde_a' => $desdeA,
            'hasta_a' => $hastaA,
            'desde_b' => $desdeB,
            'hasta_b' => $hastaB,
        ];
    }

    /**
     * Calcula la variación entre dos montos respetando CA-070.2.
     *
     * @return array{
     *     monto_a: string,
     *     monto_b: string,
     *     variacion_pesos: string,
     *     variacion_porc: ?float,
     *     variacion_porc_texto: string,
     *     signo: string,
     *     direccion: string
     * }
     */
    public function calcularVariacion(string $montoA, string $montoB): array
    {
        $diff = bcsub($montoB, $montoA, 2);
        $signo = '+';
        $direccion = 'igual';

        $comp = bccomp($diff, '0', 2);
        if ($comp === 1) {
            $signo = '+';
            $direccion = 'aumento';
        } elseif ($comp === -1) {
            $signo = '-';
            $direccion = 'disminucion';
        }

        $porc = null;
        $porcTexto = '0,00%';

        if (bccomp($montoA, '0', 2) === 0) {
            if (bccomp($montoB, '0', 2) === 1) {
                // CA-070.2: Si en A es 0 y en B > 0, variación porcentual es "N/A"
                $porcTexto = 'N/A';
            } else {
                $porcTexto = '0,00%';
            }
        } else {
            $porc = round((float) bcdiv(bcmul($diff, '100', 6), $montoA, 4), 2);
            $signoPrefijo = $porc > 0 ? '+' : '';
            $porcTexto = $signoPrefijo . number_format($porc, 2, ',', '.') . '%';
        }

        return [
            'monto_a' => $montoA,
            'monto_b' => $montoB,
            'variacion_pesos' => $diff,
            'variacion_porc' => $porc,
            'variacion_porc_texto' => $porcTexto,
            'signo' => $signo,
            'direccion' => $direccion,
        ];
    }

    /**
     * Realiza el comparativo integral entre los dos periodos (HU-H06).
     */
    public function comparar(
        Usuario $usuario,
        ?string $fechaDesdeA = null,
        ?string $fechaHastaA = null,
        ?string $fechaDesdeB = null,
        ?string $fechaHastaB = null,
        ?CarbonImmutable $referencia = null
    ): array {
        $hoy = $referencia ?? CarbonImmutable::today();

        if ($fechaDesdeA && $fechaHastaA && $fechaDesdeB && $fechaHastaB) {
            $desdeA = CarbonImmutable::parse($fechaDesdeA)->startOfDay();
            $hastaA = CarbonImmutable::parse($fechaHastaA)->endOfDay();
            $desdeB = CarbonImmutable::parse($fechaDesdeB)->startOfDay();
            $hastaB = CarbonImmutable::parse($fechaHastaB)->endOfDay();
        } else {
            $def = $this->resolverPeriodosPorDefecto($hoy);
            $desdeA = $def['desde_a'];
            $hastaA = $def['hasta_a'];
            $desdeB = $def['desde_b'];
            $hastaB = $def['hasta_b'];
        }

        $duracion = $this->validarDuracionEquivalente($desdeA, $hastaA, $desdeB, $hastaB);

        // 1. Ingresos Netos
        $ingresosA = (string) (Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha_recepcion', [$desdeA->toDateString(), $hastaA->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->sum('monto_neto_cop') ?? '0');

        $ingresosB = (string) (Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha_recepcion', [$desdeB->toDateString(), $hastaB->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->sum('monto_neto_cop') ?? '0');

        $resumenIngresos = $this->calcularVariacion($ingresosA, $ingresosB);

        // 2. Gastos Totales
        $gastosA = (string) (Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desdeA->toDateString(), $hastaA->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->sum('monto_cop') ?? '0');

        $gastosB = (string) (Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desdeB->toDateString(), $hastaB->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->sum('monto_cop') ?? '0');

        $resumenGastos = $this->calcularVariacion($gastosA, $gastosB);

        // 3. Balance
        $balanceA = bcsub($ingresosA, $gastosA, 2);
        $balanceB = bcsub($ingresosB, $gastosB, 2);
        $resumenBalance = $this->calcularVariacion($balanceA, $balanceB);

        // 4. Ejecución del Presupuesto (en puntos porcentuales)
        $ejecucionA = $this->obtenerEjecucionPresupuestoMes($usuario, $desdeA);
        $ejecucionB = $this->obtenerEjecucionPresupuestoMes($usuario, $desdeB);

        $deltaPp = null;
        $deltaPpTexto = 'N/A';
        if ($ejecucionA !== null && $ejecucionB !== null) {
            $deltaPp = round($ejecucionB - $ejecucionA, 1);
            $signoPp = $deltaPp > 0 ? '+' : '';
            $deltaPpTexto = "{$signoPp}{$deltaPp} pp";
        }

        $resumenPresupuesto = [
            'periodo_a' => $ejecucionA !== null ? "{$ejecucionA}%" : 'Sin presupuesto',
            'periodo_b' => $ejecucionB !== null ? "{$ejecucionB}%" : 'Sin presupuesto',
            'variacion_pp' => $deltaPp,
            'variacion_pp_texto' => $deltaPpTexto,
            'signo' => ($deltaPp ?? 0) >= 0 ? '+' : '-',
        ];

        // 5. Tabla comparativa por categoría ordenada por variación en pesos (RN-070.1, RN-070.2)
        $gastosCatA = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desdeA->toDateString(), $hastaA->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->selectRaw('categoria_id, SUM(monto_cop) as total')
            ->groupBy('categoria_id')
            ->pluck('total', 'categoria_id')
            ->all();

        $gastosCatB = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desdeB->toDateString(), $hastaB->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->selectRaw('categoria_id, SUM(monto_cop) as total')
            ->groupBy('categoria_id')
            ->pluck('total', 'categoria_id')
            ->all();

        $todasCatIds = array_unique(array_merge(array_keys($gastosCatA), array_keys($gastosCatB)));
        $nombresCats = Categoria::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereIn('id', $todasCatIds)
            ->pluck('nombre', 'id')
            ->all();

        $tablaCategorias = [];
        foreach ($todasCatIds as $catId) {
            $montoCatA = (string) ($gastosCatA[$catId] ?? '0');
            $montoCatB = (string) ($gastosCatB[$catId] ?? '0');
            $var = $this->calcularVariacion($montoCatA, $montoCatB);

            $tablaCategorias[] = [
                'categoria_id' => $catId,
                'nombre' => $nombresCats[$catId] ?? 'Categoría',
                'periodo_a' => $montoCatA,
                'periodo_b' => $montoCatB,
                'variacion_pesos' => $var['variacion_pesos'],
                'variacion_porc' => $var['variacion_porc'],
                'variacion_porc_texto' => $var['variacion_porc_texto'],
                'signo' => $var['signo'],
                'direccion' => $var['direccion'],
                // Para gastos: si subió es desfavorable (rojo +), si bajó es favorable (verde -)
                'es_aumento_gasto' => bccomp($var['variacion_pesos'], '0', 2) === 1,
            ];
        }

        // Ordenamiento por variación en pesos: de mayor aumento a mayor disminución (RN-070.2)
        usort($tablaCategorias, fn ($a, $b) => bccomp($b['variacion_pesos'], $a['variacion_pesos'], 2));

        return [
            'periodo_a' => [
                'desde' => $desdeA->format('Y-m-d'),
                'hasta' => $hastaA->format('Y-m-d'),
                'texto' => $desdeA->format('d/m/Y') . ' - ' . $hastaA->format('d/m/Y'),
                'dias' => $duracion['dias_a'],
            ],
            'periodo_b' => [
                'desde' => $desdeB->format('Y-m-d'),
                'hasta' => $hastaB->format('Y-m-d'),
                'texto' => $desdeB->format('d/m/Y') . ' - ' . $hastaB->format('d/m/Y'),
                'dias' => $duracion['dias_b'],
            ],
            'resumen' => [
                'ingresos' => $resumenIngresos,
                'gastos' => $resumenGastos,
                'balance' => $resumenBalance,
                'presupuesto' => $resumenPresupuesto,
            ],
            'tabla_categorias' => $tablaCategorias,
        ];
    }

    /**
     * Helper para calcular porcentaje de ejecución presupuestal de un mes.
     */
    protected function obtenerEjecucionPresupuestoMes(Usuario $usuario, CarbonImmutable $fecha): ?float
    {
        $mesStr = $fecha->format('Y-m-01');
        $presupuesto = Presupuesto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->where('mes', $mesStr)
            ->first();

        if (! $presupuesto) {
            return null;
        }

        $ejecucion = PresupuestoService::ejecucion($presupuesto, $fecha->endOfMonth());
        $asignado = (string) ($ejecucion['totales']['monto_asignado'] ?? '0');
        $gastado = (string) ($ejecucion['totales']['gastado'] ?? '0');

        if (bccomp($asignado, '0', 2) === 1) {
            return round((float) bcdiv(bcmul($gastado, '100', 6), $asignado, 4), 1);
        }

        return 0.0;
    }
}
