<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MOD-08 Créditos RF-29 / RF-33: pago real de un crédito. Un reverso no lo
 * borra: lo marca con `reversado_en` y su motivo, y sus imputaciones dejan de
 * contar. Cada pago genera su gasto en MOD-04 (`gasto_id`; en vivienda, los
 * intereses van aparte en `gasto_intereses_id`).
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class PagoCredito extends Model
{
    use HasUuids;

    protected $table = 'pagos_credito';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** Créditos RF-32: a dónde va lo que sobra después de lo exigible. */
    public const APLICACIONES_EXCEDENTE = ['ANTICIPO', 'ABONO_CAPITAL'];

    /** Créditos RF-34 / RN-04: qué reduce un abono extraordinario. */
    public const REDUCCIONES = ['PLAZO', 'CUOTA'];

    protected $fillable = [
        'credito_id',
        'usuario_id',
        'numero',
        'fecha',
        'valor',
        'medio',
        'referencia',
        'aplicacion_excedente',
        'reducir',
        'version_plan_generada',
        'gasto_id',
        'gasto_intereses_id',
        'reversado_en',
        'motivo_reverso',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fecha' => 'date:Y-m-d',
            'valor' => 'decimal:2',
            'version_plan_generada' => 'integer',
            'reversado_en' => 'datetime',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /** Créditos RF-33: los pagos que cuentan son los no reversados. */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('reversado_en');
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class, 'credito_id');
    }

    public function imputaciones(): HasMany
    {
        return $this->hasMany(ImputacionPagoCredito::class, 'pago_id');
    }
}
