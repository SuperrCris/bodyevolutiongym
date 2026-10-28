<?php

use App\Models\Cliente;
use App\Models\CredencialBiometricaEmpleado;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('un empleado puede iniciar sesión con su huella y queda auditado', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/capturar' => Http::response(['sample' => base64_encode('muestra-empleado')]),
        'http://127.0.0.1:8765/identificar' => coincidirConCandidatos(),
    ]);

    $empleado = Usuario::create([
        'name' => 'Recepción Norte',
        'username' => 'recepcion.norte',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password-seguro',
    ]);
    $empleado->credencialesBiometricas()->create([
        'proveedor' => 'digitalpersona',
        'id_externo' => '2b3f2a40-2c2d-4ed8-9d57-1fe5f74f985d',
        'plantilla' => base64_encode('plantilla-empleado'),
        'estado' => true,
    ]);

    $this->post('/login/huella')->assertRedirect('/clientes');

    $this->assertAuthenticatedAs($empleado);
    $this->assertDatabaseHas('registros', [
        'usuario_id' => $empleado->id,
        'accion' => 'autenticacion.huella',
    ]);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8765/identificar'
        && $request->data()['candidatos'][0]['template'] === base64_encode('plantilla-empleado')
    );
});

test('no inicia sesión si la huella coincide con credenciales de empleados distintos', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/capturar' => Http::response(['sample' => base64_encode('muestra-ambigua')]),
        'http://127.0.0.1:8765/identificar' => coincidirConCandidatos(),
    ]);

    foreach (['ana', 'luis'] as $username) {
        $empleado = Usuario::create([
            'name' => ucfirst($username),
            'username' => $username,
            'role' => Usuario::ROLE_RECEPCIONISTA,
            'email' => null,
            'password' => 'password-seguro',
        ]);
        $empleado->credencialesBiometricas()->create([
            'proveedor' => 'digitalpersona',
            'id_externo' => $username === 'ana'
                ? '8bc6fef8-9d6c-4c18-a8f6-c0b6d521bb1a'
                : 'e6d3f0b4-7e64-4d34-a1a8-c687b1d0f0ca',
            'plantilla' => base64_encode('plantilla-'.$username),
            'estado' => true,
        ]);
    }

    $this->post('/login/huella')
        ->assertRedirectBackWithErrors('fingerprint');

    $this->assertGuest();
    Http::assertSentCount(2);
});

test('rechaza una huella que no coincide con ningún empleado', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/capturar' => Http::response(['sample' => base64_encode('muestra-desconocida')]),
        'http://127.0.0.1:8765/identificar' => Http::response(['coincidencias' => []]),
    ]);

    $empleado = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password-seguro',
    ]);
    $empleado->credencialesBiometricas()->create([
        'proveedor' => 'digitalpersona',
        'id_externo' => '34a0dd07-a794-4d9c-9e93-55f1d2c35351',
        'plantilla' => base64_encode('otra-plantilla'),
        'estado' => true,
    ]);

    $this->post('/login/huella')
        ->assertRedirectBackWithErrors('fingerprint');

    $this->assertGuest();
    $this->assertDatabaseMissing('registros', ['accion' => 'autenticacion.huella']);
});

test('una huella enrolada como cliente no autentica a un empleado', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/capturar' => Http::response(['sample' => base64_encode('muestra-cliente')]),
        'http://127.0.0.1:8765/identificar' => coincidirConCandidatos(),
    ]);

    $empleado = Usuario::create([
        'name' => 'Operador',
        'username' => 'operador',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password-seguro',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);
    $cliente->credencialesBiometricas()->create([
        'proveedor' => 'digitalpersona',
        'id_externo' => 'credencial-cliente-prueba',
        'plantilla' => base64_encode('plantilla-cliente'),
        'estado' => true,
    ]);

    $this->post('/login/huella')
        ->assertRedirectBackWithErrors('fingerprint');

    $this->assertGuest();
    Http::assertSentCount(1);
});

