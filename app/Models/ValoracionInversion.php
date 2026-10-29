<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-039: Valor de mercado de una inversión en una fecha. La más reciente es
 * el "valor actual" (RN-039.3). Hay una por fecha.
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class ValoracionInversion extends Model
{
    use HasUuids;

    protected $table = 'valoraciones_inversion';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /**
     * INICIAL: el monto inicial al registrar la inversión (decisión 7, SC-001).
     * MANUAL: la registra el Usuario (RN-039.1). CAUSADO: el valor causado
     * aceptado (RN-039.2). LIQUIDACION: el 0 de la liquidación total (RN-038.3).
     */
    public const ORIGENES = ['INICIAL', 'MANUAL', 'CAUSADO', 'LIQUIDACION'];

    protected $fillable = [
        'usuario_id',
        'inversion_id',
        'fecha',
        'valor',
        'precio_unitario',
        'unidades',
        'origen',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'valor' => 'decimal:2',
            'precio_unitario' => 'decimal:8',
            'unidades' => 'decimal:8',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function inversion(): BelongsTo
    {
        return $this->belongsTo(Inversion::class, 'inversion_id');
    }
}
