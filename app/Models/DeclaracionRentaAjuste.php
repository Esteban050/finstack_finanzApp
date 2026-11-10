<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeclaracionRentaAjuste extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'declaracion_renta_ajustes';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    const CREATED_AT = 'creado_en';

    protected $fillable = [
        'declaracion_renta_id',
        'concepto',
        'valor_anterior_cop',
        'valor_nuevo_cop',
        'monto_ajuste_cop',
        'justificacion',
        'usuario_id',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'valor_anterior_cop' => 'decimal:2',
            'valor_nuevo_cop' => 'decimal:2',
            'monto_ajuste_cop' => 'decimal:2',
            'creado_en' => 'datetime',
        ];
    }

    public function declaracion(): BelongsTo
    {
        return $this->belongsTo(DeclaracionRenta::class, 'declaracion_renta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

