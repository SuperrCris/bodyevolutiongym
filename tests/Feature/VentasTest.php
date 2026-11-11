<?php

use App\Models\Cliente;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\Registros;
use App\Models\TipoMembresia;
use App\Models\Usuario;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

test('una recepcionista registra una venta de membresía sin asignarla a un cliente y aparece en reportes', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $plan = TipoMembresia::create([
        'nombre' => 'Pase de un día',
        'duracion_dias' => 1,
        'precio' => 25.00,
        'activo' => true,
    ]);

    $this->actingAs($recepcionista)
        ->post(route('ventas.store'), [
            'tipo_membresia_id' => $plan->id,
            'metodo_pago' => 'efectivo',
            'nombre_comprador' => 'Pase mostrador',
            'imprimir' => 'no',
        ])
        ->assertRedirect(route('clientes.index'))
        ->assertSessionHas('success', 'Venta de membresía registrada sin asignar a un cliente.');

    $pago = Pago::query()->firstOrFail();
    $this->assertDatabaseHas('pagos', [
        'id' => $pago->id,
        'id_cliente' => null,
        'membresia_id' => null,
        'tipo_membresia_id' => $plan->id,
        'usuario_id' => $recepcionista->id,
        'monto' => '25.00',
        'metodo_pago' => 'efectivo',
        'nombre_comprador' => 'Pase mostrador',
    ]);
    $this->assertDatabaseCount('clientes', 0);
    $this->assertDatabaseCount('membresias', 0);
    $this->assertDatabaseHas('registros', [
        'usuario_id' => $recepcionista->id,
        'accion' => 'membresia.venta_sin_cliente',
        'id_registro' => $pago->id,
    ]);

    $this->get(route('clientes.index'))
        ->assertOk()
        ->assertSee('data-open-modal="registrarVenta"', false)
        ->assertSee('Nombre')
        ->assertSee('name="nombre_comprador"', false);

    $this->get(route('ventas.index'))
        ->assertOk()
        ->assertSee('Pase mostrador')
        ->assertSee('Pase de un día')
        ->assertSee('name="metodo_pago"', false);

    $this->get(route('reportes.index', ['fecha' => now(config('app.display_timezone'))->toDateString()]))
        ->assertOk()
        ->assertSee('Pase mostrador')
        ->assertSee('Pase de un día')
        ->assertSee('$25.00');
});

it('muestra las fechas de ventas en el horario local en el historial y los reportes', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan mensual',
        'duracion_dias' => 30,
        'precio' => 300.00,
        'activo' => true,
    ]);
    $pago = Pago::create([
        'tipo_membresia_id' => $plan->id,
        'usuario_id' => $recepcionista->id,
        'monto' => 300.00,
        'metodo_pago' => 'efectivo',
        'nombre_comprador' => 'Venta de prueba',
    ]);
    DB::table('pagos')->where('id', $pago->id)->update([
        'created_at' => '2026-11-10 00:07:00',
        'updated_at' => '2026-11-10 00:07:00',
    ]);

    $this->actingAs($recepcionista)
        ->get(route('ventas.index'))
        ->assertOk()
        ->assertSee('09/11/2026 17:07');

    $this->get(route('reportes.index', ['fecha' => '2026-11-09']))
        ->assertOk()
        ->assertSee('09/11/2026 17:07');

    $excel = $this->get(route('reportes.excel', ['fecha' => '2026-11-09']))
        ->assertOk();
    $libro = new ZipArchive;
    expect($libro->open($excel->baseResponse->getFile()->getPathname()))->toBeTrue();
    $hoja = $libro->getFromName('xl/worksheets/sheet1.xml');
    $libro->close();

    expect($hoja)->toContain('09/11/2026 17:07');
});

