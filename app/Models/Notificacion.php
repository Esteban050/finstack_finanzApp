<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MOD-09 RF-058: notificación del Usuario. `datos` lleva las cifras del
 * evento (RN-057.5); el texto lo arma el frontend con el tipo.
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class Notificacion extends Model
{
    use HasUuids;

    protected $table = 'notificaciones';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** RN-058.3: estados. */
    public const NO_LEIDA = 'NO_LEIDA';
    public const LEIDA = 'LEIDA';
    public const ARCHIVADA = 'ARCHIVADA';

    /** RN-058.3: prioridades. */
    public const INFORMATIVA = 'INFORMATIVA';
    public const ADVERTENCIA = 'ADVERTENCIA';
    public const CRITICA = 'CRITICA';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'prioridad',
        'estado',
        'clave',
        'datos',
        'referencia_tipo',
        'referencia_id',
        'leida_en',
        'archivada_en',
        'correo_enviado_en',
    ];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'leida_en' => 'datetime',
            'archivada_en' => 'datetime',
            'correo_enviado_en' => 'datetime',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
