<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RF-020: Fuente de ingreso del Usuario.
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class FuenteIngreso extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'fuentes_ingreso';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /**
     * RN-020.1: Clasificaciones tributarias admitidas (cédulas, art. 330 ET).
     */
    public const CLASIFICACIONES = [
        'RENTAS_TRABAJO',
        'RENTAS_CAPITAL',
        'RENTAS_NO_LABORALES',
        'PENSIONES',
        'DIVIDENDOS',
    ];

    protected $fillable = [
        'usuario_id',
        'tipo_fuente_id',
        'nombre',
        'clasificacion_tributaria',
        'pagador_entidad',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * RN-020.1: El tipo de fuente proviene del catálogo global (RN-015.2).
     */
    public function tipoFuente(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'tipo_fuente_id');
    }

    public function ingresos(): HasMany
    {
        return $this->hasMany(Ingreso::class, 'fuente_id');
    }

    /**
     * RN-020.3: Una fuente con ingresos asociados no se puede borrar, solo inactivar.
     * Cuenta también los ingresos eliminados lógicamente: siguen existiendo para auditoría.
     */
    public function tieneIngresos(): bool
    {
        return $this->ingresos()->withTrashed()->exists();
    }

    /**
     * RN-020.3: Una fuente inactiva no aparece al registrar ingresos nuevos.
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public function scopeDelUsuario(Builder $query, string $usuarioId): Builder
    {
        return $query->where('usuario_id', $usuarioId);
    }
}
