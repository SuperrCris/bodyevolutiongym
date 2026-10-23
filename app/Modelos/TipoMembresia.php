<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Modelo de membresia

#[Fillable(['nombre', 'duracion_dias', 'precio', 'activo'])]
class TipoMembresia extends Model
{
    protected $table = 'tipo_membresias';

    protected $casts = [
        'precio' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function membresias(): HasMany
    {
        return $this->hasMany(Membresia::class, 'tipo_membresia_id');
    }
}
