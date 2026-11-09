<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleCuenta extends Model
{
    use HasUuids;

    protected $table = 'detalle_cuenta';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'inversion_id',
        'saldo_inicial',
        'tasa_ea',
        'es_cuenta_gmf_exenta',
        'base_dias',
        'periodicidad_abono',
    ];

    protected function casts(): array
    {
        return [
            'saldo_inicial' => 'decimal:2',
            'tasa_ea' => 'decimal:4',
            'es_cuenta_gmf_exenta' => 'boolean',
            'base_dias' => 'integer',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function inversion(): BelongsTo
    {
        return $this->belongsTo(Inversion::class, 'inversion_id');
    }
}
