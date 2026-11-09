<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleRentaVariable extends Model
{
    use HasUuids;

    protected $table = 'detalle_renta_variable';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    public const TIPOS = ['ACCION', 'ETF'];
    public const MERCADOS = ['BVC', 'EXTERIOR'];
    public const TIPOS_REPARTO = ['DISTRIBUTIVO', 'ACUMULATIVO', 'NO_APLICA'];

    protected $fillable = [
        'inversion_id',
        'simbolo',
        'nombre_instrumento',
        'tipo',
        'mercado',
        'esta_inscrito_bvc',
        'porcentaje_circulacion_vendido_anio',
        'unidades_total',
        'costo_promedio_unitario',
        'fecha_primera_compra',
        'tipo_reparto',
        'valor_actual_unidad',
        'valor_dividendo_por_unidad',
        'moneda_dividendo',
        'trm_dividendo',
        'trm_compra',
    ];

    protected function casts(): array
    {
        return [
            'esta_inscrito_bvc' => 'boolean',
            'porcentaje_circulacion_vendido_anio' => 'decimal:2',
            'unidades_total' => 'decimal:8',
            'costo_promedio_unitario' => 'decimal:8',
            'fecha_primera_compra' => 'date:Y-m-d',
            'valor_actual_unidad' => 'decimal:8',
            'valor_dividendo_por_unidad' => 'decimal:4',
            'moneda_dividendo' => 'string',
            'trm_dividendo' => 'decimal:4',
            'trm_compra' => 'decimal:4',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function inversion(): BelongsTo
    {
        return $this->belongsTo(Inversion::class, 'inversion_id');
    }
}
