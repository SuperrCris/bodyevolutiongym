<?php

use App\Models\Cliente;
use App\Models\Pago;
use App\Models\TipoMembresia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('un operador puede iniciar sesión y crear un cliente', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador@example.com',
        'password' => 'password',
    ]);

    $this->post('/login', [
        'username' => 'operador',
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

test('un operador puede guardar un cliente y continuar con el registro de su huella', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.huella@example.com',
        'password' => 'password',
    ]);

    $response = $this->actingAs($usuario)
        ->post('/clientes', [
            'nombre' => 'Ana',
            'apellido' => 'García',
            'registrar_huella_despues' => '1',
        ]);
    $cliente = Cliente::query()->where('nombre', 'Ana')->firstOrFail();

    $response->assertRedirect('/clientes')
        ->assertSessionHas('cliente_para_enrolar', $cliente->id);
    $this->assertModelExists($cliente);
});

test('muestra el modal de enrolamiento una sola vez para el cliente recién creado', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.modal.huella@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);

    $response = $this->actingAs($usuario)
        ->withSession(['cliente_para_enrolar' => $cliente->id])
        ->get('/clientes')
        ->assertOk();

    $response
        ->assertSee('Cliente: Ana García')
        ->assertSee('data-paso-enrolamiento="4"', false)
        ->assertSee('data-mensaje-enrolamiento', false);
    expect(substr_count($response->getContent(), '<div data-progreso-enrolamiento='))
        ->toBe(1);

    $this->get('/clientes')
        ->assertOk()
        ->assertDontSee('Cliente: Ana García');
});

test('ofrece el enrolamiento desde editar y mantiene una sola interfaz compartida', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.editar.huella@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);

    $response = $this->actingAs($usuario)->get('/clientes');

    $response->assertOk()
        ->assertSee('data-open-enrollment="'.$cliente->id.'"', false)
        ->assertSee('data-open-modal="renovar-'.$cliente->id.'"', false)
        ->assertDontSee('data-enrolar-huella="'.$cliente->id.'"', false);
    expect(substr_count($response->getContent(), '<div data-progreso-enrolamiento='))
        ->toBe(1);
});

test('un operador puede buscar un cliente por su ID', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.busqueda.id@example.com',
        'password' => 'password',
    ]);
    $clienteBuscado = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);
    $clienteNoCoincidente = Cliente::create(['nombre' => 'Luis', 'apellido' => 'Pérez']);

    $this->actingAs($usuario)
        ->get(route('clientes.index', ['buscar' => $clienteBuscado->id]))
        ->assertOk()
        ->assertSee('data-open-enrollment="'.$clienteBuscado->id.'"', false)
        ->assertDontSee('data-open-enrollment="'.$clienteNoCoincidente->id.'"', false);
});

test('un operador conserva la búsqueda textual de clientes', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.busqueda.nombre@example.com',
        'password' => 'password',
    ]);
    $clienteCoincidente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);
    $clienteNoCoincidente = Cliente::create(['nombre' => 'Luis', 'apellido' => 'Pérez']);

    $this->actingAs($usuario)
        ->get(route('clientes.index', ['buscar' => 'Ana']))
        ->assertOk()
        ->assertSee('data-open-enrollment="'.$clienteCoincidente->id.'"', false)
        ->assertDontSee('data-open-enrollment="'.$clienteNoCoincidente->id.'"', false);
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

