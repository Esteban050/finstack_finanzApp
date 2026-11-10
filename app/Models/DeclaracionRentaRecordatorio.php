<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeclaracionRentaRecordatorio extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'declaracion_renta_recordatorios';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'declaracion_renta_id',
        'dias_antes',
        'fecha_programada',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'dias_antes' => 'integer',
            'fecha_programada' => 'date:Y-m-d',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function declaracion(): BelongsTo
    {
        return $this->belongsTo(DeclaracionRenta::class, 'declaracion_renta_id');
    }
}
