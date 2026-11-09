<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleFpv extends Model
{
    use HasUuids;

    protected $table = 'detalle_fpv';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    public const VINCULACIONES = ['DIRECTO', 'VIA_EMPLEADOR'];

    protected $fillable = [
        'inversion_id',
        'administradora',
        'tipo_vinculacion',
        'saldo_actual',
        'unidades',
        'valor_unidad',
    ];

    protected function casts(): array
    {
        return [
            'saldo_actual' => 'decimal:2',
            'unidades' => 'decimal:8',
            'valor_unidad' => 'decimal:8',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function inversion(): BelongsTo
    {
        return $this->belongsTo(Inversion::class, 'inversion_id');
    }
}
