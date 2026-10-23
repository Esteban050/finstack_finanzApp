<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarioTributario extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'calendario_tributario';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'anio_vigencia',
        'tipo_obligacion',
        'digito_desde',
        'digito_hasta',
        'fecha_vencimiento',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'anio_vigencia' => 'integer',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
