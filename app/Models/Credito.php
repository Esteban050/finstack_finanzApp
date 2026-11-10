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
 * MOD-08 RF-048: Crédito del Usuario con sus condiciones (tipo, banco,
 * propósito, monto, tasa, plazo, periodicidad y sistema de amortización:
 * francés o alemán, Créditos 2.2 / 2.4). La tasa se guarda como se
 * ingresó y también en EA y periódica (Créditos RF-05 / 7.2). La tabla de
 * amortización (RF-049) se calcula en CreditoService a partir de estos datos.
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class Credito extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'creditos';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** RNG-07: La eliminación de registros financieros es lógica. */
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'usuario_id',
        'tipo_credito',
        'banco',
        'proposito',
        'monto',
        'tipo_tasa',
        'tasa_ingresada',
        'capitalizacion',
        'tasa_ea',
        'tasa_periodica',
        'plazo_meses',
        'periodicidad',
        'numero_cuotas',
        'sistema_amortizacion',
        'fecha_inicio',
        'fecha_primer_pago',
        'seguro_porc_mensual',
        'cargo_fijo',
        'valor_inmueble',
        'es_vis',
        'tasa_mora_ea',
        'version_plan_vigente',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'tasa_ingresada' => 'decimal:6',
            'tasa_ea' => 'decimal:18',
            'tasa_periodica' => 'decimal:18',
            'plazo_meses' => 'integer',
            'numero_cuotas' => 'integer',
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_primer_pago' => 'date:Y-m-d',
            'seguro_porc_mensual' => 'decimal:6',
            'cargo_fijo' => 'decimal:2',
            'valor_inmueble' => 'decimal:2',
            'es_vis' => 'boolean',
            'tasa_mora_ea' => 'decimal:18',
            'version_plan_vigente' => 'integer',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'eliminado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** Créditos RF-22 / RF-26: todas las cuotas, de todas las versiones del plan. */
    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaCredito::class, 'credito_id');
    }

    /** Créditos RF-26: el plan vigente, en orden. */
    public function cuotasVigentes(): HasMany
    {
        return $this->cuotas()->whereNull('reemplazada_en_version')->orderBy('numero');
    }

    /** Créditos RF-29: pagos, reversados incluidos. */
    public function pagos(): HasMany
    {
        return $this->hasMany(PagoCredito::class, 'credito_id');
    }
}
