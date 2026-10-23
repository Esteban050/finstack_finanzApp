<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenSeguridad extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tokens_seguridad';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    const CREATED_AT = 'creado_en';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'token_hash',
        'intentos',
        'reenvios_contador',
        'expira_en',
        'usado_en',
        'ip_origen',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'intentos' => 'integer',
            'reenvios_contador' => 'integer',
            'expira_en' => 'datetime',
            'usado_en' => 'datetime',
            'creado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function haExpirado(): bool
    {
        return now()->greaterThan($this->expira_en);
    }

    public function yaFueUsado(): bool
    {
        return $this->usado_en !== null;
    }
}
