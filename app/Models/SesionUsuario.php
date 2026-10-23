<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesionUsuario extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sesiones_usuario';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    const CREATED_AT = 'creado_en';

    protected $fillable = [
        'usuario_id',
        'token_jti',
        'ip_origen',
        'user_agent',
        'recordar_dispositivo_until',
        'ultima_actividad',
        'expira_en',
        'revocada',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'recordar_dispositivo_until' => 'datetime',
            'ultima_actividad' => 'datetime',
            'expira_en' => 'datetime',
            'revocada' => 'boolean',
            'creado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
