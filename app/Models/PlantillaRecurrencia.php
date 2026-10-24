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
 * RF-022: Plantilla de recurrencia. El Sistema genera cada ocurrencia en la
 * fecha programada. La tabla es compartida con los gastos recurrentes (RF-028).
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class PlantillaRecurrencia extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'plantillas_recurrencia';

    protected $keyType = 'string';

    public $incrementing = false;

    /** La tabla no tiene columna de actualización; solo se registra la creación. */
    public $timestamps = false;

    const CREATED_AT = 'creado_en';

    /** RN-022.1: Periodicidades admitidas. */
    public const PERIODICIDADES = [
        'SEMANAL',
        'QUINCENAL',
        'MENSUAL',
        'BIMESTRAL',
        'TRIMESTRAL',
        'SEMESTRAL',
        'ANUAL',
    ];

    /** RN-022.3: Modo de confirmación configurable. */
    public const MODOS_CONFIRMACION = ['AUTOMATICO', 'REQUIERE_CONFIRMACION'];

    protected $fillable = [
        'usuario_id',
        'tipo_movimiento',
        'periodicidad',
        'fecha_inicio',
        'fecha_fin',
        'max_ocurrencias',
        'ocurrencias_generadas',
        'modo_confirmacion',
        'es_monto_estimado',
        'datos_plantilla',
        'proxima_ejecucion',
        'activa',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'proxima_ejecucion' => 'date',
            'max_ocurrencias' => 'integer',
            'ocurrencias_generadas' => 'integer',
            'es_monto_estimado' => 'boolean',
            'activa' => 'boolean',
            'datos_plantilla' => 'array',
            'creado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ingresos(): HasMany
    {
        return $this->hasMany(Ingreso::class, 'plantilla_recurrencia_id');
    }

    public function scopeDeIngresos(Builder $query): Builder
    {
        return $query->where('tipo_movimiento', 'INGRESO');
    }

    public function scopeDelUsuario(Builder $query, string $usuarioId): Builder
    {
        return $query->where('usuario_id', $usuarioId);
    }

    /**
     * RN-022.3: En modo "Requiere confirmación" la ocurrencia nace en estado PENDIENTE.
     */
    public function estadoOcurrencia(): string
    {
        return $this->modo_confirmacion === 'REQUIERE_CONFIRMACION' ? 'PENDIENTE' : 'CONFIRMADO';
    }

    /**
     * CA-022.2: Al pausar o terminar una recurrencia no se generan más ocurrencias.
     * RN-022.1: El tope puede venir por fecha de fin o por número de ocurrencias.
     */
    public function agotada(): bool
    {
        if (! $this->activa) {
            return true;
        }

        if ($this->max_ocurrencias !== null && $this->ocurrencias_generadas >= $this->max_ocurrencias) {
            return true;
        }

        return $this->fecha_fin !== null && $this->proxima_ejecucion?->greaterThan($this->fecha_fin);
    }
}
