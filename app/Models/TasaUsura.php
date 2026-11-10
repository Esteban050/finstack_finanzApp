<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MOD-08 Créditos RN-01 / RF-07: tasa de usura de una modalidad durante su
 * vigencia, certificada por la Superintendencia Financiera. Es un parámetro
 * global (no pertenece a un Usuario), igual que la TRM.
 */
class TasaUsura extends Model
{
    use HasUuids;

    protected $table = 'tasas_usura';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** Créditos RN-01: modalidades que certifica la Superintendencia Financiera. */
    public const MODALIDADES = ['CONSUMO_ORDINARIO', 'MICROCREDITO', 'CONSUMO_BAJO_MONTO'];

    protected $fillable = [
        'modalidad',
        'tasa_ea',
        'vigente_desde',
        'vigente_hasta',
        'acto_administrativo',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'tasa_ea' => 'decimal:4',
            'vigente_desde' => 'date:Y-m-d',
            'vigente_hasta' => 'date:Y-m-d',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /** Créditos RN-01: la certificación de la modalidad vigente en la fecha. */
    public static function vigenteEn(string $modalidad, CarbonImmutable $fecha): ?self
    {
        return self::where('modalidad', $modalidad)
            ->where('vigente_desde', '<=', $fecha->toDateString())
            ->where('vigente_hasta', '>=', $fecha->toDateString())
            ->first();
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }
}
