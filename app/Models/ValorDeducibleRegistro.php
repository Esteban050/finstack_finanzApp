<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([DelUsuarioAutenticado::class])]
class ValorDeducibleRegistro extends Model
{
    use HasUuids;

    protected $table = 'valor_deducible_registro';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    public const CONCEPTOS = [
        'APORTE_FPV',
        'CESANTIAS_EXENTA',
        'UTILIDAD_NO_GRAVADA_BVC',
        'RETENCION_DIVIDENDOS',
        'AJUSTE_FPV_RETIRO',
    ];

    protected $fillable = [
        'usuario_id',
        'inversion_id',
        'transaccion_id',
        'anio',
        'concepto',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'valor' => 'decimal:2',
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
