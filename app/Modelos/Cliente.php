<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'apellido', 'email', 'telefono', 'direccion', 'foto', 'estado'])]
#[SoftDeletable]
class Cliente extends Model
{
    use SoftDeletes;

    public function membresias(): HasMany
    {
        return $this->hasMany(Membresia::class);
    }

    public function credencialesBiometricas(): HasMany
    {
        return $this->hasMany(CredencialBiometrica::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }
}
