<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_cliente', 'proveedor', 'id_externo', 'plantilla', 'estado'])]
class CredencialBiometrica extends Model
{
    protected $table = 'credencial_biometricas';

    protected $hidden = ['id_externo', 'plantilla'];

    protected function casts(): array
    {
        return ['plantilla' => 'encrypted', 'estado' => 'boolean'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
}
