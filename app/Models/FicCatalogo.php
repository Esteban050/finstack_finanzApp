<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FicCatalogo extends Model
{
    use HasUuids;

    protected $table = 'fic_catalogo';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'nombre',
        'perfil',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function composicion(): HasMany
    {
        return $this->hasMany(FicCatalogoComposicion::class, 'fic_catalogo_id');
    }

    public function detallesFic(): HasMany
    {
        return $this->hasMany(DetalleFic::class, 'fic_catalogo_id');
    }

    /**
     * Calcula la tarifa ponderada del fondo = suma(porcentaje * tarifa_retencion / 100)
     */
    public function tarifaPonderada(): string
    {
        $ponderada = '0.00';
        foreach ($this->composicion as $item) {
            $contribucion = bcdiv(bcmul((string) $item->porcentaje, (string) $item->tarifa_retencion, 4), '100', 4);
            $ponderada = bcadd($ponderada, $contribucion, 4);
        }
        return $ponderada;
    }
}
