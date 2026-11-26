<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cuenta de débito/ahorro del usuario. El saldo disponible se deriva de sus
 * ingresos y gastos (SaldoCuentaService); aquí solo vive el saldo inicial.
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class CuentaBancaria extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'cuentas_bancarias';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** RNG-07: La eliminación de registros financieros es lógica. */
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'usuario_id',
        'entidad_financiera_id',
        'banco_otro',
        'alias',
        'saldo_inicial',
        'es_exenta_gmf',
    ];

    protected function casts(): array
    {
        return [
            'saldo_inicial' => 'decimal:2',
            'es_exenta_gmf' => 'boolean',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'eliminado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function entidad(): BelongsTo
    {
        return $this->belongsTo(EntidadFinanciera::class, 'entidad_financiera_id');
    }

    /** Nombre del banco para mostrar: el del catálogo o, si no estaba, el que escribió el usuario. */
    protected function nombreBanco(): Attribute
    {
        return Attribute::get(fn () => $this->entidad?->nombre ?? $this->banco_otro ?? '—');
    }

    public function ingresos(): HasMany
    {
        return $this->hasMany(Ingreso::class, 'cuenta_id');
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'cuenta_id');
    }
}
