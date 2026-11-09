<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntidadFinanciera extends Model
{
    use HasUuids;

    protected $table = 'entidad_financiera';
    protected $keyType = 'string';
    public $incrementing = false;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    public const TIPOS = ['BANCO', 'FIDUCIARIA', 'COMISIONISTA', 'AFP', 'FONDO_CESANTIAS', 'OTRA'];

    protected $fillable = [
        'nombre',
        'tipo',
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function inversiones(): HasMany
    {
        return $this->hasMany(Inversion::class, 'entidad_id');
    }
}
