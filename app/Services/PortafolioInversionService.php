<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

class PortafolioInversionService
{
    public const PRODUCTOS_ADMINISTRADOS = ['FIC', 'FPV', 'CESANTIAS'];

    public const RANGOS_RIESGO = [
        'BAJO' => [
            'nombre' => 'Bajo / Conservador',
            'min_ea' => 4.00,
            'max_ea' => 7.00,
            'sugerido_ea' => 5.50,
            'color' => '#16a34a',
            'badge_bg' => '#f0fdf4',
            'badge_border' => '#86efac',
            'descripcion' => 'Prioriza la preservación del capital y la liquidez con baja volatilidad.',
        ],
        'MEDIO' => [
            'nombre' => 'Medio / Moderado',
            'min_ea' => 6.00,
            'max_ea' => 12.00,
            'sugerido_ea' => 8.50,
            'color' => '#d97706',
            'badge_bg' => '#fffbeb',
            'badge_border' => '#fcd34d',
            'descripcion' => 'Balance óptimo entre estabilidad en renta fija y apreciación en renta variable.',
        ],
        'ALTO' => [
            'nombre' => 'Alto / Crecimiento',
            'min_ea' => -10.00,
            'max_ea' => 20.00,
            'sugerido_ea' => 12.00,
            'color' => '#7c3aed',
            'badge_bg' => '#f5f3ff',
            'badge_border' => '#c4b5fd',
            'descripcion' => 'Busca maximizar el crecimiento patrimonial asumiendo fluctuaciones de mercado.',
        ],
    ];

