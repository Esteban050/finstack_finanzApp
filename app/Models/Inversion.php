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

    /** Movimientos financieramente admitidos por tipo de activo según guía SCFP-GI-INV */
    public const MOVIMIENTOS_PERMITIDOS = [
        'CDT' => ['RENDIMIENTO', 'RETENCION', 'COMISION', 'LIQUIDACION_TOTAL'],
        'BONOS_TES' => ['RENDIMIENTO', 'RETENCION', 'COMISION', 'LIQUIDACION_TOTAL'],
        'CUENTA_AHORRO_ALTO_RENDIMIENTO' => ['APORTE', 'RETIRO_PARCIAL', 'COMISION', 'LIQUIDACION_TOTAL'],
        'FONDO_INVERSION_COLECTIVA' => ['APORTE', 'RETIRO_PARCIAL', 'RENDIMIENTO', 'RETENCION', 'COMISION', 'LIQUIDACION_TOTAL'],
        'ACCIONES' => ['APORTE', 'RETIRO_PARCIAL', 'RENDIMIENTO', 'RETENCION', 'COMISION', 'LIQUIDACION_TOTAL'],
        'ETF' => ['APORTE', 'RETIRO_PARCIAL', 'RENDIMIENTO', 'RETENCION', 'COMISION', 'LIQUIDACION_TOTAL'],
        'CRIPTOACTIVO' => ['APORTE', 'RETIRO_PARCIAL', 'RENDIMIENTO', 'RETENCION', 'COMISION', 'LIQUIDACION_TOTAL'],
        'INMUEBLE_RENTA' => ['APORTE', 'RENDIMIENTO', 'COMISION', 'RETENCION', 'LIQUIDACION_TOTAL'],
        'NEGOCIO_PARTICIPACION' => ['APORTE', 'RENDIMIENTO', 'COMISION', 'RETENCION', 'LIQUIDACION_TOTAL'],
        'FONDO_VOLUNTARIO_PENSION' => ['APORTE', 'RETIRO_PARCIAL', 'COMISION', 'RETENCION', 'LIQUIDACION_TOTAL'],
        'OTRO' => ['APORTE', 'RETIRO_PARCIAL', 'RENDIMIENTO', 'COMISION', 'RETENCION', 'LIQUIDACION_TOTAL'],
    ];

    /** Nivel de riesgo típico sugerido por tipo (sección 4.12) */
    public const RIESGOS_SUGERIDOS = [
        'CDT' => 'BAJO',
        'BONOS_TES' => 'BAJO',
        'CUENTA_AHORRO_ALTO_RENDIMIENTO' => 'BAJO',
        'FONDO_INVERSION_COLECTIVA' => 'MEDIO',
        'INMUEBLE_RENTA' => 'MEDIO',
        'ACCIONES' => 'ALTO',
        'ETF' => 'MEDIO',
        'CRIPTOACTIVO' => 'ALTO',
        'NEGOCIO_PARTICIPACION' => 'ALTO',
        'FONDO_VOLUNTARIO_PENSION' => 'MEDIO',
        'OTRO' => 'MEDIO',
    ];

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
        'rentabilidad_esperada_porc',
        'plazo_meses_esperado',
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
            'rentabilidad_esperada_porc' => 'decimal:4',
            'plazo_meses_esperado' => 'integer',
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

    public function movimientosPermitidos(): array
    {
        return self::MOVIMIENTOS_PERMITIDOS[$this->tipo] ?? MovimientoInversion::TIPOS;
    }

    public function permiteMovimiento(string $tipo): bool
    {
        return in_array($tipo, $this->movimientosPermitidos(), true);
    }

    public static function riesgoSugerido(string $tipo): ?string
    {
        return self::RIESGOS_SUGERIDOS[$tipo] ?? null;
    }
}
