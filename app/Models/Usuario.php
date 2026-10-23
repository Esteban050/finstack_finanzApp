<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids;

    protected $table = 'usuarios';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'nombres',
        'apellidos',
        'tipo_documento',
        'numero_documento',
        'correo',
        'password_hash',
        'rol',
        'estado',
        'intentos_fallidos',
        'bloqueado_hasta',
        'mfa_activo',
        'fecha_solicitud_supresion',
        'ultima_exportacion_en',
        'ultimo_acceso',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'bloqueado_hasta' => 'datetime',
            'mfa_activo' => 'boolean',
            'fecha_solicitud_supresion' => 'datetime',
            'ultima_exportacion_en' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'intentos_fallidos' => 'integer',
        ];
    }

    /**
     * Override default Laravel password column name
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getNameAttribute(): string
    {
        return trim($this->nombres . ' ' . $this->apellidos);
    }

    public function getEmailAttribute(): string
    {
        return $this->correo;
    }

    /**
     * Get email attribute name for notifications
     */
    public function getEmailForVerification(): string
    {
        return $this->correo;
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return $this->correo;
    }

    // Role helpers
    public function esAdmin(): bool
    {
        return $this->rol === 'ADMIN';
    }

    public function esUsuario(): bool
    {
        return $this->rol === 'USUARIO';
    }

    // State helpers
    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVA';
    }

    public function estaBloqueada(): bool
    {
        if ($this->estado === 'BLOQUEADA') {
            if ($this->bloqueado_hasta && now()->greaterThan($this->bloqueado_hasta)) {
                // El tiempo de bloqueo ya expiró
                return false;
            }
            return true;
        }
        return false;
    }

    // Relations
    public function historialContrasenas(): HasMany
    {
        return $this->hasMany(HistorialContrasena::class, 'usuario_id');
    }

    public function tokensSeguridad(): HasMany
    {
        return $this->hasMany(TokenSeguridad::class, 'usuario_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionUsuario::class, 'usuario_id');
    }

    public function perfilesTributarios(): HasMany
    {
        return $this->hasMany(PerfilTributario::class, 'usuario_id');
    }

    public function perfilTributarioActivo(): HasOne
    {
        return $this->hasOne(PerfilTributario::class, 'usuario_id')->whereNull('vigente_hasta');
    }

    public function aceptacionesPolitica(): HasMany
    {
        return $this->hasMany(AceptacionPolitica::class, 'usuario_id');
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(BitacoraAuditoria::class, 'usuario_id');
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class, 'usuario_id');
    }
}
