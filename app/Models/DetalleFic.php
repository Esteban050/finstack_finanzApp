<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleFic extends Model
{
    use HasUuids;

    protected $table = 'detalle_fic';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'inversion_id',
        'fic_catalogo_id',
        'unidades',
        'valor_unidad_compra',
        'valor_unidad_actual',
        'pacto_permanencia_dias',
        'comision_administracion_ea',
    ];

    protected function casts(): array
    {
        return [
            'unidades' => 'decimal:8',
            'valor_unidad_compra' => 'decimal:8',
            'valor_unidad_actual' => 'decimal:8',
            'pacto_permanencia_dias' => 'integer',
            'comision_administracion_ea' => 'decimal:2',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function inversion(): BelongsTo
    {
        return $this->belongsTo(Inversion::class, 'inversion_id');
    }

    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(FicCatalogo::class, 'fic_catalogo_id');
    }
}
