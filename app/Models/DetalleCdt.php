<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleCdt extends Model
{
    use HasUuids;

    protected $table = 'detalle_cdt';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'inversion_id',
        'capital',
        'tasa_valor',
        'tipo_tasa',
        'periodicidad_nominal',
        'plazo_dias',
        'fecha_vencimiento',
        'periodicidad_pago',
        'es_cdat',
        'base_dias',
    ];

    protected function casts(): array
    {
        return [
            'capital' => 'decimal:2',
            'tasa_valor' => 'decimal:4',
            'plazo_dias' => 'integer',
            'fecha_vencimiento' => 'date:Y-m-d',
            'es_cdat' => 'boolean',
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
