<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_cliente', 'proveedor', 'id_externo', 'plantilla', 'estado'])]
class CredencialBiometrica extends Model
{
    protected function casts(): array
    {
        return ['id_externo' => 'encrypted', 'plantilla' => 'encrypted', 'estado' => 'boolean'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
}
