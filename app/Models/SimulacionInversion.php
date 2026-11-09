<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-042 / RF-SIM-07 / RF-PAR-05: Simulación de inversión guardada.
 * Soporta comparación de alternativas y snapshots específicos por producto.
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class SimulacionInversion extends Model
{
    use HasUuids;

    protected $table = 'simulaciones_inversion';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'usuario_id',
        'inversion_id',
        'nombre',
        'fecha_simulacion',
        'alternativas',
        'parametros_entrada',
        'version_parametros',
        'bruto',
        'retencion_total',
        'gmf_total',
        'comisiones',
        'neto',
        'rentabilidad_neta_ea',
        'detalle_lineas',
        'impuesto_renta_estimado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_simulacion' => 'date:Y-m-d',
            'alternativas' => 'array',
            'parametros_entrada' => 'array',
            'detalle_lineas' => 'array',
            'bruto' => 'decimal:2',
            'retencion_total' => 'decimal:2',
            'gmf_total' => 'decimal:2',
            'comisiones' => 'decimal:2',
            'neto' => 'decimal:2',
            'rentabilidad_neta_ea' => 'decimal:4',
            'impuesto_renta_estimado' => 'decimal:2',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function inversion(): BelongsTo
    {
        return $this->belongsTo(Inversion::class, 'inversion_id');
    }
}