test('un administrador puede cambiar la vigencia de una membresía y auditar los valores', function () {
    $administrador = Usuario::create([
        'name' => 'Cristian',
        'username' => 'admin.vigencia',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan de prueba',
        'duracion_dias' => 30,
        'precio' => 350,
        'activo' => true,
    ]);

    $this->actingAs($administrador)
        ->post(route('clientes.renovar', $cliente), [
            'tipo_membresia_id' => $plan->id,
            'metodo_pago' => 'efectivo',
        ])
        ->assertRedirect('/clientes');
    $membresia = $cliente->membresias()->firstOrFail();
    $fechaAnterior = $membresia->finalizacion->toDateString();
    $fechaNueva = $membresia->finalizacion->copy()->subDays(10)->toDateString();

    $this->actingAs($administrador)
        ->get('/clientes')
        ->assertOk()
        ->assertSee('Editar vigencia')
        ->assertSee('min="'.now()->toDateString().'"', false);

    $this->put(route('membresias.vigencia.update', $membresia), [
        'finalizacion' => $fechaNueva,
    ])
        ->assertRedirect('/clientes')
        ->assertSessionHas('success');

    expect($membresia->refresh()->finalizacion->toDateString())->toBe($fechaNueva);
    $this->assertDatabaseHas('registros', [
        'usuario_id' => $administrador->id,
        'accion' => 'membresia.vigencia.actualizada',
    ]);
    $metadatos = DB::table('registros')
        ->where('usuario_id', $administrador->id)
        ->where('accion', 'membresia.vigencia.actualizada')
        ->value('metadatos');
    expect(json_decode($metadatos, true, flags: JSON_THROW_ON_ERROR))->toBe([
        'finalizacion_anterior' => $fechaAnterior,
        'finalizacion_nueva' => $fechaNueva,
    ]);
    $this->actingAs($administrador)
        ->get('/registros?accion=membresia.vigencia.actualizada')
        ->assertOk()
        ->assertSee($fechaAnterior)
        ->assertSee($fechaNueva);

    $this->actingAs($administrador)
        ->put(route('membresias.vigencia.update', $membresia), [
            'finalizacion' => $fechaNueva,
        ])
        ->assertRedirect('/clientes')
        ->assertSessionHas('success', 'La membresía ya tenía esa fecha de vigencia; no se registró ningún cambio.');
    expect(DB::table('registros')->where('accion', 'membresia.vigencia.actualizada')->count())->toBe(1);
});

test('una recepcionista no puede cambiar la vigencia de una membresía', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion.vigencia',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan de prueba',
        'duracion_dias' => 30,
        'precio' => 350,
        'activo' => true,
    ]);

    $this->actingAs($recepcionista)
        ->post(route('clientes.renovar', $cliente), [
            'tipo_membresia_id' => $plan->id,
            'metodo_pago' => 'efectivo',
        ])
        ->assertRedirect('/clientes');
    $membresia = $cliente->membresias()->firstOrFail();
    $fechaAnterior = $membresia->finalizacion->toDateString();

    $this->actingAs($recepcionista)
        ->get('/clientes')
        ->assertOk()
        ->assertDontSee('Editar vigencia');

    $this->put(route('membresias.vigencia.update', $membresia), [
        'finalizacion' => now()->addYear()->toDateString(),
    ])->assertForbidden();

    expect($membresia->refresh()->finalizacion->toDateString())->toBe($fechaAnterior);
    $this->assertDatabaseMissing('registros', [
        'accion' => 'membresia.vigencia.actualizada',
    ]);
});

test('rechaza una vigencia anterior al día actual y no registra el cambio', function () {
    $administrador = Usuario::create([
        'name' => 'Cristian',
        'username' => 'admin.vigencia.invalida',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan de prueba',
        'duracion_dias' => 30,
        'precio' => 350,
        'activo' => true,
    ]);

    $this->actingAs($administrador)
        ->post(route('clientes.renovar', $cliente), [
            'tipo_membresia_id' => $plan->id,
            'metodo_pago' => 'efectivo',
        ])
        ->assertRedirect('/clientes');
    $membresia = $cliente->membresias()->firstOrFail();
    $fechaAnterior = $membresia->finalizacion->toDateString();
    $fechaAnteriorAlDiaActual = now()->subDay()->toDateString();

    $this->actingAs($administrador)
        ->put(route('membresias.vigencia.update', $membresia), [
            'finalizacion' => $fechaAnteriorAlDiaActual,
        ])
        ->assertSessionHasErrors([
            'finalizacion' => 'La vigencia no puede ser anterior al día actual.',
        ]);

    expect($membresia->refresh()->finalizacion->toDateString())->toBe($fechaAnterior);
    $this->assertDatabaseMissing('registros', [
        'accion' => 'membresia.vigencia.actualizada',
    ]);
});