    public const PORTAFOLIOS = [
        'FIC' => [
            'BAJO' => [
                'titulo' => 'Portafolio FIC Conservador / Liquidez',
                'enfoque' => 'Preservación de capital con disponibilidad a la vista y vencimientos cortos.',
                'activos' => [
                    ['nombre' => 'Títulos de Deuda Pública (TES Tasa Fija / UVR corto plazo)', 'porcentaje' => 55, 'categoria' => 'Deuda Soberana'],
                    ['nombre' => 'Certificados de Depósito a Término (CDT Bancarios AAA)', 'porcentaje' => 35, 'categoria' => 'Renta Fija Privada'],
                    ['nombre' => 'Cuentas de Ahorro Remuneradas y Repos a la vista', 'porcentaje' => 10, 'categoria' => 'Liquidez Inmediata'],
                ],
            ],
            'MEDIO' => [
                'titulo' => 'Portafolio FIC Moderado / Renta Mixta',
                'enfoque' => 'Combinación de renta fija corporativa nacional con cuotas inmobiliarias y acciones.',
                'activos' => [
                    ['nombre' => 'Bonos Corporativos Grado de Inversión (AAA / AA+)', 'porcentaje' => 40, 'categoria' => 'Renta Fija'],
                    ['nombre' => 'Deuda Pública Nacional (TES mediano plazo 2 a 5 años)', 'porcentaje' => 30, 'categoria' => 'Deuda Soberana'],
                    ['nombre' => 'Fondos Inmobiliarios y Crédito Estructurado Local', 'porcentaje' => 20, 'categoria' => 'Activos Alternativos'],
                    ['nombre' => 'Renta Variable Local (Acciones BVC índice MSCI Colcap)', 'porcentaje' => 10, 'categoria' => 'Renta Variable'],
                ],
            ],
            'ALTO' => [
                'titulo' => 'Portafolio FIC Mayor Riesgo / Crecimiento Global',
                'enfoque' => 'Alta exposición a mercados bursátiles internacionales y sectores dinámicos.',
                'activos' => [
                    ['nombre' => 'Acciones Internacionales y ETFs Globales (S&P 500, MSCI World)', 'porcentaje' => 50, 'categoria' => 'Renta Variable Global'],
                    ['nombre' => 'Acciones Colombianas de Mediana y Pequeña Capitalización', 'porcentaje' => 30, 'categoria' => 'Renta Variable Local'],
                    ['nombre' => 'Fondos de Capital Privado, Tecnología y Startups', 'porcentaje' => 20, 'categoria' => 'Alternativos & PE'],
                ],
            ],
        ],
        'FPV' => [
            'BAJO' => [
                'titulo' => 'Portafolio FPV Renta Fija Conservadora',
                'enfoque' => 'Seguridad pensional a corto/mediano plazo respaldada por emisores soberanos y bancarios.',
                'activos' => [
                    ['nombre' => 'TES Soberanos en Pesos y UVR (Ministerio de Hacienda)', 'porcentaje' => 65, 'categoria' => 'Deuda Pública'],
                    ['nombre' => 'Bonos Ordinarios y Subordinados de Bancos Colombianos AAA', 'porcentaje' => 25, 'categoria' => 'Renta Fija Privada'],
                    ['nombre' => 'Caja Institucional y Depósitos Bancarios a Término', 'porcentaje' => 10, 'categoria' => 'Liquidez'],
                ],
            ],
            'MEDIO' => [
                'titulo' => 'Portafolio FPV Balanceado Global',
                'enfoque' => 'Horizonte medio para acumulación pensional con diversificación geográfica.',
                'activos' => [
                    ['nombre' => 'Renta Fija Institucional Colombiana (TES y Bonos)', 'porcentaje' => 45, 'categoria' => 'Renta Fija Nacional'],
                    ['nombre' => 'ETFs y Fondos Mutuos de Renta Variable Global', 'porcentaje' => 30, 'categoria' => 'Renta Variable Global'],
                    ['nombre' => 'Fondos Inmobiliarios y Fideicomisos de Infraestructura', 'porcentaje' => 15, 'categoria' => 'Alternativos'],
                    ['nombre' => 'Deuda Internacional Grado de Inversión (Dólares)', 'porcentaje' => 10, 'categoria' => 'Deuda Externa'],
                ],
            ],
            'ALTO' => [
                'titulo' => 'Portafolio FPV Renta Variable Crecimiento Agresivo',
                'enfoque' => 'Estrategia de largo plazo para inversionistas jóvenes que toleran volatilidad por mayor retorno.',
                'activos' => [
                    ['nombre' => 'Renta Variable de Estados Unidos, Europa y Asia Emergente', 'porcentaje' => 70, 'categoria' => 'Renta Variable Internacional'],
                    ['nombre' => 'Renta Variable Nacional en Bolsa de Valores de Colombia', 'porcentaje' => 20, 'categoria' => 'Renta Variable Local'],
                    ['nombre' => 'Fondos Temáticos Globales (Inteligencia Artificial, Energías Limpias)', 'porcentaje' => 10, 'categoria' => 'Activos Temáticos'],
                ],
            ],
        ],
        'CESANTIAS' => [
            'BAJO' => [
                'titulo' => 'Portafolio Cesantías Subcuenta Corto Plazo',
                'enfoque' => 'Establecido por la Ley 50/1990 para trabajadores con expectativa de retiro en el corto plazo.',
                'activos' => [
                    ['nombre' => 'Títulos de Deuda Pública Soberana con vencimiento menor a 1 año', 'porcentaje' => 80, 'categoria' => 'Deuda Soberana Corto Plazo'],
                    ['nombre' => 'Depósitos de Dinero a la Vista y en Cuenta de Margen Garantizada', 'porcentaje' => 20, 'categoria' => 'Liquidez Inmediata'],
                ],
            ],
            'MEDIO' => [
                'titulo' => 'Portafolio Cesantías Subcuenta Largo Plazo Moderado',
                'enfoque' => 'Portafolio principal para trabajadores con permanencia mayor a un año.',
                'activos' => [
                    ['nombre' => 'Títulos TES Tasa Fija y TES UVR (Largo Plazo)', 'porcentaje' => 60, 'categoria' => 'Deuda Pública'],
                    ['nombre' => 'Bonos Corporativos Empresariales y Títulos Hipotecarios (TIPS)', 'porcentaje' => 30, 'categoria' => 'Renta Fija Institucional'],
                    ['nombre' => 'Renta Variable Local (Acciones del índice Colcap)', 'porcentaje' => 10, 'categoria' => 'Renta Variable'],
                ],
            ],
            'ALTO' => [
                'titulo' => 'Portafolio Cesantías Subcuenta Largo Plazo Mayor Riesgo',
                'enfoque' => 'Maximiza el ahorro de cesantías con horizonte de 5+ años o retiro para jubilación.',
                'activos' => [
                    ['nombre' => 'Fondos Bursátiles Internacionales (ETFs de Índices Bursátiles Globales)', 'porcentaje' => 50, 'categoria' => 'Renta Variable Global'],
                    ['nombre' => 'Títulos de Deuda Soberana de Mediano y Largo Plazo', 'porcentaje' => 30, 'categoria' => 'Deuda Soberana'],
                    ['nombre' => 'Inversión en Infraestructura Nacional (4G/5G) y Deuda Privada', 'porcentaje' => 20, 'categoria' => 'Alternativos & Infraestructura'],
                ],
            ],
        ],
    ];

