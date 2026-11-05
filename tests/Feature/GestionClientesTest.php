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

test('un operador puede crear un cliente sin correo ni documento y guardar su dirección', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.direccion@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->post('/clientes', [
            'nombre' => 'Lucía',
            'apellido' => 'Pérez',
            'direccion' => 'Av. Central 123',
        ])
        ->assertRedirect('/clientes');

    $this->assertDatabaseHas('clientes', [
        'nombre' => 'Lucía',
        'apellido' => 'Pérez',
        'email' => null,
        'documento' => null,
        'direccion' => 'Av. Central 123',
    ]);

    $response = $this->get('/clientes')->assertOk();

    expect(substr_count($response->getContent(), 'name="foto_documento"'))->toBe(2);
    expect(substr_count($response->getContent(), 'name="direccion"'))->toBe(2);
    expect(substr_count($response->getContent(), 'name="email"'))->toBe(0);
    expect(substr_count($response->getContent(), 'name="fecha_nacimiento"'))->toBe(0);

    $cliente = Cliente::query()->where('nombre', 'Lucía')->firstOrFail();

    $this->get(route('clientes.index', ['abrir_renovacion' => $cliente->id]))
        ->assertOk()
        ->assertDontSee('Documento del cliente');
});

test('editar un cliente sin correo ni fecha de nacimiento conserva los datos existentes', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.editar.datos@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create([
        'nombre' => 'Elena',
        'apellido' => 'Ríos',
        'email' => 'elena.rios@example.com',
        'fecha_nacimiento' => '1998-05-10',
    ]);

    $this->actingAs($usuario)
        ->put(route('clientes.update', $cliente), [
            'nombre' => 'Elena',
            'apellido' => 'Ríos',
            'telefono' => '555123456',
            'direccion' => 'Calle Norte 45',
        ])
        ->assertRedirect('/clientes');

    $this->assertDatabaseHas('clientes', [
        'id' => $cliente->id,
        'email' => 'elena.rios@example.com',
        'fecha_nacimiento' => '1998-05-10',
    ]);
});

test('el formulario de nuevo cliente muestra guardar y asignar huella sin mostrar progreso de enrolamiento', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.nuevo.huella@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->get('/clientes')
        ->assertOk()
        ->assertSee('Guardar cliente')
        ->assertSee('Guardar y asignar huella')
        ->assertDontSee('Enrolamiento de huella')
        ->assertDontSee('data-progreso-enrolamiento=""', false);
});

test('la API permite crear un cliente sin correo ni documento con dirección', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.api.direccion@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->postJson('/api/v1/clientes', [
            'nombre' => 'Mario',
            'apellido' => 'López',
            'direccion' => 'Calle Norte 45',
        ])
        ->assertStatus(201)
        ->assertJsonPath('email', null)
        ->assertJsonPath('documento', null)
        ->assertJsonPath('direccion', 'Calle Norte 45');

    $this->assertDatabaseHas('clientes', [
        'nombre' => 'Mario',
        'apellido' => 'López',
        'email' => null,
        'documento' => null,
        'direccion' => 'Calle Norte 45',
    ]);
});

test('un operador puede registrar clientes con correo y documento repetidos', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.duplicados@example.com',
        'password' => 'password',
    ]);
    Cliente::create([
        'nombre' => 'Ana',
        'apellido' => 'García',
        'email' => 'compartido@example.com',
        'documento' => 'DOC-REPETIDO',
    ]);

    $this->actingAs($usuario)
        ->post('/clientes', [
            'nombre' => 'Luis',
            'apellido' => 'Pérez',
            'email' => 'compartido@example.com',
            'documento' => 'DOC-REPETIDO',
        ])
        ->assertRedirect('/clientes');

    $this->assertDatabaseCount('clientes', 2);
});

test('un operador puede actualizar un cliente con correo y documento de otro cliente', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.editar.duplicados@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create([
        'nombre' => 'Ana',
        'apellido' => 'García',
        'email' => 'ana@example.com',
        'documento' => 'DOC-ANA',
    ]);
    Cliente::create([
        'nombre' => 'Luis',
        'apellido' => 'Pérez',
        'email' => 'compartido@example.com',
        'documento' => 'DOC-REPETIDO',
    ]);

    $this->actingAs($usuario)
        ->put(route('clientes.update', $cliente), [
            'nombre' => 'Ana',
            'apellido' => 'García',
            'email' => 'compartido@example.com',
            'documento' => 'DOC-REPETIDO',
        ])
        ->assertRedirect('/clientes');

    $this->assertDatabaseHas('clientes', [
        'id' => $cliente->id,
        'email' => 'compartido@example.com',
        'documento' => 'DOC-REPETIDO',
    ]);
});

