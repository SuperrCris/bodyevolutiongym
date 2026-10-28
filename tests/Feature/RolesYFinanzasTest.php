<?php

use App\Models\Cliente;
use App\Models\Egreso;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\Registros;
use App\Models\TipoMembresia;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

test('el usuario puede iniciar sesión por nombre de usuario sin depender del correo', function () {
    Usuario::create([
        'name' => 'Recepción Norte',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password-seguro',
    ]);

    $this->post('/login', [
        'username' => 'RECEPCION',
        'password' => 'password-seguro',
    ])->assertRedirect('/clientes');

    $this->post('/logout');

    $this->post('/login', [
        'username' => 'Recepción Norte',
        'password' => 'password-seguro',
    ])->assertRedirect('/clientes');

    $this->post('/logout');

    $this->post('/login', [
        'username' => 'recepcion',
        'password' => 'incorrecta',
    ])->assertSessionHasErrors('username');
});

test('genera un nombre de usuario para cuentas creadas por integraciones existentes', function () {
    $usuario = Usuario::create([
        'name' => 'Operador Norte',
        'email' => null,
        'password' => 'password',
    ]);

    expect($usuario->username)->toBe('operador');
    expect($usuario->refresh()->role)->toBe(Usuario::ROLE_RECEPCIONISTA);
});

test('una recepcionista puede operar clientes y finanzas pero no administrar cuentas ni revisar auditoría', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($recepcionista)
        ->get('/clientes')
        ->assertOk();

    $this->get('/egresos')->assertOk();
    $this->get('/reportes')->assertOk();
    $this->get('/empleados')->assertForbidden();
    $this->get('/registros')->assertForbidden();
});

test('las rutas de clientes de la API requieren autenticación', function () {
    $this->get('/api/v1/clientes')->assertUnauthorized();
});

