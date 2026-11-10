<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MOD-08 Créditos RF-22 / RF-26: cuota programada del plan de un crédito, con
 * interés y capital como campos separados, y sus seguros y cargos aparte
 * (Créditos RF-21, 2.5). El plan vigente son las filas sin
 * `reemplazada_en_version`; las de versiones anteriores se conservan.
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class CuotaCredito extends Model
{
    use HasUuids;

    protected $table = 'cuotas_credito';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** Créditos RF-25: estados de una cuota. */
    public const ESTADOS = ['PENDIENTE', 'PAGADA', 'PAGADA_PARCIAL', 'VENCIDA'];

    protected $fillable = [
        'credito_id',
        'usuario_id',
        'version_plan',
        'numero',
        'fecha_vencimiento',
        'saldo_inicial',
        'cuota',
        'interes',
        'capital',
        'saldo_final',
        'seguro',
        'cargos',
        'reemplazada_en_version',
    ];

    protected function casts(): array
    {
        return [
            'version_plan' => 'integer',
            'numero' => 'integer',
            'fecha_vencimiento' => 'date:Y-m-d',
            'saldo_inicial' => 'decimal:2',
            'cuota' => 'decimal:2',
            'interes' => 'decimal:2',
            'capital' => 'decimal:2',
            'saldo_final' => 'decimal:2',
            'seguro' => 'decimal:2',
            'cargos' => 'decimal:2',
            'reemplazada_en_version' => 'integer',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class, 'credito_id');
    }

    public function imputaciones(): HasMany
    {
        return $this->hasMany(ImputacionPagoCredito::class, 'cuota_id');
    }
}
