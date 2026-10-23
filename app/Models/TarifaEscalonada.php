<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifaEscalonada extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tarifas_escalonadas';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'parametro_normativo_id',
        'tipo_tabla',
        'rango_desde',
        'rango_hasta',
        'tarifa_porc',
        'impuesto_base_uvt',
    ];

    protected function casts(): array
    {
        return [
            'rango_desde' => 'decimal:2',
            'rango_hasta' => 'decimal:2',
            'tarifa_porc' => 'decimal:3',
            'impuesto_base_uvt' => 'decimal:2',
        ];
    }

    public function parametroNormativo(): BelongsTo
    {
        return $this->belongsTo(ParametroNormativo::class, 'parametro_normativo_id');
    }
}
