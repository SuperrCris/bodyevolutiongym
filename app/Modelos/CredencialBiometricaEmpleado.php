<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['usuario_id', 'proveedor', 'id_externo', 'plantilla', 'estado'])]
class CredencialBiometricaEmpleado extends Model
{
    protected $table = 'credenciales_biometricas_usuarios';

    protected $hidden = ['id_externo', 'plantilla'];

    protected function casts(): array
    {
        return [
            'plantilla' => 'encrypted',
            'estado' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
