<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MOD-09 RF-055: línea del presupuesto, una por categoría o subcategoría
 * (CA-055.2, RN-055.2). No tiene usuario_id propio: el aislamiento (RNG-01)
 * llega por su presupuesto.
 */
class LineaPresupuesto extends Model
{
    use HasUuids;

    protected $table = 'lineas_presupuesto';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    public const PESOS = 'PESOS';
    public const PORC_INGRESO = 'PORC_INGRESO';

    protected $fillable = [
        'presupuesto_id',
        'categoria_id',
        'modo_asignacion',
        'porcentaje_ingreso',
        'monto_asignado',
        'saldo_trasladado',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje_ingreso' => 'decimal:2',
            'monto_asignado' => 'decimal:2',
            'saldo_trasladado' => 'decimal:2',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class, 'presupuesto_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }
}
