<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RF-021: Ingreso registrado por el Usuario, asociado a una fuente (RF-020).
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class Ingreso extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'ingresos';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** RNG-07: La eliminación de registros financieros es lógica. */
    const DELETED_AT = 'eliminado_en';

    /** RN-021.1 / RN-022.3: Origen y estado de la ocurrencia. */
    public const ORIGENES = ['MANUAL', 'RECURRENTE', 'IMPORTADO'];
    public const ESTADOS = ['CONFIRMADO', 'PENDIENTE'];

    /**
     * `monto_neto_cop` se omite a propósito: es una columna generada por Postgres
     * (RN-021.2, monto_bruto − retención − aportes) y no se puede escribir.
     */
    protected $fillable = [
        'usuario_id',
        'fuente_id',
        'plantilla_recurrencia_id',
        'fecha_recepcion',
        'clasificacion_tributaria',
        'moneda',
        'monto_original',
        'tasa_cambio_usada',
        'monto_bruto_cop',
        'retencion_fuente_cop',
        'aportes_descontados_cop',
        'origen',
        'estado',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_recepcion' => 'date',
            'monto_original' => 'decimal:2',
            'tasa_cambio_usada' => 'decimal:4',
            'monto_bruto_cop' => 'decimal:2',
            'retencion_fuente_cop' => 'decimal:2',
            'aportes_descontados_cop' => 'decimal:2',
            'monto_neto_cop' => 'decimal:2',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'eliminado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteIngreso::class, 'fuente_id');
    }

  //  public function plantillaRecurrencia(): BelongsTo
    //{
      //  return $this->belongsTo(PlantillaRecurrencia::class, 'plantilla_recurrencia_id');
    //}

    /**
     * RN-022.3: Una ocurrencia "Pendiente" no entra en los cálculos hasta confirmarse.
     */
    public function scopeConfirmados(Builder $query): Builder
    {
        return $query->where('estado', 'CONFIRMADO');
    }

    public function scopeDelUsuario(Builder $query, string $usuarioId): Builder
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'PENDIENTE';
    }
}
