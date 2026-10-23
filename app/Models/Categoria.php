<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'categorias';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    const CREATED_AT = 'creado_en';

    protected $fillable = [
        'usuario_id',
        'categoria_padre_id',
        'tipo',
        'nombre',
        'naturaleza_sugerida',
        'necesidad_sugerida',
        'icono',
        'color_hex',
        'es_predeterminada',
        'activa',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'es_predeterminada' => 'boolean',
            'activa' => 'boolean',
            'creado_en' => 'datetime',
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_padre_id');
    }

    public function subcategorias(): HasMany
    {
        return $this->hasMany(Categoria::class, 'categoria_padre_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
