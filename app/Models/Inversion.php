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
 * RF-037: Inversión del Usuario, con sus movimientos (RF-038) y sus
 * valoraciones de mercado (RF-039).
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class Inversion extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'inversiones';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** RNG-07: La eliminación de registros financieros es lógica. */
    const DELETED_AT = 'eliminado_en';

    /** RN-037.1 */
    public const TIPOS = [
        'CDT',
        'CUENTA_AHORRO_ALTO_RENDIMIENTO',
        'FONDO_INVERSION_COLECTIVA',
        'ACCIONES',
        'ETF',
        'BONOS_TES',
        'FONDO_VOLUNTARIO_PENSION',
        'CRIPTOACTIVO',
        'INMUEBLE_RENTA',
        'NEGOCIO_PARTICIPACION',
        'OTRO',
    ];

    /** RN-037.2: tipos con tasa pactada, plazo y vencimiento. */
    public const CON_TASA = ['CDT', 'BONOS_TES'];

    /** RN-037.3: tipos con símbolo, unidades y precio de compra. */
    public const CON_UNIDADES = ['ACCIONES', 'ETF', 'CRIPTOACTIVO'];

    /** RN-041.4: solo el USD tiene TRM para convertir el portafolio. */
    public const MONEDAS = ['COP', 'USD'];

    public const NIVELES_RIESGO = ['BAJO', 'MEDIO', 'ALTO'];

    public const PERIODICIDADES = ['AL_VENCIMIENTO', 'MENSUAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL'];

    /** RN-037.5 */
    public const ESTADOS = ['ACTIVA', 'VENCIDA', 'LIQUIDADA'];

    protected $fillable = [
        'usuario_id',
        'nombre',
        'tipo',
        'entidad',
        'moneda',
        'fecha_apertura',
        'monto_inicial',
        'nivel_riesgo',
        'tasa_ea',
        'plazo_dias',
        'periodicidad_intereses',
        'fecha_vencimiento',
        'simbolo',
        'unidades_iniciales',
        'precio_unitario_compra',
        'estado',
        'liquidada_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'date:Y-m-d',
            'fecha_vencimiento' => 'date:Y-m-d',
            'liquidada_en' => 'date:Y-m-d',
            'monto_inicial' => 'decimal:2',
            'tasa_ea' => 'decimal:4',
            'plazo_dias' => 'integer',
            'unidades_iniciales' => 'decimal:8',
            'precio_unitario_compra' => 'decimal:8',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'eliminado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInversion::class, 'inversion_id');
    }

    public function valoraciones(): HasMany
    {
        return $this->hasMany(ValoracionInversion::class, 'inversion_id');
    }

    public function tieneTasa(): bool
    {
        return in_array($this->tipo, self::CON_TASA, true);
    }

    public function tieneUnidades(): bool
    {
        return in_array($this->tipo, self::CON_UNIDADES, true);
    }

    public function estaLiquidada(): bool
    {
        return $this->estado === 'LIQUIDADA';
    }
}
