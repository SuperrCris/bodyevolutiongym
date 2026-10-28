<?php

use App\Domain\Biometria\VerificadorBiometrico;
use App\Domain\Biometria\VerificadorBiometricoSdk;
use App\Models\Cliente;
use App\Models\CredencialBiometrica;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('resuelve el adaptador DigitalPersona desde el contenedor', function () {
    expect(app(VerificadorBiometrico::class))
        ->toBeInstanceOf(VerificadorBiometricoSdk::class);
});

test('devuelve el progreso real del enrolamiento de huella', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/enrolar/progreso' => Http::response([
            'muestras' => 2,
            'total' => 4,
            'en_curso' => true,
            'estado' => 'en_curso',
            'mensaje' => 'Captura 2 de 4 aceptada.',
        ]),
    ]);

    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.progreso@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->getJson("/api/v1/customers/{$cliente->id}/biometrics/enroll/progress")
        ->assertOk()
        ->assertJson([
            'muestras' => 2,
            'total' => 4,
            'en_curso' => true,
            'estado' => 'en_curso',
            'mensaje' => 'Captura 2 de 4 aceptada.',
        ]);
});

test('completa un enrolamiento asíncrono y guarda la credencial biométrica', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/enrolar/iniciar' => Http::response(['en_curso' => true]),
        'http://127.0.0.1:8765/enrolar/progreso' => Http::response([
            'muestras' => 4,
            'total' => 4,
            'en_curso' => false,
            'estado' => 'completado',
            'mensaje' => 'Las cuatro capturas fueron aceptadas.',
            'error' => '',
        ]),
        'http://127.0.0.1:8765/enrolar/resultado' => Http::response([
            'template' => base64_encode('plantilla-digitalpersona'),
        ]),
    ]);

    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.async@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->postJson("/api/v1/customers/{$cliente->id}/biometrics/enroll/start")
        ->assertAccepted()
        ->assertJson(['en_curso' => true]);
    $this->actingAs($usuario)
        ->getJson("/api/v1/customers/{$cliente->id}/biometrics/enroll/progress")
        ->assertOk()
        ->assertJsonPath('muestras', 4)
        ->assertJsonPath('estado', 'completado');
    $this->actingAs($usuario)
        ->postJson("/api/v1/customers/{$cliente->id}/biometrics/enroll/finish")
        ->assertCreated()
        ->assertJsonPath('id_cliente', $cliente->id);

    expect(CredencialBiometrica::query()->where('id_cliente', $cliente->id)->where('estado', true)->exists())
        ->toBeTrue();
});

