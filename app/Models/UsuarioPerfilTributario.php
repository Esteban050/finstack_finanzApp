<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([DelUsuarioAutenticado::class])]
class UsuarioPerfilTributario extends Model
{
    use HasUuids;

    protected $table = 'usuario_perfil_tributario';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'usuario_id',
        'anio_gravable',
        'es_declarante',
        'es_residente',
        'obligado_contabilidad',
        'ingreso_anual_estimado',
        'ingreso_laboral_promedio_6m',
        'tarifa_marginal_estimada',
    ];

    protected function casts(): array
    {
        return [
            'anio_gravable' => 'integer',
            'es_declarante' => 'boolean',
            'es_residente' => 'boolean',
            'obligado_contabilidad' => 'boolean',
            'ingreso_anual_estimado' => 'decimal:2',
            'ingreso_laboral_promedio_6m' => 'decimal:2',
            'tarifa_marginal_estimada' => 'decimal:2',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