test('una recepcionista puede corregir el método de pago de una venta existente', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'Pérez']);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan mensual',
        'duracion_dias' => 30,
        'precio' => 300.00,
        'activo' => true,
    ]);
    $membresia = Membresia::create([
        'id_cliente' => $cliente->id,
        'tipo_membresia_id' => $plan->id,
        'comienzo' => today(),
        'finalizacion' => today()->addDays(29),
        'estado' => Membresia::ESTADO_ACTIVA,
    ]);
    $pago = Pago::create([
        'id_cliente' => $cliente->id,
        'membresia_id' => $membresia->id,
        'monto' => 300.00,
        'metodo_pago' => 'efectivo',
    ]);

    $this->actingAs($recepcionista)
        ->put(route('ventas.metodo.update', $pago), ['metodo_pago' => 'tarjeta'])
        ->assertRedirect(route('ventas.index'))
        ->assertSessionHas('success', 'Método de pago corregido correctamente.');

    $this->assertDatabaseHas('pagos', [
        'id' => $pago->id,
        'metodo_pago' => 'tarjeta',
    ]);
    expect(Registros::query()->where('accion', 'pago.metodo_pago.actualizado')->firstOrFail()->metadatos)
        ->toBe(['metodo_anterior' => 'efectivo', 'metodo_nuevo' => 'tarjeta']);
});

test('no permite registrar una venta con un plan inactivo', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan inactivo',
        'duracion_dias' => 1,
        'precio' => 25.00,
        'activo' => false,
    ]);

    $this->actingAs($recepcionista)
        ->post(route('ventas.store'), [
            'tipo_membresia_id' => $plan->id,
            'metodo_pago' => 'efectivo',
            'imprimir' => 'no',
        ])
        ->assertSessionHasErrors('tipo_membresia_id');

    $this->assertDatabaseCount('pagos', 0);
});

test('registra una venta sin cliente e imprime los tickets con comprador y plan', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/imprimir' => Http::response([
            'printed' => true,
            'printer' => 'EC-PM-80250LI',
            'copies' => 2,
        ]),
    ]);

    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $plan = TipoMembresia::create([
        'nombre' => 'Pase de un día',
        'duracion_dias' => 1,
        'precio' => 25.00,
        'activo' => true,
    ]);

    $this->actingAs($recepcionista)
        ->post(route('ventas.store'), [
            'tipo_membresia_id' => $plan->id,
            'metodo_pago' => 'efectivo',
            'nombre_comprador' => 'Pase mostrador',
            'imprimir' => 'dos',
        ])
        ->assertRedirect(route('clientes.index'))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Dos tickets enviados'));

    $pago = Pago::query()->firstOrFail();
    $this->assertModelExists($pago);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://127.0.0.1:8765/imprimir'
        && str_starts_with($request->body(), "BEG-PRINT-V2\n2\n")
        && str_contains($request->body(), 'Comprador: Pase mostrador')
        && str_contains($request->body(), 'Membresia pase de un día'));
});

test('conserva el recibo de una venta independiente si falla la impresión', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8765/imprimir' => Http::failedConnection(),
    ]);

    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $plan = TipoMembresia::create([
        'nombre' => 'Pase de un día',
        'duracion_dias' => 1,
        'precio' => 25.00,
        'activo' => true,
    ]);

    $response = $this->actingAs($recepcionista)
        ->post(route('ventas.store'), [
            'tipo_membresia_id' => $plan->id,
            'metodo_pago' => 'efectivo',
            'nombre_comprador' => 'Pase mostrador',
            'imprimir' => 'uno',
        ]);
    $pago = Pago::query()->firstOrFail();

    $response->assertRedirect(route('pagos.recibo', $pago))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'puente local de impresión'));

    $this->get(route('pagos.recibo', $pago))
        ->assertOk()
        ->assertSee('Pase mostrador')
        ->assertSee('Pase de un día')
        ->assertSee('No asignada')
        ->assertSee(route('pagos.imprimir', $pago), false);

    $this->assertModelExists($pago);
});

test('no permite cambiar un pago a un método de pago no admitido', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $pago = Pago::create([
        'monto' => 25.00,
        'metodo_pago' => 'efectivo',
    ]);

    $this->actingAs($recepcionista)
        ->put(route('ventas.metodo.update', $pago), ['metodo_pago' => 'bitcoin'])
        ->assertSessionHasErrors('metodo_pago');

    $this->assertDatabaseHas('pagos', [
        'id' => $pago->id,
        'metodo_pago' => 'efectivo',
    ]);
    $this->assertDatabaseCount('registros', 0);
});