    public static function esProductoAdministrado(string $tipo): bool
    {
        return in_array($tipo, self::PRODUCTOS_ADMINISTRADOS, true);
    }

    public static function obtenerPortafolio(string $tipo, string $nivelRiesgo): ?array
    {
        $tipo = strtoupper(trim($tipo));
        $nivelRiesgo = strtoupper(trim($nivelRiesgo));

        if (! isset(self::PORTAFOLIOS[$tipo][$nivelRiesgo])) {
            return null;
        }

        $portafolio = self::PORTAFOLIOS[$tipo][$nivelRiesgo];
        $rango = self::RANGOS_RIESGO[$nivelRiesgo] ?? self::RANGOS_RIESGO['MEDIO'];

        return array_merge($portafolio, [
            'tipo' => $tipo,
            'nivel_riesgo' => $nivelRiesgo,
            'rango_ea' => $rango,
        ]);
    }

    public static function portafoliosParaProducto(string $tipo): array
    {
        $tipo = strtoupper(trim($tipo));
        if (! isset(self::PORTAFOLIOS[$tipo])) {
            return [];
        }

        $res = [];
        foreach (['BAJO', 'MEDIO', 'ALTO'] as $nivel) {
            $res[$nivel] = self::obtenerPortafolio($tipo, $nivel);
        }

        return $res;
    }

    /**
     * Genera una serie de puntos mensuales para la proyección teórica de capitalización compuesta.
     * V(m) = Monto * (1 + EA)^(dias/365)
     */
    public static function generarCurvaProyeccion(float $montoInicial, float $tasaEa, int $meses, CarbonInterface|DateTimeInterface $fechaInicio): array
    {
        $fechaInicio = CarbonImmutable::parse($fechaInicio);
        $puntos = [];
        $tasaDecimal = $tasaEa / 100.0;

        for ($m = 0; $m <= $meses; $m++) {
            $fechaPunto = $fechaInicio->addMonths($m);
            $dias = $fechaInicio->diffInDays($fechaPunto);

            // Fórmula de interés compuesto exacto
            $factor = $dias > 0 ? pow(max(0.0001, 1.0 + $tasaDecimal), $dias / 365.0) : 1.0;
            $valorProyectado = round($montoInicial * $factor, 2);
            $gananciaProyectada = round($valorProyectado - $montoInicial, 2);

            $puntos[] = [
                'mes' => $m,
                'fecha' => $fechaPunto->toDateString(),
                'etiqueta' => $fechaPunto->format('d/m/Y'),
                'valor' => $valorProyectado,
                'ganancia' => $gananciaProyectada,
            ];
        }

        return $puntos;
    }

    /**
     * Calcula el saldo de mercado resultante a partir de una tasa EA y los días transcurridos.
     */
    public static function calcularSaldoPorRendimiento(float $montoInicial, float $tasaEa, CarbonInterface|DateTimeInterface $fechaInicio, CarbonInterface|DateTimeInterface $fechaCorte): float
    {
        $fechaInicio = CarbonImmutable::parse($fechaInicio);
        $fechaCorte = CarbonImmutable::parse($fechaCorte);

        $dias = $fechaInicio->diffInDays($fechaCorte);
        if ($dias <= 0) {
            return $montoInicial;
        }

        $tasaDecimal = $tasaEa / 100.0;
        // Si la tasa es negativa (ej. -10%), 1 + tasaDecimal = 0.90
        $base = max(0.0001, 1.0 + $tasaDecimal);
        $factor = pow($base, $dias / 365.0);

        return round($montoInicial * $factor, 2);
    }

    /**
     * Dado un saldo actual y el monto inicial, despeja la Tasa Efectiva Anual lograda.
     * EA = (Saldo / Monto)^(365 / dias) - 1
     */
    public static function calcularRendimientoRealEa(float $montoInicial, float $saldoActual, CarbonInterface|DateTimeInterface $fechaInicio, CarbonInterface|DateTimeInterface $fechaCorte): float
    {
        $fechaInicio = CarbonImmutable::parse($fechaInicio);
        $fechaCorte = CarbonImmutable::parse($fechaCorte);

        $dias = $fechaInicio->diffInDays($fechaCorte);
        if ($dias <= 0 || $montoInicial <= 0 || $saldoActual <= 0) {
            return 0.0;
        }

        $ratio = $saldoActual / $montoInicial;
        $exponente = 365.0 / (float) $dias;
        $ea = (pow($ratio, $exponente) - 1.0) * 100.0;

        return round($ea, 2);
    }
}
