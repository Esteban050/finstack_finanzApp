<?php

// Formato de presentación para cantidades con muchos decimales posibles (unidades,
// precios por unidad, dividendos por acción): redondea a $maxDecimals y quita los
// ceros sobrantes, dejando al menos $minDecimals. 25.00000000 → "25"; 0.01250000 → "0,0125".
$formatValue = require __DIR__.'/format-value.php';

return static function ($value, int $maxDecimals = 8, int $minDecimals = 0) use ($formatValue): string {
    $texto = $formatValue($value, $maxDecimals);
    if ($maxDecimals === 0 || ! str_contains($texto, ',')) {
        return $texto;
    }
    [$entero, $fraccion] = explode(',', $texto, 2);
    $fraccion = rtrim($fraccion, '0');
    if (strlen($fraccion) < $minDecimals) {
        $fraccion = str_pad($fraccion, $minDecimals, '0');
    }

    return $fraccion === '' ? $entero : $entero.','.$fraccion;
};
