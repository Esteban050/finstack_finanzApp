<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AceptacionPolitica extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'aceptaciones_politica';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    const CREATED_AT = 'fecha_aceptacion';

    protected $fillable = [
        'usuario_id',
        'version_politica',
        'fecha_aceptacion',
        'ip_origen',
        'medio',
        'revocada_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_aceptacion' => 'datetime',
            'revocada_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
