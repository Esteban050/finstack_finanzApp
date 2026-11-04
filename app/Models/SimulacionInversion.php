<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-042 RN-042.4: Simulación guardada con un nombre. Guarda los datos de cada
 * alternativa (RN-042.1), no los resultados: se recalculan en cada consulta.
 * No afecta el patrimonio ni los reportes.
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
        'nombre',
        'alternativas',
    ];

    protected function casts(): array
    {
        return [
            'alternativas' => 'array',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
