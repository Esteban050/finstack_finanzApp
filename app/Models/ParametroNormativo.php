<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParametroNormativo extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'parametros_normativos';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    const CREATED_AT = 'creado_en';

    protected $fillable = [
        'anio_vigencia',
        'version',
        'es_activa',
        'acto_administrativo',
        'fecha_acto',
        'valor_uvt',
        'valor_smmlv',
        'tarifa_salud_indep',
        'tarifa_pension_indep',
        'tarifa_salud_emp',
        'tarifa_pension_emp',
        'porc_ibc_indep',
        'porc_ibc_integral',
        'ibc_min_smmlv',
        'ibc_max_smmlv',
        'tope_patrimonio_uvt',
        'tope_ingresos_uvt',
        'tope_tc_uvt',
        'tope_compras_uvt',
        'tope_consignaciones_uvt',
        'limite_renta_exenta_25_uvt',
        'porc_limite_global_exentas',
        'limite_global_exentas_uvt',
        'tarifa_gmf',
        'exencion_gmf_uvt',
        'ipc_anual',
        'justificacion_cambio',
        'creado_por',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'anio_vigencia' => 'integer',
            'version' => 'integer',
            'es_activa' => 'boolean',
            'fecha_acto' => 'date',
            'valor_uvt' => 'decimal:2',
            'valor_smmlv' => 'decimal:2',
            'tarifa_salud_indep' => 'decimal:2',
            'tarifa_pension_indep' => 'decimal:2',
            'tarifa_salud_emp' => 'decimal:2',
            'tarifa_pension_emp' => 'decimal:2',
            'porc_ibc_indep' => 'decimal:2',
            'porc_ibc_integral' => 'decimal:2',
            'ibc_min_smmlv' => 'decimal:2',
            'ibc_max_smmlv' => 'decimal:2',
            'tope_patrimonio_uvt' => 'integer',
            'tope_ingresos_uvt' => 'integer',
            'tope_tc_uvt' => 'integer',
            'tope_compras_uvt' => 'integer',
            'tope_consignaciones_uvt' => 'integer',
            'limite_renta_exenta_25_uvt' => 'integer',
            'porc_limite_global_exentas' => 'decimal:2',
            'limite_global_exentas_uvt' => 'integer',
            'tarifa_gmf' => 'decimal:4',
            'exencion_gmf_uvt' => 'integer',
            'ipc_anual' => 'decimal:2',
            'creado_en' => 'datetime',
        ];
    }

    public function tarifasEscalonadas(): HasMany
    {
        return $this->hasMany(TarifaEscalonada::class, 'parametro_normativo_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
