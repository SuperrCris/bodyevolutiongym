<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_cliente', 'tipo_membresia_id', 'comienzo', 'finalizacion', 'estado'])]
class Membresia extends Model
{
    protected function casts(): array
    {
        return ['comienzo' => 'date', 'finalizacion' => 'date'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TipoMembresia::class, 'tipo_membresia_id');
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'active' && $this->finalizacion->isTodayOrFuture();
    }
}
