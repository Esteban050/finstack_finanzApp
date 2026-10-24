<?php

namespace App\Models;

use App\Casts\PgsqlArrayCast;
use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RF-025: Gasto registrado por el Usuario, clasificado en las seis dimensiones
 * de RF-026 (categoría/subcategoría, naturaleza, necesidad, medio de pago,
 * relevancia tributaria y etiquetas libres).
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class Gasto extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'gastos';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** RNG-07: La eliminación de registros financieros es lógica. */
    const DELETED_AT = 'eliminado_en';

    /** RN-026.1 (2): Naturaleza. La sugiere la subcategoría (RN-026.2). */
    public const NATURALEZAS = ['FIJO', 'VARIABLE'];

    /** RN-026.1 (3): Necesidad. La sugiere la subcategoría (RN-026.2). */
    public const NECESIDADES = ['ESENCIAL', 'DISCRECIONAL'];

    /** RN-025.1 / RN-026.1 (4): Medio de pago. */
    public const MEDIOS_PAGO = [
        'EFECTIVO',
        'TARJETA_DEBITO',
        'TARJETA_CREDITO',
        'TRANSFERENCIA',
        'BILLETERA_DIGITAL',
        'OTRO',
    ];

    /** RN-026.1 (5): Relevancia tributaria. Por defecto "No deducible". */
    public const RELEVANCIAS = ['NO_DEDUCIBLE', 'POTENCIALMENTE_DEDUCIBLE'];

    /** RN-026.1 (5): Subtipos de un gasto potencialmente deducible. */
    public const SUBTIPOS_DEDUCCION = [
        'INTERESES_VIVIENDA',
        'MEDICINA_PREPAGADA',
        'DEPENDIENTES',
        'AFC_VOLUNTARIOS',
        'GMF',
        'OTROS',
    ];

    /** RN-029.1 / RN-028.2: Origen y estado de la ocurrencia. */
    public const ORIGENES = ['MANUAL', 'RECURRENTE', 'IMPORTADO'];
    public const ESTADOS = ['CONFIRMADO', 'PENDIENTE'];

    /**
     * `monto_cop` sí es escribible, a diferencia de `ingresos.monto_neto_cop`:
     * en gastos no hay una fórmula entre columnas que Postgres pueda generar,
     * la conversión a pesos la hace GastoService (RN-025.1, RNG-02).
     */
    protected $fillable = [
        'usuario_id',
        'categoria_id',
        'subcategoria_id',
        'plantilla_recurrencia_id',
        'fecha',
        'moneda',
        'monto_original',
        'tasa_cambio_usada',
        'monto_cop',
        'naturaleza',
        'necesidad',
        'medio_pago',
        'relevancia_tributaria',
        'subtipo_deduccion',
        'etiquetas',
        'comercio_beneficiario',
        'origen',
        'estado',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto_original' => 'decimal:2',
            'tasa_cambio_usada' => 'decimal:4',
            'monto_cop' => 'decimal:2',
            // La columna es varchar(30)[] de Postgres, no jsonb (RN-026.1, etiquetas).
            'etiquetas' => PgsqlArrayCast::class,
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'eliminado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** RN-026.1 (1): Jerarquía de dos niveles, obligatoria. */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function subcategoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'subcategoria_id');
    }

    public function plantillaRecurrencia(): BelongsTo
    {
        return $this->belongsTo(PlantillaRecurrencia::class, 'plantilla_recurrencia_id');
    }

    /**
     * CA-028.2: Un gasto con "Monto estimado" no entra en la ejecución
     * presupuestal hasta que se confirma, así que tampoco en los acumulados
     * de RN-025.2 ni en los totales de RN-029.1.
     */
    public function scopeConfirmados(Builder $query): Builder
    {
        return $query->where('estado', 'CONFIRMADO');
    }

    public function scopeDelUsuario(Builder $query, string $usuarioId): Builder
    {
        return $query->where('usuario_id', $usuarioId);
    }

    /** RN-025.2: Solo los consumos con tarjeta de crédito alimentan RF-044. */
    public function scopeConTarjetaCredito(Builder $query): Builder
    {
        return $query->where('medio_pago', 'TARJETA_CREDITO');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'PENDIENTE';
    }

    /** RN-026.3: Los gastos potencialmente deducibles se consolidan en RF-043. */
    public function esPotencialmenteDeducible(): bool
    {
        return $this->relevancia_tributaria === 'POTENCIALMENTE_DEDUCIBLE';
    }
}
