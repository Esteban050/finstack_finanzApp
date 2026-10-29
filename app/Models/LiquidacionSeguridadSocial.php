<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-033 RN-033.7: "Liquidación estimada" de aportes de un periodo (AAAA-MM),
 * con los parámetros usados. Hay una por periodo y recalcular la actualiza.
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class LiquidacionSeguridadSocial extends Model
{
    use HasUuids;

    protected $table = 'liquidaciones_seguridad_social';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** RN-033.1: ingresos registrados del mes o valor ingresado a mano. */
    public const ORIGEN_REGISTRADO = 'REGISTRADO';
    public const ORIGEN_MANUAL = 'MANUAL';

    protected $fillable = [
        'usuario_id',
        'periodo',
        'origen_ingreso',
        'ingreso_base',
        'ibc',
        'salud',
        'pension',
        'fsp',
        'arl',
        'total',
        'parametro_normativo_id',
        'parametros_usados',
    ];

    protected function casts(): array
    {
        return [
            'ingreso_base' => 'decimal:2',
            'ibc' => 'decimal:2',
            'salud' => 'decimal:2',
            'pension' => 'decimal:2',
            'fsp' => 'decimal:2',
            'arl' => 'decimal:2',
            'total' => 'decimal:2',
            'parametros_usados' => 'array',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function parametroNormativo(): BelongsTo
    {
        return $this->belongsTo(ParametroNormativo::class, 'parametro_normativo_id');
    }
}