test('un operador puede imprimir el recibo de una renovación solicitada', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/imprimir' => Http::response([
            'printed' => true,
            'printer' => 'EC-PM-80250LI',
            'copies' => 2,
        ]),
    ]);

    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.recibo@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create([
        'nombre' => "Luis <script>alert('x')</script>",
        'apellido' => 'Pérez',
        'email' => 'luis.recibo@example.com',
    ]);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan de prueba',
        'duracion_dias' => 30,
        'precio' => 350,
        'activo' => true,
    ]);

    $response = $this->actingAs($usuario)
        ->post("/clientes/{$cliente->id}/renovar", [
            'tipo_membresia_id' => $plan->id,
            'metodo_pago' => 'efectivo',
            'imprimir' => 'true',
        ]);
    $pago = Pago::query()->firstOrFail();

    $response->assertRedirect('/clientes')
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'EC-PM-80250LI'));
    $this->assertModelExists($pago);
    $this->assertDatabaseCount('pagos', 1);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://127.0.0.1:8765/imprimir');
    Http::assertSentCount(1);
    $request = Http::recorded()->first()[0];
    $body = $request->body();

    expect($body)->toStartWith("BEG-PRINT-V1\n")
        ->toContain(base64_encode(file_get_contents(public_path('images/logo.png'))))
        ->toContain('Folio: #'.$pago->id)
        ->toContain('Luis <script>alert(\'x\')</script> Pérez')
        ->toContain('Método: efectivo')
        ->toContain('TOTAL: $350.00');

    $this->actingAs($usuario)
        ->get(route('pagos.recibo', $pago))
        ->assertOk()
        ->assertSee('Recibo de pago')
        ->assertSee('Luis')
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee("<script>alert('x')</script>", false)
        ->assertSee('Plan de prueba')
        ->assertSee('$350.00')
        ->assertSee('Efectivo')
        ->assertSee('images/logo.png')
        ->assertSee(route('pagos.imprimir', $pago), false)
        ->assertDontSee('window.print()', false);
});

test('no confirma la impresión si el puente no confirma las dos copias en un solo trabajo', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/imprimir' => Http::response([
            'printed' => true,
            'printer' => 'EC-PM-80250LI',
        ]),
    ]);

    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.recibo.copias@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'López']);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan de prueba',
        'duracion_dias' => 30,
        'precio' => 350,
        'activo' => true,
    ]);

    $this->actingAs($usuario)->post("/clientes/{$cliente->id}/renovar", [
        'tipo_membresia_id' => $plan->id,
        'metodo_pago' => 'efectivo',
    ]);
    $pago = Pago::query()->firstOrFail();

    $this->actingAs($usuario)
        ->post(route('pagos.imprimir', $pago))
        ->assertRedirect(route('pagos.recibo', $pago))
        ->assertSessionHas('error', 'El puente local no confirmó la impresión de dos recibos en un solo trabajo.');

    $this->assertModelExists($pago);
    $this->assertDatabaseCount('pagos', 1);
    Http::assertSentCount(1);
});

test('mantiene el recibo disponible cuando falla la impresora predeterminada', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/imprimir' => Http::response([
            'error' => 'No se pudo imprimir en EC-PM-80250LI.',
        ], 503),
    ]);

    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.recibo.error@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'López']);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan de prueba',
        'duracion_dias' => 30,
        'precio' => 350,
        'activo' => true,
    ]);

    $response = $this->actingAs($usuario)
        ->post("/clientes/{$cliente->id}/renovar", [
            'tipo_membresia_id' => $plan->id,
            'metodo_pago' => 'efectivo',
            'imprimir' => 'true',
        ]);
    $pago = Pago::query()->firstOrFail();

    $response->assertRedirect(route('pagos.recibo', $pago))
        ->assertSessionHas('error', 'No se pudo imprimir en EC-PM-80250LI.');
    $this->assertModelExists($pago);
});

test('un operador puede reintentar la impresión silenciosa de un recibo existente', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/imprimir' => Http::response([
            'printed' => true,
            'printer' => 'EC-PM-80250LI',
            'copies' => 2,
        ]),
    ]);

    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.recibo.reintento@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'López']);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan de prueba',
        'duracion_dias' => 30,
        'precio' => 350,
        'activo' => true,
    ]);

    $this->actingAs($usuario)->post("/clientes/{$cliente->id}/renovar", [
        'tipo_membresia_id' => $plan->id,
        'metodo_pago' => 'efectivo',
    ]);
    $pago = Pago::query()->firstOrFail();

    $this->actingAs($usuario)
        ->post(route('pagos.imprimir', $pago))
        ->assertRedirect('/clientes')
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'EC-PM-80250LI'));

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://127.0.0.1:8765/imprimir'
        && str_contains($request->body(), 'Folio: #'.$pago->id)
    );
});

test('mantiene el recibo disponible si el puente local de impresión no responde', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/imprimir' => Http::failedConnection(),
    ]);

    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.recibo.puente@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'López']);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan de prueba',
        'duracion_dias' => 30,
        'precio' => 350,
        'activo' => true,
    ]);

    $response = $this->actingAs($usuario)
        ->post("/clientes/{$cliente->id}/renovar", [
            'tipo_membresia_id' => $plan->id,
            'metodo_pago' => 'efectivo',
            'imprimir' => 'true',
        ]);
    $pago = Pago::query()->firstOrFail();

    $response->assertRedirect(route('pagos.recibo', $pago))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'puente local de impresión'));
    $this->assertModelExists($pago);
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
