<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParametroTributario extends Model
{
    use HasUuids;

    protected $table = 'parametro_tributario';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'clave',
        'valor_numerico',
        'unidad',
        'vigente_desde',
        'vigente_hasta',
        'norma_referencia',
        'estado_verificacion',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'valor_numerico' => 'decimal:4',
            'vigente_desde' => 'date:Y-m-d',
            'vigente_hasta' => 'date:Y-m-d',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
