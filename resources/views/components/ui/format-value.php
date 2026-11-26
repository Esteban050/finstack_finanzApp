<?php

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

// Formato exclusivamente de presentación: no convierte montos a float.
return static function ($value, int $precision = 0, string $decimalSeparator = ',', string $thousandsSeparator = '.'): string {
    $number = (string) BigDecimal::of($value === null || $value === '' ? '0' : (string) $value)->toScale($precision, RoundingMode::HalfUp);
    [$whole, $fraction] = array_pad(explode('.', $number, 2), 2, '');
    $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', $thousandsSeparator, $whole);
    return $whole . ($precision ? $decimalSeparator . $fraction : '');
};