test('un administrador puede crear empleados y registrar la acción en auditoría', function () {
    $administrador = Usuario::create([
        'name' => 'Cristian',
        'username' => 'cristian',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($administrador)
        ->get('/empleados')
        ->assertOk();

    $this->get('/registros')->assertOk();

    $this->post('/empleados', [
        'name' => 'Ana Recepción',
        'username' => 'ana.recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'otra-clave-segura',
        'password_confirmation' => 'otra-clave-segura',
    ])
        ->assertRedirect('/empleados');

    $empleado = Usuario::query()->where('username', 'ana.recepcion')->firstOrFail();

    expect($empleado->role)->toBe(Usuario::ROLE_RECEPCIONISTA);
    expect(Hash::check('otra-clave-segura', $empleado->password))->toBeTrue();
    $this->assertDatabaseHas('registros', [
        'usuario_id' => $administrador->id,
        'accion' => 'empleado.creado',
    ]);
});

test('no permite iniciar sesión por un nombre completo compartido entre cuentas', function () {
    Usuario::create([
        'name' => 'Ana Lopez',
        'username' => 'ana.uno',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    Usuario::create([
        'name' => 'Ana Lopez',
        'username' => 'ana.dos',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);

    $this->post('/login', [
        'username' => 'Ana Lopez',
        'password' => 'password',
    ])->assertSessionHasErrors('username');
});

test('un administrador puede restablecer la contraseña de un empleado', function () {
    $administrador = Usuario::create([
        'name' => 'Cristian',
        'username' => 'cristian',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);
    $empleado = Usuario::create([
        'name' => 'Ana Recepción',
        'username' => 'ana.recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'clave-anterior',
    ]);

    $this->actingAs($administrador)
        ->put("/empleados/{$empleado->id}", [
            'name' => $empleado->name,
            'username' => $empleado->username,
            'role' => Usuario::ROLE_RECEPCIONISTA,
            'email' => null,
            'password' => 'clave-nueva-segura',
            'password_confirmation' => 'clave-nueva-segura',
        ])
        ->assertRedirect('/empleados');

    expect(Hash::check('clave-nueva-segura', $empleado->refresh()->password))->toBeTrue();
});

test('el administrador revisa las acciones registradas', function () {
    $administrador = Usuario::create([
        'name' => 'Cristian',
        'username' => 'cristian',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);
    $this->actingAs($administrador);
    Registros::create([
        'usuario_id' => $administrador->id,
        'accion' => 'prueba.auditoria',
        'metadatos' => ['origen' => 'prueba'],
    ]);

    $this->get('/registros?accion=prueba.auditoria')
        ->assertOk()
        ->assertSee('prueba.auditoria')
        ->assertSee('origen');
});

test('una recepcionista no puede crear cuentas de empleado', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($recepcionista)
        ->post('/empleados', [
            'name' => 'Ana Recepción',
            'username' => 'ana.recepcion',
            'role' => Usuario::ROLE_RECEPCIONISTA,
            'email' => null,
            'password' => 'otra-clave-segura',
            'password_confirmation' => 'otra-clave-segura',
        ])
        ->assertForbidden();
});

test('una cuenta no puede dejar al sistema sin administradores', function () {
    $administrador = Usuario::create([
        'name' => 'Cristian',
        'username' => 'cristian',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($administrador)
        ->put("/empleados/{$administrador->id}", [
            'name' => 'Cristian',
            'username' => 'cristian',
            'role' => Usuario::ROLE_RECEPCIONISTA,
            'email' => null,
        ])
        ->assertSessionHasErrors('role');

    expect($administrador->refresh()->role)->toBe(Usuario::ROLE_ADMINISTRADOR);
});

test('una recepcionista puede registrar egresos y estos quedan auditados', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($recepcionista)
        ->post('/egresos', [
            'concepto' => 'Mantenimiento de caminadora',
            'categoria' => 'Mantenimiento',
            'monto' => '125.50',
            'fecha' => today()->toDateString(),
            'metodo_pago' => 'transferencia',
            'referencia' => 'TRX-001',
        ])
        ->assertRedirect('/egresos');

    $egreso = Egreso::query()->where('concepto', 'Mantenimiento de caminadora')->firstOrFail();

    expect($egreso->monto)->toBe('125.50');
    expect($egreso->usuario_id)->toBe($recepcionista->id);
    $this->assertDatabaseHas('registros', [
        'usuario_id' => $recepcionista->id,
        'accion' => 'egreso.creado',
    ]);
});

test('el reporte separa ingresos de membresías y egresos por categoría y exporta CSV', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Luis', 'apellido' => 'Pérez']);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan anual',
        'duracion_dias' => 365,
        'precio' => 499.00,
        'activo' => true,
    ]);
    $membresia = Membresia::create([
        'id_cliente' => $cliente->id,
        'tipo_membresia_id' => $plan->id,
        'comienzo' => today(),
        'finalizacion' => today()->addDays(364),
        'estado' => Membresia::ESTADO_ACTIVA,
    ]);
    Pago::create([
        'id_cliente' => $cliente->id,
        'membresia_id' => $membresia->id,
        'usuario_id' => $recepcionista->id,
        'monto' => 499.00,
        'metodo_pago' => 'cash',
    ]);
    Egreso::create([
        'usuario_id' => $recepcionista->id,
        'concepto' => 'Limpieza',
        'categoria' => 'Insumos',
        'monto' => 125.00,
        'fecha' => today(),
        'metodo_pago' => 'efectivo',
    ]);
    $fecha = today()->toDateString();
    $this->assertDatabaseHas('egresos', ['categoria' => 'Insumos']);

    $this->actingAs($recepcionista)
        ->get("/reportes?desde={$fecha}&hasta={$fecha}")
        ->assertOk()
        ->assertSee('Plan anual')
        ->assertSee('Membresías')
        ->assertSee('Insumos')
        ->assertSee('$499.00')
        ->assertSee('$125.00')
        ->assertSee('$374.00');

    $csv = $this->get("/reportes/csv?desde={$fecha}&hasta={$fecha}");
    $csv->assertOk();

    expect($csv->headers->get('content-disposition'))->toContain('reporte-financiero-'.$fecha.'-'.$fecha.'.csv');
});

test('un administrador puede crear y actualizar planes de membresía y auditar los cambios', function () {
    $administrador = Usuario::create([
        'name' => 'Cristian',
        'username' => 'cristian',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($administrador)
        ->get('/tipos-de-membresia')
        ->assertOk()
        ->assertSee('Configurar membresías');

    $this->post('/tipos-de-membresia', [
        'nombre' => 'Membresía mensual',
        'duracion_dias' => 30,
        'precio' => '499.00',
        'activo' => '1',
    ])->assertRedirect('/tipos-de-membresia');

    $plan = TipoMembresia::query()->where('nombre', 'Membresía mensual')->firstOrFail();
    $this->assertModelExists($plan);
    $this->assertDatabaseHas('registros', [
        'usuario_id' => $administrador->id,
        'accion' => 'tipo_membresia.creado',
    ]);

    $this->put("/tipos-de-membresia/{$plan->id}", [
        'nombre' => 'Membresía mensual plus',
        'duracion_dias' => 45,
        'precio' => '599.00',
        'activo' => '0',
    ])
        ->assertRedirect('/tipos-de-membresia')
        ->assertSessionHas('success');

    expect($plan->refresh()->nombre)->toBe('Membresía mensual plus');
    expect($plan->duracion_dias)->toBe(45);
    expect($plan->precio)->toBe('599.00');
    expect($plan->activo)->toBeFalse();
    $this->assertDatabaseHas('registros', [
        'usuario_id' => $administrador->id,
        'accion' => 'tipo_membresia.actualizado',
    ]);
});

test('una recepcionista no puede abrir ni modificar planes de membresía', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);

    $this->get('/tipos-de-membresia')->assertRedirect('/login');

    $this->actingAs($recepcionista)
        ->get('/clientes')
        ->assertOk()
        ->assertDontSee(route('tipos-membresia.index'), false);

    $this->get('/tipos-de-membresia')
        ->assertForbidden();

    $this->post('/tipos-de-membresia', [
        'nombre' => 'Plan no autorizado',
        'duracion_dias' => 30,
        'precio' => '100.00',
        'activo' => '1',
    ])->assertForbidden();

    $this->postJson('/api/v1/planes-de-membresia', [
        'nombre' => 'Plan no autorizado',
        'duracion_dias' => 30,
        'precio' => '100.00',
        'activo' => true,
    ])->assertForbidden();

    $this->assertDatabaseCount('tipo_membresias', 0);
});

test('un administrador puede crear planes desde la API y registrar la acción', function () {
    $administrador = Usuario::create([
        'name' => 'Cristian',
        'username' => 'cristian',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($administrador)
        ->postJson('/api/v1/planes-de-membresia', [
            'nombre' => 'Plan API',
            'duracion_dias' => 30,
            'precio' => '299.00',
        ])
        ->assertCreated()
        ->assertJsonPath('nombre', 'Plan API')
        ->assertJsonPath('activo', true);

    $this->assertDatabaseHas('tipo_membresias', [
        'nombre' => 'Plan API',
        'duracion_dias' => 30,
        'precio' => '299.00',
        'activo' => true,
    ]);
    $this->assertDatabaseHas('registros', [
        'usuario_id' => $administrador->id,
        'accion' => 'tipo_membresia.creado',
    ]);
});

test('rechaza duraciones invalidas al configurar planes de membresía', function () {
    $administrador = Usuario::create([
        'name' => 'Cristian',
        'username' => 'cristian',
        'role' => Usuario::ROLE_ADMINISTRADOR,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($administrador)
        ->post('/tipos-de-membresia', [
            'nombre' => 'Plan inválido',
            'duracion_dias' => 0,
            'precio' => '100.00',
            'activo' => '1',
        ])
        ->assertSessionHasErrors('duracion_dias');

    $this->assertDatabaseCount('tipo_membresias', 0);
});

test('los planes desactivados no aparecen ni se pueden renovar desde web o API', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $cliente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'López']);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan pausado',
        'duracion_dias' => 30,
        'precio' => 350,
        'activo' => false,
    ]);

    $this->actingAs($recepcionista)
        ->get('/clientes')
        ->assertOk()
        ->assertDontSee('Plan pausado');

    $this->post("/clientes/{$cliente->id}/renovar", [
        'tipo_membresia_id' => $plan->id,
        'metodo_pago' => 'efectivo',
    ])->assertSessionHasErrors('tipo_membresia_id');

    $this->postJson("/api/v1/clientes/{$cliente->id}/membresias/renovar", [
        'membership_plan_id' => $plan->id,
        'payment_method' => 'cash',
    ])->assertJsonValidationErrors('membership_plan_id');

    expect($cliente->membresias()->exists())->toBeFalse();
    expect($cliente->pagos()->exists())->toBeFalse();
});
