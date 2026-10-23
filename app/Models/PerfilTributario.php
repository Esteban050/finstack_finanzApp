<?php

namespace App\Models;

use App\Casts\PgsqlArrayCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfilTributario extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'perfiles_tributarios';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    const CREATED_AT = 'creado_en';

    protected $fillable = [
        'usuario_id',
        'fecha_nacimiento',
        'ciudad_residencia',
        'telefono',
        'ultimos_digitos_nit',
        'personas_a_cargo',
        'ocupaciones',
        'es_salario_integral',
        'clase_riesgo_arl',
        'arl_obligatoria',
        'responsable_iva',
        'vigente_desde',
        'vigente_hasta',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date:Y-m-d',
            'personas_a_cargo' => 'integer',
            'ocupaciones' => PgsqlArrayCast::class,
            'es_salario_integral' => 'boolean',
            'clase_riesgo_arl' => 'integer',
            'arl_obligatoria' => 'boolean',
            'responsable_iva' => 'boolean',
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
            'creado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
