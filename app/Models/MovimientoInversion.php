<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RF-038 / v1.1: Movimiento/Transacción de una inversión.
 * Registra flujos con desglose tributario exacto (retención, GMF, comisiones).
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

    /** Tipos v1.1 + compatibilidad */
    public const TIPOS = [
        'COMPRA',
        'VENTA',
        'APORTE',
        'RETIRO',
        'ABONO_INTERES',
        'DIVIDENDO',
        'COMISION',
        'REDENCION',
        // Compatibilidad histórica
        'RETIRO_PARCIAL',
        'RENDIMIENTO',
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
        'monto_bruto',
        'valor_unidad',
        'trm',
        'comision',
        'retencion_aplicada',
        'gmf_aplicado',
        'impuesto_origen',
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
            'monto_bruto' => 'decimal:2',
            'valor_unidad' => 'decimal:8',
            'trm' => 'decimal:4',
            'comision' => 'decimal:2',
            'retencion_aplicada' => 'decimal:2',
            'gmf_aplicado' => 'decimal:2',
            'impuesto_origen' => 'decimal:2',
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

    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class, 'ingreso_id');
    }

    public function valoresDeducibles(): HasMany
    {
        return $this->hasMany(ValorDeducibleRegistro::class, 'transaccion_id');
    }

    public function ingresosInversion(): HasMany
    {
        return $this->hasMany(IngresoInversion::class, 'transaccion_id');
    }
}
