<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RF-038: Movimiento de una inversión. Los movimientos son los flujos con los
 * que se calcula la rentabilidad (RF-040).
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class MovimientoInversion extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'movimientos_inversion';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** RNG-07: La eliminación de registros financieros es lógica. */
    const DELETED_AT = 'eliminado_en';

    /** RN-038.1 */
    public const TIPOS = [
        'APORTE',
        'RETIRO_PARCIAL',
        'RENDIMIENTO',
        'COMISION',
        'RETENCION',
        'LIQUIDACION_TOTAL',
    ];

    protected $fillable = [
        'usuario_id',
        'inversion_id',
        'tipo',
        'fecha',
        'monto',
        'tasa_cambio_usada',
        'monto_cop',
        'unidades',
        'nota',
        'es_inicial',
        'ingreso_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'monto' => 'decimal:2',
            'tasa_cambio_usada' => 'decimal:4',
            'monto_cop' => 'decimal:2',
            'unidades' => 'decimal:8',
            'es_inicial' => 'boolean',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'eliminado_en' => 'datetime',
        ];
    }

    public function inversion(): BelongsTo
    {
        return $this->belongsTo(Inversion::class, 'inversion_id');
    }

    /** RN-038.4: el ingreso de "Rentas de capital" creado por un rendimiento. */
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class, 'ingreso_id');
    }
}
