<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RF-035: Pago real de aportes a seguridad social hecho por PILA, con el gasto
 * que se creó automáticamente al registrarlo (RN-035.3).
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class PagoSeguridadSocial extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'pagos_seguridad_social';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** RNG-07: La eliminación de registros financieros es lógica. */
    const DELETED_AT = 'eliminado_en';

    /** RN-035.4 / RN-035.5: conceptos que se pagan por planilla. */
    public const CONCEPTOS = ['salud', 'pension', 'fsp', 'arl'];

    /**
     * `total` se omite a propósito: es una columna generada por Postgres (suma
     * de los conceptos y los intereses de mora) y no se puede escribir.
     */
    protected $fillable = [
        'usuario_id',
        'periodo',
        'fecha_pago',
        'numero_planilla',
        'es_correccion',
        'salud',
        'pension',
        'fsp',
        'arl',
        'intereses_mora',
        'gasto_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pago' => 'date:Y-m-d',
            'es_correccion' => 'boolean',
            'salud' => 'decimal:2',
            'pension' => 'decimal:2',
            'fsp' => 'decimal:2',
            'arl' => 'decimal:2',
            'intereses_mora' => 'decimal:2',
            'total' => 'decimal:2',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'eliminado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** RN-035.3: el gasto de "Seguridad social" creado con el pago. */
    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Gasto::class, 'gasto_id');
    }

    /** RN-035.1: el soporte del pago (RF-030). */
    public function soporte(): HasOne
    {
        return $this->hasOne(SoportePagoSeguridadSocial::class, 'pago_id');
    }
}
