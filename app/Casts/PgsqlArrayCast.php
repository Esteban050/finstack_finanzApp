<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PgsqlArrayCast implements CastsAttributes
{
    /**
     * Cast the given value from PostgreSQL format to PHP array.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $str = trim((string) $value);
        if ($str === '' || $str === '{}') {
            return [];
        }

        // Si viene en formato nativo de PostgreSQL: {"elem1","elem2"}
        if (str_starts_with($str, '{') && str_ends_with($str, '}')) {
            $inner = substr($str, 1, -1);
            if (trim($inner) === '') {
                return [];
            }
            return str_getcsv($inner, ',', '"', '\\');
        }

        // Si viene en formato JSON
        $decoded = json_decode($str, true);
        return is_array($decoded) ? $decoded : [$str];
    }

    /**
     * Prepare the given value for storage in PostgreSQL TEXT[] format.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (is_null($value)) {
            return '{}';
        }

        if (is_string($value) && str_starts_with($value, '{') && str_ends_with($value, '}')) {
            return $value;
        }

        $items = is_array($value) ? $value : [$value];
        if (empty($items)) {
            return '{}';
        }

        $formatted = array_map(function ($item) {
            $itemStr = (string) $item;
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $itemStr);
            return '"' . $escaped . '"';
        }, $items);

        return '{' . implode(',', $formatted) . '}';
    }
}
