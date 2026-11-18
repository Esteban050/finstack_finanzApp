<?php

namespace App\Models;

use App\Casts\PgsqlArrayCast;
use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MOD-09 RN-059.3: preferencias del resumen por correo del Usuario. Una
 * fila por usuario (la PK es usuario_id).
 */
#[ScopedBy([DelUsuarioAutenticado::class])]
class PreferenciaNotificacion extends Model
{
    protected $table = 'preferencias_notificacion';
    protected $primaryKey = 'usuario_id';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    public const NINGUNA = 'NINGUNA';
    public const SEMANAL = 'SEMANAL';
    public const MENSUAL = 'MENSUAL';

    public const FRECUENCIAS = [self::NINGUNA, self::SEMANAL, self::MENSUAL];

    public const BLOQUES = ['INGRESOS_GASTOS', 'PRESUPUESTO', 'ALERTAS', 'VENCIMIENTOS'];

    protected $fillable = [
        'usuario_id',
        'frecuencia_resumen',
        'bloques_resumen',
        'ultimo_resumen',
    ];

    protected function casts(): array
    {
        return [
            'bloques_resumen' => PgsqlArrayCast::class,
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
