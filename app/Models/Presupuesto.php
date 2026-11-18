<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MOD-09 RF-055: presupuesto de un mes del Usuario. Las cifras de ejecución
 * (RF-056) no se guardan: las calcula PresupuestoService con los gastos.
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class Presupuesto extends Model
{
    use HasUuids;

    protected $table = 'presupuestos';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'usuario_id',
        'mes',
        'repetir_cada_mes',
        'trasladar_saldo',
        'ingreso_base',
        'base_fijada',
    ];

    protected function casts(): array
    {
        return [
            'mes' => 'date:Y-m-d',
            'repetir_cada_mes' => 'boolean',
            'trasladar_saldo' => 'boolean',
            'ingreso_base' => 'decimal:2',
            'base_fijada' => 'boolean',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(LineaPresupuesto::class, 'presupuesto_id');
    }
}
