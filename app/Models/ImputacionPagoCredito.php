<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MOD-08 Créditos RF-31 / 7.1: parte de un pago aplicada a un concepto y, salvo
 * el abono extraordinario, a una cuota. No se edita: un error se corrige
 * reversando el pago (RF-33).
 *
 * No lleva usuario_id: se llega a ella siempre por su pago, que sí está
 * aislado por usuario (RNG-01).
 */
class ImputacionPagoCredito extends Model
{
    use HasUuids;

    protected $table = 'imputaciones_pago_credito';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = null;

    /** Créditos 7.1: lista cerrada de conceptos. */
    public const CONCEPTOS = ['CAPITAL', 'INTERES_CORRIENTE', 'INTERES_MORA', 'SEGURO', 'CARGO', 'ABONO_EXTRAORDINARIO'];

    protected $fillable = [
        'pago_id',
        'cuota_id',
        'concepto',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'creado_en' => 'datetime',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PagoCredito::class, 'pago_id');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(CuotaCredito::class, 'cuota_id');
    }
}
