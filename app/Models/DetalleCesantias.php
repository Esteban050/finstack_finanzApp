<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleCesantias extends Model
{
    use HasUuids;

    protected $table = 'detalle_cesantias';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    public const REGIMENES = ['ANUALIZADO', 'TRADICIONAL'];

    protected $fillable = [
        'inversion_id',
        'fondo',
        'regimen',
        'saldo',
        'tasa_interes_esperada',
        'plazo_dias',
        'salario_promedio_6m_override',
    ];

    protected function casts(): array
    {
        return [
            'saldo' => 'decimal:2',
            'tasa_interes_esperada' => 'decimal:4',
            'plazo_dias' => 'integer',
            'salario_promedio_6m_override' => 'decimal:2',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function inversion(): BelongsTo
    {
        return $this->belongsTo(Inversion::class, 'inversion_id');
    }
}
