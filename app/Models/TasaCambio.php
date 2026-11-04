<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RF-017: TRM diaria (COP por 1 USD), obtenida de la fuente oficial o
 * registrada por el Administrador.
 *
 * Es un dato global, no del Usuario: no lleva el scope DelUsuarioAutenticado.
 */
class TasaCambio extends Model
{
    use HasUuids;

    protected $table = 'tasas_cambio';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    /** RN-017.1: datos abiertos de la Superintendencia Financiera. */
    public const FUENTE_OFICIAL = 'DATOS_ABIERTOS';

    /** RN-017.3: registrada o corregida por el Administrador. */
    public const FUENTE_MANUAL = 'MANUAL';

    protected $fillable = [
        'moneda',
        'valor',
        'vigencia_desde',
        'vigencia_hasta',
        'fuente',
        'justificacion',
        'registrado_por',
        'consultado_en',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:4',
            'vigencia_desde' => 'date:Y-m-d',
            'vigencia_hasta' => 'date:Y-m-d',
            'consultado_en' => 'datetime',
        ];
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }

    public function esManual(): bool
    {
        return $this->fuente === self::FUENTE_MANUAL;
    }
}
