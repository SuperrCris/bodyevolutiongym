<?php

namespace Database\Seeders;

use App\Models\TipoMembresia;
use App\Models\Usuario;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Usuario::query()->updateOrCreate(['email' => 'admin@bodyevolutiongym.test'], [
            'name' => 'Cristian',
            'username' => 'cristian',
            'role' => Usuario::ROLE_ADMINISTRADOR,
            'password' => 'password',
        ]);

        TipoMembresia::query()->updateOrCreate(['nombre' => 'Plan mensual'], [
            'duracion_dias' => 30,
            'precio' => 499.00,
            'activo' => true,
        ]);
    }
}
