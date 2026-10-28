<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'username', 'role', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Usuario extends Authenticatable
{
    public const ROLE_ADMINISTRADOR = 'administrador';

    public const ROLE_RECEPCIONISTA = 'recepcionista';

    protected $attributes = [
        'role' => self::ROLE_RECEPCIONISTA,
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected static function booted(): void
    {
        static::creating(function (self $usuario): void {
            if (filled($usuario->username)) {
                return;
            }

            $base = Str::slug(Str::before(trim($usuario->name), ' '));
            $base = $base !== '' ? $base : 'usuario';
            $username = $base;
            $suffix = 2;

            while (static::query()->where('username', $username)->exists()) {
                $username = $base.$suffix;
                $suffix++;
            }

            $usuario->username = $username;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function esAdministrador(): bool
    {
        return $this->role === self::ROLE_ADMINISTRADOR;
    }

    public function egresos(): HasMany
    {
        return $this->hasMany(Egreso::class, 'usuario_id');
    }

    public function credencialesBiometricas(): HasMany
    {
        return $this->hasMany(CredencialBiometricaEmpleado::class, 'usuario_id');
    }
}