test('el administrador puede enrolar la huella de un empleado con cuatro capturas', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/enrolar/iniciar' => Http::response(['en_curso' => true]),
        'http://127.0.0.1:8765/enrolar/progreso' => Http::response([
            'muestras' => 4,
            'total' => 4,
            'en_curso' => false,
            'estado' => 'completado',
            'mensaje' => 'Las cuatro capturas fueron aceptadas.',
        ]),
        'http://127.0.0.1:8765/enrolar/resultado' => Http::response([
            'template' => base64_encode('plantilla-enrolada'),
        ]),
    ]);

    $administrador = Usuario::create([
        'name' => 'Cristian',
        'username' => 'cristian',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password-seguro',
    ]);
    $empleado = Usuario::create([
        'name' => 'Recepción Norte',
        'username' => 'recepcion.norte',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password-seguro',
    ]);
    $credencialAnterior = $empleado->credencialesBiometricas()->create([
        'proveedor' => 'digitalpersona',
        'id_externo' => '4b553003-189f-4294-bcc1-b09882488b9d',
        'plantilla' => base64_encode('plantilla-anterior'),
        'estado' => true,
    ]);

    $this->actingAs($administrador)
        ->postJson("/api/v1/empleados/{$empleado->id}/biometricos/enrolar/iniciar")
        ->assertAccepted();
    $this->getJson("/api/v1/empleados/{$empleado->id}/biometricos/enrolar/progreso")
        ->assertOk()
        ->assertJsonPath('muestras', 4)
        ->assertJsonPath('estado', 'completado');
    $this->postJson("/api/v1/empleados/{$empleado->id}/biometricos/enrolar/finalizar")
        ->assertCreated()
        ->assertJsonPath('id_usuario', $empleado->id);

    $credencial = CredencialBiometricaEmpleado::query()
        ->where('usuario_id', $empleado->id)
        ->where('estado', true)
        ->firstOrFail();
    expect($credencial->plantilla)->toBe(base64_encode('plantilla-enrolada'))
        ->and($credencial->getRawOriginal('plantilla'))->not->toBe(base64_encode('plantilla-enrolada'));
    expect($credencialAnterior->refresh()->estado)->toBeFalse()
        ->and(CredencialBiometricaEmpleado::query()->where('usuario_id', $empleado->id)->where('estado', true)->count())->toBe(1);
    $this->assertDatabaseHas('registros', [
        'usuario_id' => $administrador->id,
        'accion' => 'empleado.huella.enrolada',
    ]);
    $this->actingAs($administrador)
        ->get('/empleados')
        ->assertOk()
        ->assertSee('Huella registrada')
        ->assertSee('data-paso-huella', false);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8765/enrolar/iniciar'
        && $request->data()['cliente'] === 'empleado-'.$empleado->id
    );
});

test('una recepcionista no puede enrolar huellas de empleados', function () {
    Http::preventStrayRequests();
    Http::fake(['http://127.0.0.1:8765/enrolar/iniciar' => Http::response(['en_curso' => true])]);

    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password-seguro',
    ]);
    $empleado = Usuario::create([
        'name' => 'Empleado',
        'username' => 'empleado',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password-seguro',
    ]);

    $this->actingAs($recepcionista)
        ->postJson("/api/v1/empleados/{$empleado->id}/biometricos/enrolar/iniciar")
        ->assertForbidden();

    Http::assertSentCount(0);
});

test('muestra los errores del lector sin iniciar una sesión', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/capturar' => Http::response([
            'error' => 'El lector está ocupado con otra captura.',
        ], 422),
    ]);

    $this->post('/login/huella')
        ->assertRedirectBackWithErrors('fingerprint');

    $this->assertGuest();
});

test('muestra la alternativa de inicio de sesión con huella', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Entrar con huella')
        ->assertSee(route('login.fingerprint'));
});
