<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'apellido', 'email', 'telefono', 'documento', 'fecha_nacimiento', 'direccion', 'foto', 'foto_documento', 'estado'])]
#[SoftDeletable]
class Cliente extends Model
{
    protected $table = 'clientes';

    use SoftDeletes;

    public function membresias(): HasMany
    {
        return $this->hasMany(Membresia::class, 'id_cliente');
    }

    public function credencialesBiometricas(): HasMany
    {
        return $this->hasMany(CredencialBiometrica::class, 'id_cliente');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'id_cliente');
    }
}