test('la API permite registrar clientes con correo y documento repetidos', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.api.duplicados@example.com',
        'password' => 'password',
    ]);
    Cliente::create([
        'nombre' => 'Ana',
        'apellido' => 'García',
        'email' => 'compartido@example.com',
        'documento' => 'DOC-REPETIDO',
    ]);

    $this->actingAs($usuario)
        ->postJson('/api/v1/clientes', [
            'nombre' => 'Luis',
            'apellido' => 'Pérez',
            'email' => 'compartido@example.com',
            'documento' => 'DOC-REPETIDO',
        ])
        ->assertStatus(201)
        ->assertJsonPath('email', 'compartido@example.com')
        ->assertJsonPath('documento', 'DOC-REPETIDO');

    $this->assertDatabaseCount('clientes', 2);
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

test('muestra el enrolamiento dentro del modal de alta para el cliente recién creado', function () {
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
        ->assertSee('data-mensaje-enrolamiento', false)
        ->assertSee('id="crearCliente" data-modal class="modal-backdrop z-50 flex bg-slate-950/80"', false)
        ->assertSee('data-refresh-after-close', false)
        ->assertSee('data-enrolar-huella="'.$cliente->id.'"', false)
        ->assertSee('data-open-membership-modal="'.$cliente->id.'"', false)
        ->assertSee('Comprar membresía')
        ->assertSee('>Salir</button>', false)
        ->assertSee('id="renovar-'.$cliente->id.'" data-modal', false)
        ->assertDontSee('id="enrolarHuellaCompartido"', false);

    $this->get('/clientes')
        ->assertOk()
        ->assertDontSee('Cliente: Ana García');
});

test('muestra la captura de huella dentro del formulario de edición del cliente', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.editar.huella@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);

    $response = $this->actingAs($usuario)->get('/clientes');

    $response->assertOk()
        ->assertSee('data-progreso-enrolamiento="'.$cliente->id.'"', false)
        ->assertSee('data-mensaje-enrolamiento', false)
        ->assertSee('data-enrolar-huella="'.$cliente->id.'"', false)
        ->assertSee('data-open-modal="renovar-'.$cliente->id.'"', false)
        ->assertDontSee('id="enrolarHuellaCompartido"', false);
    expect(substr_count($response->getContent(), '<div data-progreso-enrolamiento='))
        ->toBe(1);
});

test('muestra la opción de actualizar la huella existente en la edición del cliente', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.actualizar.huella@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);
    $cliente->credencialesBiometricas()->create([
        'proveedor' => 'digitalpersona',
        'id_externo' => 'credencial-edicion',
        'plantilla' => 'plantilla-anterior',
        'estado' => true,
    ]);

    $this->actingAs($usuario)
        ->get('/clientes')
        ->assertOk()
        ->assertSee('data-label-inicial="Actualizar huella"', false)
        ->assertSee('Hay una huella registrada. Captura de nuevo para reemplazarla.');
});

test('muestra las vistas de tarjetas y lista sin quitar acciones de clientes', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.vistas@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create([
        'nombre' => 'Ana',
        'apellido' => 'García',
    ]);

    $this->actingAs($usuario)
        ->get(route('clientes.index'))
        ->assertOk()
        ->assertSee('data-clientes-lista data-view-mode="tarjetas"', false)
        ->assertSee('data-client-view="tarjetas"', false)
        ->assertSee('data-client-view="lista"', false)
        ->assertSee('aria-label="Modo de visualización de clientes"', false)
        ->assertSee('data-cliente-card', false)
        ->assertSee('data-open-modal="editar-'.$cliente->id.'"', false)
        ->assertSee('data-open-modal="renovar-'.$cliente->id.'"', false);
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
        ->assertSee('data-enrolar-huella="'.$clienteBuscado->id.'"', false)
        ->assertDontSee('data-enrolar-huella="'.$clienteNoCoincidente->id.'"', false);
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
        ->assertSee('data-enrolar-huella="'.$clienteCoincidente->id.'"', false)
        ->assertDontSee('data-enrolar-huella="'.$clienteNoCoincidente->id.'"', false)
        ->assertSee('Limpiar búsqueda')
        ->assertSee('href="'.route('clientes.index').'"', false);
});

test('un operador puede abrir renovación sin filtrar la lista de clientes', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.renovacion.directa@example.com',
        'password' => 'password',
    ]);
    $clienteParaRenovar = Cliente::create([
        'nombre' => 'Cliente',
        'apellido' => 'Para renovar',
    ]);
    $clienteParaRenovar->created_at = now()->subMinutes(13);
    $clienteParaRenovar->save();

    for ($indice = 1; $indice <= 12; $indice++) {
        $clienteVisible = Cliente::create([
            'nombre' => 'Cliente visible',
            'apellido' => (string) $indice,
        ]);
        $clienteVisible->created_at = now()->addSeconds($indice);
        $clienteVisible->save();
    }

    $response = $this->actingAs($usuario)
        ->get(route('clientes.index', ['abrir_renovacion' => $clienteParaRenovar->id]));

    $response->assertOk()
        ->assertSee('id="renovar-'.$clienteParaRenovar->id.'"', false)
        ->assertSee('Cliente visible')
        ->assertDontSee('#'.$clienteParaRenovar->id.' - Cliente Para renovar')
        ->assertDontSee('name="buscar" value="'.$clienteParaRenovar->id.'"', false);
});

