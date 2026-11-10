<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeclaracionRenta extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'declaraciones_renta';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'usuario_id',
        'anio_gravable',
        'estado_evaluacion',
        'es_preliminar',
        'estado_declaracion',
        'fecha_presentacion',
        'numero_radicado_dian',
        'c1_patrimonio_bruto_cop',
        'c1_ajuste_manual_cop',
        'c1_valor_final_cop',
        'c2_ingresos_brutos_cop',
        'c2_ajuste_manual_cop',
        'c2_valor_final_cop',
        'c3_consumos_tarjeta_cop',
        'c4_compras_totales_cop',
        'c5_consignaciones_declaradas_cop',
        'c5_piso_inversiones_cop',
        'c5_confirmado',
        'c6_responsable_iva',
        'datos_consolidado_cache',
        'datos_estimacion_cache',
    ];

    protected function casts(): array
    {
        return [
            'anio_gravable' => 'integer',
            'es_preliminar' => 'boolean',
            'c5_confirmado' => 'boolean',
            'c6_responsable_iva' => 'boolean',
            'fecha_presentacion' => 'date:Y-m-d',
            'c1_patrimonio_bruto_cop' => 'decimal:2',
            'c1_ajuste_manual_cop' => 'decimal:2',
            'c1_valor_final_cop' => 'decimal:2',
            'c2_ingresos_brutos_cop' => 'decimal:2',
            'c2_ajuste_manual_cop' => 'decimal:2',
            'c2_valor_final_cop' => 'decimal:2',
            'c3_consumos_tarjeta_cop' => 'decimal:2',
            'c4_compras_totales_cop' => 'decimal:2',
            'c5_consignaciones_declaradas_cop' => 'decimal:2',
            'c5_piso_inversiones_cop' => 'decimal:2',
            'datos_consolidado_cache' => 'array',
            'datos_estimacion_cache' => 'array',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ajustes(): HasMany
    {
        return $this->hasMany(DeclaracionRentaAjuste::class, 'declaracion_renta_id')->orderByDesc('creado_en');
    }

    public function recordatorios(): HasMany
    {
        return $this->hasMany(DeclaracionRentaRecordatorio::class, 'declaracion_renta_id')->orderBy('fecha_programada');
    }
}

