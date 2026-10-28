<?php

namespace App\Services;

use App\Models\FuenteIngreso;
use Illuminate\Validation\ValidationException;

/**
 * MOD-03 — Reglas de cálculo y clasificación de ingresos (RF-020, RF-021).
 *
 * Toda la aritmética se hace con bcmath sobre cadenas: RNG-02 prohíbe el punto
 * flotante para montos y exige precisión decimal de 2 posiciones.
 */
class IngresoService
{
    /**
     * RN-020.2: Clasificación tributaria sugerida según el tipo de fuente
     * (catálogo RN-015.2). El usuario puede cambiar la sugerencia.
     */
    public const SUGERENCIAS = [
        'Salario' => 'RENTAS_TRABAJO',
        'Honorarios/Servicios' => 'RENTAS_TRABAJO',
        'Arrendamientos' => 'RENTAS_CAPITAL',
        'Rendimientos financieros' => 'RENTAS_CAPITAL',
        'Pensión' => 'PENSIONES',
        'Dividendos' => 'DIVIDENDOS',
    ];

    /**
     * RN-020.2: Sugerir la cédula a partir del nombre del tipo de fuente.
     * Los tipos sin regla explícita (Venta de activos, Negocio propio, Otros)
     * caen en rentas no laborales, que es la cédula residual del art. 330 ET.
     */
    public static function sugerirClasificacion(?string $nombreTipoFuente): string
    {
        if ($nombreTipoFuente === null) {
            return 'RENTAS_NO_LABORALES';
        }

        return self::SUGERENCIAS[trim($nombreTipoFuente)] ?? 'RENTAS_NO_LABORALES';
    }

    /**
     * RN-021.3: Si la moneda no es COP se guardan el monto original, la tasa usada
     * y el monto convertido a COP. Este último es el que usan todos los cálculos.
     */
    public static function convertirACop(string $monto, string $tasaCambio): string
    {
        return self::redondear(bcmul($monto, $tasaCambio, 6));
    }

    /**
     * RN-021.2: Monto neto = bruto − retención en la fuente − aportes descontados.
     *
     * En la base `ingresos.monto_neto_cop` es una columna generada que aplica esta
     * misma fórmula, así que el valor persistido no depende de este método. Se usa
     * para previsualizar en el formulario y para verificar la paridad en pruebas.
     */
    public static function calcularNeto(string $bruto, string $retencion, string $aportes): string
    {
        return self::redondear(bcsub(bcsub($bruto, $retencion, 4), $aportes, 4));
    }

    /**
     * RNG-02: Redondeo a 2 decimales, mitad hacia arriba. Todos los montos del
     * dominio son positivos (CHECK monto_original > 0, retención y aportes >= 0).
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
     * RN-021.1 / RN-021.2 / RN-021.3: Convertir a COP y verificar las relaciones
     * entre montos antes de tocar la base. Lo comparten el registro de un ingreso
     * (RF-021) y la plantilla de recurrencia (RF-022), que persiste estos mismos
     * valores en `datos_plantilla`.
     *
     * La retención y los aportes se reciben en la moneda del ingreso y se
     * convierten con la misma tasa, para que la comparación contra el monto bruto
     * de CA-021.2 se haga sobre magnitudes homogéneas.
     *
     * `$fechaTasa` es la fecha del hecho económico: con ella se busca la TRM
     * cuando el ingreso es en USD y no trae tasa (RF-017, CA-017.1).
     *
     * @throws ValidationException
     */
    public static function prepararMontos(array $validated, FuenteIngreso $fuente, string $fechaTasa): array
    {
        $moneda = strtoupper($validated['moneda']);
        $montoOriginal = (string) $validated['monto_original'];

        // RN-021.3: si la moneda no es COP se guarda la tasa usada y el monto
        // convertido, que es el que alimenta todos los cálculos. RN-017.4: en USD
        // la tasa por defecto es la TRM; otras monedas exigen la tasa manual.
        $tasa = TrmService::tasaPara($moneda, $validated['tasa_cambio_usada'] ?? null, $fechaTasa);

        $brutoCop = self::convertirACop($montoOriginal, $tasa);
        $retencionCop = self::convertirACop((string) ($validated['retencion_fuente'] ?? '0'), $tasa);
        $aportesCop = self::convertirACop((string) ($validated['aportes_descontados'] ?? '0'), $tasa);

        // RNG-09: el monto convertido también debe caber en el rango permitido.
        if (! self::montoEnRango($brutoCop)) {
            throw ValidationException::withMessages([
                'monto_original' => 'El monto convertido a pesos supera el máximo permitido de $ 999.999.999.999,99.',
            ]);
        }

        // CA-021.2: una retención mayor que el monto bruto se rechaza.
        if (bccomp($retencionCop, $brutoCop, 2) === 1) {
            throw ValidationException::withMessages([
                'retencion_fuente' => 'La retención no puede superar el monto bruto.',
            ]);
        }

        // Constraint chk_ingreso_neto_positivo: se valida acá para dar un mensaje
        // en lugar de que el error salga de la base.
        if (bccomp(self::calcularNeto($brutoCop, $retencionCop, $aportesCop), '0', 2) === -1) {
            throw ValidationException::withMessages([
                'aportes_descontados' => 'La suma de la retención y los aportes no puede superar el monto bruto.',
            ]);
        }

        return [
            'moneda' => $moneda,
            'monto_original' => $montoOriginal,
            'tasa_cambio_usada' => $tasa,
            'monto_bruto_cop' => $brutoCop,
            'retencion_fuente_cop' => $retencionCop,
            'aportes_descontados_cop' => $aportesCop,
            // RN-021.4: el ingreso hereda la clasificación tributaria de la fuente,
            // salvo que el usuario la cambie en ese registro.
            'clasificacion_tributaria' => $validated['clasificacion_tributaria']
                ?? $fuente->clasificacion_tributaria,
        ];
    }
}