test('un operador puede renovar una membresía sin imprimir el ticket', function () {
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
            'imprimir' => 'no',
        ])
        ->assertRedirect('/clientes')
        ->assertSessionHas('success', 'Membresía renovada correctamente.');

    expect($cliente->membresias()->where('estado', 'activa')->exists())->toBeTrue();
    expect($cliente->pagos()->where('monto', 350)->exists())->toBeTrue();
    Http::assertSentCount(0);
});

test('muestra las tres opciones de impresión al renovar una membresía', function () {
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.opciones.impresion@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'García']);

    $this->actingAs($usuario)
        ->get('/clientes')
        ->assertOk()
        ->assertSee('Impresión del recibo')
        ->assertSee('name="imprimir" value="dos" checked', false)
        ->assertSee('name="imprimir" value="uno"', false)
        ->assertSee('name="imprimir" value="no"', false)
        ->assertSee('Imprimir tickets')
        ->assertSee('Imprimir un solo ticket')
        ->assertSee('No imprimir ticket');
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

test('un operador puede imprimir dos tickets para una renovación solicitada', function () {
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
            'imprimir' => 'dos',
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

    expect($body)->toStartWith("BEG-PRINT-V2\n2\n")
        ->toContain(base64_encode(file_get_contents(public_path('images/logo.png'))))
        ->toContain('Folio: #'.$pago->id)
        ->toContain('Cliente: #'.$cliente->id." •  Luis <script>alert('x')</script> Pérez")
        ->toContain('Membresia plan de prueba')
        ->toContain('TOTAL: $350.00')
        ->toContain('efectivo')
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

test('un operador puede imprimir un solo ticket para una renovación solicitada', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/imprimir' => Http::response([
            'printed' => true,
            'printer' => 'EC-PM-80250LI',
            'copies' => 1,
        ]),
    ]);

    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.recibo.uno@example.com',
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
            'imprimir' => 'uno',
        ]);
    $pago = Pago::query()->firstOrFail();

    $response->assertRedirect('/clientes')
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Un ticket enviado'));
    $this->assertModelExists($pago);
    $this->assertDatabaseCount('pagos', 1);
    Http::assertSentCount(1);
    expect(Http::recorded()->first()[0]->body())->toStartWith("BEG-PRINT-V2\n1\n");
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
            'foto' => UploadedFile::fake()->createWithContent(
                'cliente.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC'),
            ),
        ])
        ->assertRedirect('/clientes');

    $cliente = Cliente::query()->where('email', 'marta.foto@example.com')->firstOrFail();

    expect($cliente->foto)->not->toBeNull();
    Storage::disk('public')->assertExists($cliente->foto);
});

test('un operador puede guardar y ver la imagen del documento al renovar', function () {
    Storage::fake('local');
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.documento@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->post('/clientes', [
            'nombre' => 'Marta',
            'apellido' => 'Documento',
            'foto_documento' => UploadedFile::fake()->image('documento.jpg'),
        ])
        ->assertRedirect('/clientes');

    $cliente = Cliente::query()->where('apellido', 'Documento')->firstOrFail();

    expect($cliente->foto_documento)->toStartWith('documentos/clientes/');
    Storage::disk('local')->assertExists($cliente->foto_documento);

    $this->get(route('clientes.documento', $cliente))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->get(route('clientes.index', ['abrir_renovacion' => $cliente->id]))
        ->assertOk()
        ->assertSee('Documento del cliente')
        ->assertSee(route('clientes.documento', $cliente), false);
});

test('un operador puede agregar la imagen del documento al editar un cliente', function () {
    Storage::fake('local');
    $usuario = Usuario::create([
        'name' => 'Operador',
        'email' => 'operador.editar.documento@example.com',
        'password' => 'password',
    ]);
    $cliente = Cliente::create([
        'nombre' => 'Marta',
        'apellido' => 'Actualización',
    ]);

    $this->actingAs($usuario)
        ->put(route('clientes.update', $cliente), [
            'nombre' => 'Marta',
            'apellido' => 'Actualización',
            'foto_documento' => UploadedFile::fake()->image('documento.png'),
        ])
        ->assertRedirect('/clientes');

    $cliente->refresh();

    expect($cliente->foto_documento)->not->toBeNull();
    Storage::disk('local')->assertExists($cliente->foto_documento);
});

test('la imagen del documento no es accesible sin iniciar sesión', function () {
    $cliente = Cliente::create([
        'nombre' => 'Marta',
        'apellido' => 'Privado',
        'foto_documento' => 'documentos/clientes/documento.jpg',
    ]);

    $this->get(route('clientes.documento', $cliente))
        ->assertRedirect(route('login'));
});
