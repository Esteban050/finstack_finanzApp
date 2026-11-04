<?php

namespace App\Models;

use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RN-035.1: Soporte del pago PILA, con las reglas de RF-030. El archivo se
 * guarda cifrado (RN-030.3) y su ruta no sale en las respuestas.
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class SoportePagoSeguridadSocial extends Model
{
    use HasUuids;

    protected $table = 'soportes_pago_seguridad_social';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'usuario_id',
        'pago_id',
        'nombre_original',
        'tipo_mime',
        'tamano_bytes',
        'ruta',
    ];

    protected $hidden = ['ruta'];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PagoSeguridadSocial::class, 'pago_id');
    }
}
