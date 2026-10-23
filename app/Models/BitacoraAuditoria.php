<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraAuditoria extends Model
{
    use HasFactory;

    protected $table = 'bitacora_auditoria';
    public $timestamps = false;

    const CREATED_AT = 'fecha_evento';

    protected $fillable = [
        'usuario_id',
        'accion',
        'entidad_afectada',
        'entidad_id',
        'valor_anterior',
        'valor_nuevo',
        'justificacion',
        'ip_origen',
        'fecha_evento',
    ];

    protected function casts(): array
    {
        return [
            'valor_anterior' => 'array',
            'valor_nuevo' => 'array',
            'fecha_evento' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
