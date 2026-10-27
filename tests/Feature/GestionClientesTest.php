<?php

use App\Models\Cliente;
use App\Models\TipoMembresia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('un operador puede iniciar sesión y crear un cliente', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador@example.com',
        'password' => 'password',
    ]);

    $this->post('/login', [
        'email' => 'operador@example.com',
        'password' => 'password',
    ])->assertRedirect('/clientes');

    $this->post('/clientes', [
        'nombre' => 'Ana',
        'apellido' => 'García',
        'email' => 'ana@example.com',
        'telefono' => '555123456',
        'documento' => 'DOC-001',
    ])->assertRedirect('/clientes');

    expect($usuario->refresh()->exists)->toBeTrue();
    expect(Cliente::query()->where('documento', 'DOC-001')->exists())->toBeTrue();
});

test('un operador puede renovar la membresía de un cliente', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create([
        'nombre' => 'Luis',
        'apellido' => 'Pérez',
        'email' => 'luis@example.com',
    ]);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan de prueba',
        'duracion_dias' => 30,
        'precio' => 350,
        'activo' => true,
    ]);

    $this->actingAs($usuario)
        ->post("/clientes/{$cliente->id}/renovar", [
            'tipo_membresia_id' => $plan->id,
            'metodo_pago' => 'efectivo',
        ])
        ->assertRedirect('/clientes');

    expect($cliente->membresias()->where('estado', 'activa')->exists())->toBeTrue();
    expect($cliente->pagos()->where('monto', 350)->exists())->toBeTrue();
});

test('un operador puede guardar la foto de un cliente', function () {
    Storage::fake('public');
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.foto@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->post('/clientes', [
            'nombre' => 'Marta',
            'apellido' => 'Foto',
            'email' => 'marta.foto@example.com',
            'foto' => UploadedFile::fake()->image('cliente.jpg', 200, 200),
        ])
        ->assertRedirect('/clientes');

    $cliente = Cliente::query()->where('email', 'marta.foto@example.com')->firstOrFail();

    expect($cliente->foto)->not->toBeNull();
    Storage::disk('public')->assertExists($cliente->foto);
});
