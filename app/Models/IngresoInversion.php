<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([DelUsuarioAutenticado::class])]
class IngresoInversion extends Model
{
    use HasUuids;

    protected $table = 'ingreso_inversion';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    public const TIPOS = [
        'RENDIMIENTO',
        'UTILIDAD_VENTA',
        'DIVIDENDO',
    ];

    protected $fillable = [
        'usuario_id',
        'inversion_id',
        'transaccion_id',
        'anio',
        'tipo',
        'valor_bruto',
        'retencion',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'valor_bruto' => 'decimal:2',
            'retencion' => 'decimal:2',
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

    public function transaccion(): BelongsTo
    {
        return $this->belongsTo(MovimientoInversion::class, 'transaccion_id');
    }
}
