<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TablaExencionCesantias extends Model
{
    use HasUuids;

    protected $table = 'tabla_exencion_cesantias';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'uvt_desde',
        'uvt_hasta',
        'porcentaje_exento',
        'vigente_desde',
    ];

    protected function casts(): array
    {
        return [
            'uvt_desde' => 'decimal:2',
            'uvt_hasta' => 'decimal:2',
            'porcentaje_exento' => 'decimal:2',
            'vigente_desde' => 'date:Y-m-d',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }
}