test('registra una plantilla biométrica para un cliente', function () {
    Http::fake([
        'http://127.0.0.1:8765/enrolar' => Http::response([
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
        'http://127.0.0.1:8765/identificar' => coincidirConCandidatos(),
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

test('identifica al cliente correcto cuando la coincidencia está en un lote posterior', function () {
    config(['biometria.lote_identificacion' => 2]);
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/identificar' => coincidirConCandidatos(base64_encode('plantilla-3')),
    ]);

    foreach ([1, 2, 3] as $numero) {
        $cliente = Cliente::create(['nombre' => 'Cliente', 'apellido' => (string) $numero]);
        $cliente->credencialesBiometricas()->create([
            'proveedor' => 'digitalpersona',
            'id_externo' => 'credencial-lote-'.$numero,
            'plantilla' => base64_encode('plantilla-'.$numero),
            'estado' => true,
        ]);
    }
    $usuario = Usuario::create(['name' => 'Operador', 'email' => 'op.lote@example.com', 'password' => 'password']);

    $this->actingAs($usuario)
        ->postJson('/api/v1/biometrics/verify', ['capture_token' => base64_encode('muestra')])
        ->assertOk()
        ->assertJson(['matched' => true, 'id_cliente' => $cliente->id, 'id_externo' => 'credencial-lote-3']);

    Http::assertSentCount(2);
});

test('deja de consultar lotes al encontrar la primera coincidencia', function () {
    config(['biometria.lote_identificacion' => 2]);
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/identificar' => coincidirConCandidatos(base64_encode('plantilla-1')),
    ]);

    foreach ([1, 2, 3] as $numero) {
        Cliente::create(['nombre' => 'Cliente', 'apellido' => (string) $numero])
            ->credencialesBiometricas()
            ->create([
                'proveedor' => 'digitalpersona',
                'id_externo' => 'credencial-corte-'.$numero,
                'plantilla' => base64_encode('plantilla-'.$numero),
                'estado' => true,
            ]);
    }
    $usuario = Usuario::create(['name' => 'Operador', 'email' => 'op.corte@example.com', 'password' => 'password']);

    $this->actingAs($usuario)
        ->postJson('/api/v1/biometrics/verify', ['capture_token' => base64_encode('muestra')])
        ->assertOk()
        ->assertJson(['matched' => true, 'id_externo' => 'credencial-corte-1']);

    Http::assertSentCount(1);
});

test('no coincide y omite credenciales sin plantilla ni activas', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/identificar' => Http::response(['coincidencias' => [], 'evaluadas' => 1, 'invalidas' => 0]),
    ]);

    $cliente = Cliente::create(['nombre' => 'Luis', 'apellido' => 'Pérez']);
    $cliente->credencialesBiometricas()->createMany([
        ['proveedor' => 'digitalpersona', 'id_externo' => 'sin-plantilla', 'plantilla' => null, 'estado' => true],
        ['proveedor' => 'digitalpersona', 'id_externo' => 'inactiva', 'plantilla' => base64_encode('vieja'), 'estado' => false],
        ['proveedor' => 'digitalpersona', 'id_externo' => 'activa', 'plantilla' => base64_encode('vigente'), 'estado' => true],
    ]);
    $usuario = Usuario::create(['name' => 'Operador', 'email' => 'op.sin@example.com', 'password' => 'password']);

    $this->actingAs($usuario)
        ->postJson('/api/v1/biometrics/verify', ['capture_token' => base64_encode('muestra')])
        ->assertOk()
        ->assertExactJson(['matched' => false]);

    Http::assertSent(fn (Request $request): bool => count($request['candidatos']) === 1
        && $request['candidatos'][0]['template'] === base64_encode('vigente'));
});

test('actualizar la huella desactiva la credencial anterior', function () {
    Http::fake([
        'http://127.0.0.1:8765/enrolar' => Http::sequence()
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

test('el sondeo continuo identifica al cliente y devuelve su membresía', function () {
    Http::fake([
        'http://127.0.0.1:8765/poll*' => Http::response(['seq' => 4, 'sample' => base64_encode('muestra')]),
        'http://127.0.0.1:8765/identificar' => coincidirConCandidatos(),
    ]);

    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'Gómez', 'email' => 'ana.scan@example.com']);
    $cliente->credencialesBiometricas()->create([
        'proveedor' => 'digitalpersona',
        'id_externo' => 'credencial-scan',
        'plantilla' => base64_encode('plantilla'),
        'estado' => true,
    ]);
    $usuario = Usuario::create(['name' => 'Operador', 'email' => 'op.scan@example.com', 'password' => 'password']);

    $this->actingAs($usuario)
        ->getJson('/api/v1/biometrics/scan?after=3')
        ->assertOk()
        ->assertJsonPath('seq', 4)
        ->assertJsonPath('matched', true)
        ->assertJsonPath('cliente.nombre', 'Ana Gómez')
        ->assertJsonPath('cliente.vigente', false);
});

test('el sondeo continuo no notifica cuando no hay huella nueva', function () {
    Http::fake(['http://127.0.0.1:8765/poll*' => Http::response(['seq' => 4])]);
    $usuario = Usuario::create(['name' => 'Operador', 'email' => 'op.vacio@example.com', 'password' => 'password']);

    $this->actingAs($usuario)
        ->getJson('/api/v1/biometrics/scan?after=4')
        ->assertOk()
        ->assertJson(['seq' => 4, 'detected' => false]);
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
        'http://127.0.0.1:8765/capturar' => Http::response(['error' => 'El lector está ocupado con otra captura.'], 422),
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
