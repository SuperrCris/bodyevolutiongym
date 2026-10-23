<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id_cliente', 'tipo_membresia_id', 'comienzo', 'finalizacion', 'estado'])]
class Membresia extends Model
{
    public const ESTADO_ACTIVA = 'activa';

    protected $table = 'membresias';

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
        return $this->estado === self::ESTADO_ACTIVA
            && $this->finalizacion?->isTodayOrFuture() === true;
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'membresia_id');
    }
}
