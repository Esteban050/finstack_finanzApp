<?php

namespace App\Services\Renta;

use App\Models\DeclaracionRenta;
use App\Models\DeclaracionRentaAjuste;
use App\Models\Usuario;
use Illuminate\Validation\ValidationException;

class InformacionComplementariaService
{
    public function __construct(
        protected ConsolidadoRentaService $consolidadoService
    ) {}

    /**
     * Obtiene o inicializa la declaración de renta anual para un usuario y año gravable.
     *
     * Con $forzarRecalculo = true se vuelven a leer los datos consolidados (ingresos, inversiones,
     * perfil) y se actualizan los valores C1, C2, C5 y C6 de la declaración. Las pantallas del módulo
     * lo usan en cada consulta para que lo registrado después de abrir la declaración por primera vez
     * (ingresos, inversiones, perfil) se refleje en la evaluación y la estimación. Una declaración
     * ya PRESENTADA queda congelada y nunca se recalcula.
     */
    public function obtenerDeclaracion(Usuario $usuario, int $anioGravable, bool $forzarRecalculo = false): DeclaracionRenta
    {
        $declaracion = DeclaracionRenta::where('usuario_id', $usuario->id)
            ->where('anio_gravable', $anioGravable)
            ->first();

        if ($declaracion && (! $forzarRecalculo || $declaracion->estado_declaracion === 'PRESENTADA')) {
            return $declaracion;
        }

        $consolidado = $this->consolidadoService->consolidar($usuario, $anioGravable);

        $patrimonioConsolidado = (float) ($consolidado['patrimonio_bruto_31_12']['total_cop'] ?? 0);
        $ingresosConsolidados = (float) ($consolidado['ingresos_brutos']['total_cop'] ?? 0);
        $pisoInversiones = (float) ($consolidado['piso_inversiones_c5']['total_cop'] ?? 0);

        if (! $declaracion) {
            $declaracion = DeclaracionRenta::create([
                'usuario_id' => $usuario->id,
                'anio_gravable' => $anioGravable,
                'c1_patrimonio_bruto_cop' => $patrimonioConsolidado,
                'c1_ajuste_manual_cop' => 0,
                'c1_valor_final_cop' => $patrimonioConsolidado,
                'c2_ingresos_brutos_cop' => $ingresosConsolidados,
                'c2_ajuste_manual_cop' => 0,
                'c2_valor_final_cop' => $ingresosConsolidados,
                'c5_piso_inversiones_cop' => $pisoInversiones,
                'c5_confirmado' => false,
                'c6_responsable_iva' => (bool) ($usuario->perfilTributarioActivo?->responsable_iva ?? false),
                'datos_consolidado_cache' => $consolidado,
                'estado_evaluacion' => 'INCOMPLETO',
            ]);
        } else {
            $declaracion->c1_patrimonio_bruto_cop = $patrimonioConsolidado;
            $declaracion->c1_valor_final_cop = max(0.0, $patrimonioConsolidado + (float) $declaracion->c1_ajuste_manual_cop);
            $declaracion->c2_ingresos_brutos_cop = $ingresosConsolidados;
            $declaracion->c2_valor_final_cop = max(0.0, $ingresosConsolidados + (float) $declaracion->c2_ajuste_manual_cop);
            $declaracion->c5_piso_inversiones_cop = $pisoInversiones;
            if ($usuario->perfilTributarioActivo) {
                $declaracion->c6_responsable_iva = (bool) $usuario->perfilTributarioActivo->responsable_iva;
            }
            $declaracion->datos_consolidado_cache = $consolidado;
            $declaracion->save();
        }

        return $declaracion;
    }

