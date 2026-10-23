<?php

use App\Domain\Biometria\VerificadorBiometrico;
use App\Domain\Biometria\VerificadorBiometricoSdk;
use App\Models\Cliente;
use App\Models\CredencialBiometrica;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('resuelve el adaptador DigitalPersona desde el contenedor', function () {
    expect(app(VerificadorBiometrico::class))
        ->toBeInstanceOf(VerificadorBiometricoSdk::class);
});

test('registra una plantilla biométrica para un cliente', function () {
    Http::fake([
        'http://127.0.0.1:8765/enroll' => Http::response([
            'template' => base64_encode('plantilla-digitalpersona'),
        ]),
    ]);

    $cliente = Cliente::create([
        'nombre' => 'Ana',
        'apellido' => 'García',
        'email' => 'ana@example.com',
    ]);
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->postJson("/api/v1/customers/{$cliente->id}/biometrics/enroll")
        ->assertCreated()
        ->assertJsonPath('id_cliente', $cliente->id);

    expect(CredencialBiometrica::query()
        ->where('id_cliente', $cliente->id)
        ->where('proveedor', 'digitalpersona')
        ->exists())->toBeTrue();

    $credencial = CredencialBiometrica::query()->where('id_cliente', $cliente->id)->firstOrFail();
    expect($credencial->getRawOriginal('id_externo'))->toHaveLength(36)
        ->and($credencial->getRawOriginal('plantilla'))->not->toBe(base64_encode('plantilla-digitalpersona'));
});

test('identifica un cliente con una muestra biométrica', function () {
    Http::fake([
        'http://127.0.0.1:8765/verify' => Http::response(['matched' => true]),
    ]);

    $cliente = Cliente::create([
        'nombre' => 'Luis',
        'apellido' => 'Pérez',
        'email' => 'luis@example.com',
    ]);

    $cliente->credencialesBiometricas()->create([
        'proveedor' => 'digitalpersona',
        'id_externo' => 'credencial-prueba',
        'plantilla' => base64_encode('plantilla-digitalpersona'),
        'estado' => true,
    ]);
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->postJson('/api/v1/biometrics/verify', [
            'capture_token' => base64_encode('muestra-digitalpersona'),
        ])->assertOk()
        ->assertJson([
            'matched' => true,
            'id_cliente' => $cliente->id,
        ]);
});

test('actualizar la huella desactiva la credencial anterior', function () {
    Http::fake([
        'http://127.0.0.1:8765/enroll' => Http::sequence()
            ->push(['template' => base64_encode('primera-huella')])
            ->push(['template' => base64_encode('segunda-huella')]),
    ]);

    $cliente = Cliente::create([
        'nombre' => 'Marta',
        'apellido' => 'López',
        'email' => 'marta@example.com',
    ]);
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->postJson("/api/v1/customers/{$cliente->id}/biometrics/enroll")
        ->assertCreated();
    $this->actingAs($usuario)
        ->postJson("/api/v1/customers/{$cliente->id}/biometrics/enroll")
        ->assertCreated();

    expect(CredencialBiometrica::query()->where('id_cliente', $cliente->id)->count())->toBe(2)
        ->and(CredencialBiometrica::query()->where('id_cliente', $cliente->id)->where('estado', true)->count())->toBe(1);
});

test('devuelve 503 claro cuando el puente biométrico está apagado', function () {
    Http::fake(fn () => throw new ConnectionException('refused'));

    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->postJson('/api/v1/biometrics/capture')
        ->assertStatus(503)
        ->assertJsonPath('message', fn (string $mensaje) => str_contains($mensaje, 'puente biométrico'));
});

test('devuelve 422 con el mensaje del lector cuando la captura falla', function () {
    Http::fake([
        'http://127.0.0.1:8765/capture' => Http::response(['error' => 'El lector está ocupado con otra captura.'], 422),
    ]);

    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->postJson('/api/v1/biometrics/capture')
        ->assertStatus(422)
        ->assertJsonPath('message', 'El lector está ocupado con otra captura.');
});
