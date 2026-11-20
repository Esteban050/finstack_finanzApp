<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EsquemaCostoPresunto extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'esquemas_costos_presuntos';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    public const ACTIVIDAD_SERVICIOS = 'PRESTACION_SERVICIOS';
    public const ACTIVIDAD_CAPITAL = 'RENTAS_CAPITAL';
    public const ACTIVIDAD_INMOBILIARIA = 'ACTIVIDADES_INMOBILIARIAS';

    protected $fillable = [
        'codigo_actividad',
        'nombre_actividad',
        'porcentaje_costo_presunto',
        'normativa_referencia',
        'admite_costos_reales',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje_costo_presunto' => 'decimal:2',
            'admite_costos_reales' => 'boolean',
            'activo' => 'boolean',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /**
     * Retorna todas las actividades activas indexadas por código.
     */
    public static function catalogoActivo(): array
    {
        return self::where('activo', true)
            ->get()
            ->keyBy('codigo_actividad')
            ->toArray();
    }

    /**
     * Obtiene el porcentaje de presunción para una actividad dada.
     */
    public static function porcentajePara(string $codigoActividad): string
    {
        $esquema = self::where('codigo_actividad', $codigoActividad)->where('activo', true)->first();

        if ($esquema !== null) {
            return (string) $esquema->porcentaje_costo_presunto;
        }

        return match ($codigoActividad) {
            self::ACTIVIDAD_SERVICIOS => '0.00',
            self::ACTIVIDAD_CAPITAL => '28.08',
            self::ACTIVIDAD_INMOBILIARIA => '65.70',
            default => '0.00',
        };
    }
}