    /**
     * Guarda la información complementaria y los ajustes manuales auditados (RF-044).
     */
    public function guardarInformacionComplementaria(
        Usuario $usuario,
        int $anioGravable,
        array $datos
    ): DeclaracionRenta {
        // Se parte siempre de valores consolidados vigentes para validar y guardar los ajustes.
        $declaracion = $this->obtenerDeclaracion($usuario, $anioGravable, true);
        $consolidado = $declaracion->datos_consolidado_cache ?? $this->consolidadoService->consolidar($usuario, $anioGravable);

        $patrimonioConsolidado = (float) ($declaracion->c1_patrimonio_bruto_cop ?? $consolidado['patrimonio_bruto_31_12']['total_cop'] ?? 0);
        $ingresosConsolidados = (float) ($declaracion->c2_ingresos_brutos_cop ?? $consolidado['ingresos_brutos']['total_cop'] ?? 0);

        // 1. Validar ajuste manual de patrimonio (C1) y regla de no negatividad (RN-044.2 / CA-044.2)
        $ajustePatrimonio = isset($datos['c1_ajuste_manual_cop']) ? (float) $datos['c1_ajuste_manual_cop'] : (float) $declaracion->c1_ajuste_manual_cop;
        if (($patrimonioConsolidado + $ajustePatrimonio) < 0) {
            $maxRestar = number_format($patrimonioConsolidado, 0, ',', '.');
            throw ValidationException::withMessages([
                'c1_ajuste_manual_cop' => "El valor final del patrimonio bruto no puede ser negativo. Revisa el ajuste: el máximo que puedes restar es $ {$maxRestar}.",
            ]);
        }

        // 2. Validar ajuste manual de ingresos (C2) y regla de no negatividad
        $ajusteIngresos = isset($datos['c2_ajuste_manual_cop']) ? (float) $datos['c2_ajuste_manual_cop'] : (float) $declaracion->c2_ajuste_manual_cop;
        if (($ingresosConsolidados + $ajusteIngresos) < 0) {
            $maxRestar = number_format($ingresosConsolidados, 0, ',', '.');
            throw ValidationException::withMessages([
                'c2_ajuste_manual_cop' => "El valor final de los ingresos brutos no puede ser negativo. Revisa el ajuste: el máximo que puedes restar es $ {$maxRestar}.",
            ]);
        }

        // 3. Justificación obligatoria si el ajuste cambia a un valor distinto de 0 (RN-044.3).
        //    Volver un ajuste a $0 (revertirlo) no exige nota: el formulario oculta la explicación
        //    cuando ambos ajustes quedan en 0, y se deja constancia con una nota automática.
        $cambioPatrimonio = abs($ajustePatrimonio - (float) $declaracion->c1_ajuste_manual_cop) > 0.001;
        $cambioIngresos = abs($ajusteIngresos - (float) $declaracion->c2_ajuste_manual_cop) > 0.001;

        if ($cambioPatrimonio || $cambioIngresos) {
            $justificacion = trim((string) ($datos['justificacion_ajuste'] ?? ''));
            $len = mb_strlen($justificacion);

            $hayAjusteNoCero = ($cambioPatrimonio && abs($ajustePatrimonio) > 0.001)
                || ($cambioIngresos && abs($ajusteIngresos) > 0.001);

            if ($hayAjusteNoCero) {
                if ($len < 10 || $len > 500) {
                    throw ValidationException::withMessages([
                        'justificacion_ajuste' => 'Todo ajuste manual requiere obligatoriamente una nota explicativa de entre 10 y 500 caracteres (RN-044.3).',
                    ]);
                }
            } elseif ($len > 500) {
                throw ValidationException::withMessages([
                    'justificacion_ajuste' => 'La nota explicativa no puede superar los 500 caracteres.',
                ]);
            } elseif ($len < 10) {
                $justificacion = 'Ajuste manual revertido a $0';
            }

            // Registrar auditoría de patrimonio
            if ($cambioPatrimonio) {
                DeclaracionRentaAjuste::create([
                    'declaracion_renta_id' => $declaracion->id,
                    'concepto' => 'PATRIMONIO_BRUTO',
                    'valor_anterior_cop' => $declaracion->c1_valor_final_cop,
                    'valor_nuevo_cop' => $patrimonioConsolidado + $ajustePatrimonio,
                    'monto_ajuste_cop' => $ajustePatrimonio,
                    'justificacion' => $justificacion,
                    'usuario_id' => $usuario->id,
                    'creado_en' => now(),
                ]);
            }

            // Registrar auditoría de ingresos
            if ($cambioIngresos) {
                DeclaracionRentaAjuste::create([
                    'declaracion_renta_id' => $declaracion->id,
                    'concepto' => 'INGRESOS_BRUTOS',
                    'valor_anterior_cop' => $declaracion->c2_valor_final_cop,
                    'valor_nuevo_cop' => $ingresosConsolidados + $ajusteIngresos,
                    'monto_ajuste_cop' => $ajusteIngresos,
                    'justificacion' => $justificacion,
                    'usuario_id' => $usuario->id,
                    'creado_en' => now(),
                ]);
            }
        }

        // 4. Actualizar campos de la declaración
        $declaracion->c1_ajuste_manual_cop = $ajustePatrimonio;
        $declaracion->c1_valor_final_cop = $patrimonioConsolidado + $ajustePatrimonio;

        $declaracion->c2_ajuste_manual_cop = $ajusteIngresos;
        $declaracion->c2_valor_final_cop = $ingresosConsolidados + $ajusteIngresos;

        if (array_key_exists('c3_consumos_tarjeta_cop', $datos)) {
            $declaracion->c3_consumos_tarjeta_cop = max(0.0, (float) $datos['c3_consumos_tarjeta_cop']);
        }

        if (array_key_exists('c4_compras_totales_cop', $datos)) {
            $declaracion->c4_compras_totales_cop = max(0.0, (float) $datos['c4_compras_totales_cop']);
        }

        if (array_key_exists('c5_consignaciones_declaradas_cop', $datos) && $datos['c5_consignaciones_declaradas_cop'] !== null && $datos['c5_consignaciones_declaradas_cop'] !== '') {
            $declaracion->c5_consignaciones_declaradas_cop = max(0.0, (float) $datos['c5_consignaciones_declaradas_cop']);
            $declaracion->c5_confirmado = true;
        }

        if (isset($datos['c5_confirmado'])) {
            $declaracion->c5_confirmado = (bool) $datos['c5_confirmado'];
        }

        $declaracion->save();

        return $declaracion;
    }
}
