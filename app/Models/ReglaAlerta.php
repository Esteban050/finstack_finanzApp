<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * MOD-09 RF-057 / RF-059: regla de alerta que el Usuario ajustó. Los tipos
 * sin fila usan los valores por defecto de config/scfp.php (ReglaAlertaService).
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class ReglaAlerta extends Model
{
    use HasUuids;

    protected $table = 'reglas_alerta';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'activa',
        'enviar_correo',
        'parametros',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'enviar_correo' => 'boolean',
            'parametros' => 'array',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }
}
