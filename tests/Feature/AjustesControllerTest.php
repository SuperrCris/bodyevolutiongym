<?php

use App\Models\Cliente;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\TipoMembresia;
use App\Models\Usuario;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

test('una cuenta administradora puede consultar los valores predeterminados del ticket', function () {
    $administrador = Usuario::create([
        'name' => 'Administradora',
        'username' => 'administradora',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($administrador)
        ->get(route('ajustes.index'))
        ->assertOk()
        ->assertSee('BODY EVOLUTION GYM')
        ->assertSee('6421095410')
        ->assertSee('¡Gracias por tu preferencia!')
        ->assertSee('Guardar ajustes');
});

test('una recepcionista puede consultar ajustes pero no cambiar el ticket', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($recepcionista)
        ->get(route('ajustes.index'))
        ->assertOk()
        ->assertSee('Duración de las notificaciones')
        ->assertSee('name="duracion_notificacion_segundos"', false)
        ->assertDontSee('name="encabezado"', false);

    $this->put(route('ajustes.ticket.update'), [
        'encabezado' => 'Encabezado no autorizado',
        'pie' => 'Pie no autorizado',
    ])->assertForbidden();

    $this->assertDatabaseCount('ajustes_sistema', 0);
});

test('cada rol puede guardar su tiempo personal para las notificaciones', function (string $role, string $username) {
    $usuario = Usuario::create([
        'name' => 'Usuario de prueba',
        'username' => $username,
        'role' => $role,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($usuario)
        ->get(route('ajustes.index'))
        ->assertOk()
        ->assertSee('value="20"', false);

    $this->put(route('ajustes.notificaciones.update'), [
        'duracion_notificacion_segundos' => '45',
    ])
        ->assertRedirect(route('ajustes.index'))
        ->assertSessionHas('success', 'Se actualizó el tiempo de las notificaciones.');

    $this->assertDatabaseHas('users', [
        'id' => $usuario->id,
        'duracion_notificacion_segundos' => 45,
    ]);

    $this->get(route('clientes.index'))
        ->assertOk()
        ->assertSee('data-duracion-segundos="45"', false);
})->with([
    'administrador' => [Usuario::ROLE_ADMINISTRADOR, 'admin-tiempo'],
    'recepcionista' => [Usuario::ROLE_RECEPCIONISTA, 'recepcion-tiempo'],
]);

test('rechaza tiempos de notificación fuera del rango permitido', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion-tiempo-invalido',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($recepcionista)
        ->put(route('ajustes.notificaciones.update'), [
            'duracion_notificacion_segundos' => 121,
        ])
        ->assertSessionHasErrors([
            'duracion_notificacion_segundos' => 'El tiempo debe estar entre 5 y 120 segundos.',
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $recepcionista->id,
        'duracion_notificacion_segundos' => 20,
    ]);
});

test('una cuenta administradora puede guardar y auditar el encabezado y pie del ticket', function () {
    $administrador = Usuario::create([
        'name' => 'Administradora',
        'username' => 'administradora',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($administrador)
        ->put(route('ajustes.ticket.update'), [
            'encabezado' => "Gimnasio Norte\r\nRFC: ABC123",
            'pie' => 'Síguenos en redes',
            'role' => Usuario::ROLE_RECEPCIONISTA,
        ])
        ->assertRedirect(route('ajustes.index'))
        ->assertSessionHas('success', 'Se actualizó el encabezado y pie del ticket.');

    $this->assertDatabaseHas('ajustes_sistema', [
        'clave' => 'ticket_encabezado',
        'valor' => "Gimnasio Norte\nRFC: ABC123",
    ]);
    $this->assertDatabaseHas('ajustes_sistema', [
        'clave' => 'ticket_pie',
        'valor' => 'Síguenos en redes',
    ]);
    $this->assertDatabaseHas('registros', [
        'usuario_id' => $administrador->id,
        'accion' => 'ajustes.ticket.actualizados',
    ]);

    $this->get(route('ajustes.index'))
        ->assertOk()
        ->assertSee('Gimnasio Norte')
        ->assertSee('RFC: ABC123')
        ->assertSee('Síguenos en redes');
});

test('rechaza caracteres de control en el texto del ticket sin guardar cambios', function () {
    $administrador = Usuario::create([
        'name' => 'Administradora',
        'username' => 'administradora',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($administrador)
        ->put(route('ajustes.ticket.update'), [
            'encabezado' => "Encabezado\x01 inválido",
            'pie' => 'Pie válido',
        ])
        ->assertSessionHasErrors([
            'encabezado' => 'El encabezado contiene caracteres no permitidos.',
        ]);

    $this->assertDatabaseCount('ajustes_sistema', 0);
    $this->assertDatabaseMissing('registros', ['accion' => 'ajustes.ticket.actualizados']);
});

test('rechaza textos del ticket que exceden el límite y no guarda cambios', function () {
    $administrador = Usuario::create([
        'name' => 'Administradora',
        'username' => 'administradora',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($administrador)
        ->put(route('ajustes.ticket.update'), [
            'encabezado' => str_repeat('x', 501),
            'pie' => 'Pie válido',
        ])
        ->assertSessionHasErrors([
            'encabezado' => 'El encabezado no puede superar los 500 caracteres.',
        ]);

    $this->assertDatabaseCount('ajustes_sistema', 0);
    $this->assertDatabaseMissing('registros', ['accion' => 'ajustes.ticket.actualizados']);
});

test('usa los ajustes guardados en el recibo y la impresión y escapa el HTML', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/imprimir' => Http::response([
            'printed' => true,
            'printer' => 'EC-PM-80250LI',
            'copies' => 2,
        ]),
    ]);

    $administrador = Usuario::create([
        'name' => 'Administradora',
        'username' => 'administradora',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);
    $this->actingAs($administrador)
        ->put(route('ajustes.ticket.update'), [
            'encabezado' => "<script>alert('x')</script>\nRFC: ABC123",
            'pie' => "Gracias\nVisítanos pronto",
        ])
        ->assertRedirect(route('ajustes.index'));

    $cliente = Cliente::create([
        'nombre' => 'Ana',
        'apellido' => 'Prueba',
    ]);
    $plan = TipoMembresia::create([
        'nombre' => 'Mensual',
        'duracion_dias' => 30,
        'precio' => 350,
        'activo' => true,
    ]);
    $membresia = Membresia::create([
        'id_cliente' => $cliente->id,
        'tipo_membresia_id' => $plan->id,
        'comienzo' => today(),
        'finalizacion' => today()->addDays(30),
        'estado' => Membresia::ESTADO_ACTIVA,
    ]);
    $pago = Pago::create([
        'id_cliente' => $cliente->id,
        'membresia_id' => $membresia->id,
        'usuario_id' => $administrador->id,
        'monto' => 350,
        'metodo_pago' => 'efectivo',
    ]);

    $this->get(route('pagos.recibo', $pago))
        ->assertOk()
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee("<script>alert('x')</script>", false)
        ->assertSee('RFC: ABC123')
        ->assertSee('Visítanos pronto');

    $this->post(route('pagos.imprimir', $pago))
        ->assertRedirect(route('clientes.index'));

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://127.0.0.1:8765/imprimir');
    $contenido = Http::recorded()->first()[0]->body();

    expect($contenido)
        ->toContain("<script>alert('x')</script>?n\nRFC: ABC123")
        ->toContain("Gracias\nVisítanos pronto");
});

test('redirige al inicio de sesión cuando una persona invitada consulta ajustes', function () {
    $this->get(route('ajustes.index'))->assertRedirect(route('login'));
});
