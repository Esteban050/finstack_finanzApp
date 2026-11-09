<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FicCatalogoComposicion extends Model
{
    use HasUuids;

    protected $table = 'fic_catalogo_composicion';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'fic_catalogo_id',
        'concepto',
        'porcentaje',
        'tarifa_retencion',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
            'tarifa_retencion' => 'decimal:2',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function fic(): BelongsTo
    {
        return $this->belongsTo(FicCatalogo::class, 'fic_catalogo_id');
    }
}
