<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RF-037 / v1.1: Inversión del Usuario con soporte formal para los 6 productos tributarios:
 * CDT, ACCION_ETF, FIC, CUENTA_ALTO_RENDIMIENTO, FPV, CESANTIAS.
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

    /** Catálogo oficial v1.1 */
    public const TIPOS = [
        'CDT',
        'ACCION_ETF',
        'FIC',
        'CUENTA_ALTO_RENDIMIENTO',
        'FPV',
        'CESANTIAS',
    ];

    /** Tipos con tasa pactada o esperada */
    public const CON_TASA = ['CDT', 'CUENTA_ALTO_RENDIMIENTO', 'CESANTIAS'];

    /** Tipos con valor por unidad y cantidad de unidades */
    public const CON_UNIDADES = ['ACCION_ETF', 'FIC', 'FPV'];

    public const MONEDAS = ['COP', 'USD'];

    public const NIVELES_RIESGO = ['BAJO', 'MEDIO', 'ALTO'];

    public const PERIODICIDADES = ['AL_VENCIMIENTO', 'MENSUAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL'];

    public const ESTADOS = ['ACTIVA', 'CERRADA', 'VENCIDA', 'LIQUIDADA'];

    /** Movimientos estrictos permitidos por producto en v1.1 */
    public const MOVIMIENTOS_PERMITIDOS = [
        'CDT' => ['ABONO_INTERES', 'REDENCION'],
        'CUENTA_ALTO_RENDIMIENTO' => ['APORTE', 'RETIRO', 'ABONO_INTERES'],
        'ACCION_ETF' => ['COMPRA', 'VENTA', 'DIVIDENDO'],
        'FIC' => ['APORTE', 'REDENCION'],
        'FPV' => ['APORTE', 'RETIRO'],
        'CESANTIAS' => ['APORTE', 'RETIRO'],
    ];

    public const RIESGOS_SUGERIDOS = [
        'CDT' => 'BAJO',
        'CUENTA_ALTO_RENDIMIENTO' => 'BAJO',
        'FIC' => 'MEDIO',
        'ACCION_ETF' => 'ALTO',
        'FPV' => 'MEDIO',
        'CESANTIAS' => 'BAJO',
    ];

    protected $fillable = [
        'usuario_id',
        'nombre',
        'tipo',
        'entidad',
        'entidad_id',
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

    public function entidadFinanciera(): BelongsTo
    {
        return $this->belongsTo(EntidadFinanciera::class, 'entidad_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInversion::class, 'inversion_id');
    }

    public function valoraciones(): HasMany
    {
        return $this->hasMany(ValoracionInversion::class, 'inversion_id');
    }

    public function simulaciones(): HasMany
    {
        return $this->hasMany(SimulacionInversion::class, 'inversion_id');
    }

    // Relaciones 1:1 especializadas
    public function detalleCdt(): HasOne
    {
        return $this->hasOne(DetalleCdt::class, 'inversion_id');
    }

    public function detalleCuenta(): HasOne
    {
        return $this->hasOne(DetalleCuenta::class, 'inversion_id');
    }

    public function detalleRentaVariable(): HasOne
    {
        return $this->hasOne(DetalleRentaVariable::class, 'inversion_id');
    }

    public function detalleFic(): HasOne
    {
        return $this->hasOne(DetalleFic::class, 'inversion_id');
    }

    public function detalleFpv(): HasOne
    {
        return $this->hasOne(DetalleFpv::class, 'inversion_id');
    }

    public function detalleCesantias(): HasOne
    {
        return $this->hasOne(DetalleCesantias::class, 'inversion_id');
    }

    // Puentes con módulo de Ingresos
    public function valoresDeducibles(): HasMany
    {
        return $this->hasMany(ValorDeducibleRegistro::class, 'inversion_id');
    }

    public function ingresosInversion(): HasMany
    {
        return $this->hasMany(IngresoInversion::class, 'inversion_id');
    }

    public function detalle(): mixed
    {
        return match ($this->tipo) {
            'CDT' => $this->detalleCdt,
            'CUENTA_ALTO_RENDIMIENTO' => $this->detalleCuenta,
            'ACCION_ETF' => $this->detalleRentaVariable,
            'FIC' => $this->detalleFic,
            'FPV' => $this->detalleFpv,
            'CESANTIAS' => $this->detalleCesantias,
            default => null,
        };
    }

    public function tieneTasa(): bool
    {
        return in_array($this->tipo, self::CON_TASA, true);
    }

    public function tieneUnidades(): bool
    {
        if ($this->tipo === 'FPV') {
            return $this->detalleFpv && $this->detalleFpv->unidades !== null && bccomp((string) $this->detalleFpv->unidades, '0', 8) > 0;
        }

        return in_array($this->tipo, self::CON_UNIDADES, true);
    }

    public function estaLiquidada(): bool
    {
        return in_array($this->estado, ['CERRADA', 'LIQUIDADA'], true);
    }

    public function movimientosPermitidos(): array
    {
        if ($this->tipo === 'CDT') {
            $detalle = $this->detalleCdt;
            if ($detalle && in_array(strtoupper((string) $detalle->periodicidad_pago), ['VENCIMIENTO', 'AL_VENCIMIENTO'], true)) {
                return ['REDENCION'];
            }
            return ['ABONO_INTERES', 'REDENCION'];
        }

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
