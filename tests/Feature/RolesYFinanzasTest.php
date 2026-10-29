<?php

use App\Models\Cliente;
use App\Models\Egreso;
use App\Models\Membresia;
use App\Models\Pago;
use App\Models\Registros;
use App\Models\TipoMembresia;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
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
        ->assertOk()
        ->assertDontSee('Egresos');

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

test('el corte diario muestra membresías por método de pago y exporta el CSV del día', function () {
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

    $vista = $this->actingAs($recepcionista)
        ->get("/reportes?fecha={$fecha}")
        ->assertOk();

    $vista
        ->assertSee('Plan anual')
        ->assertSee('Membresías')
        ->assertSee('Efectivo')
        ->assertSee('Aguas')
        ->assertSee('Salidas')
        ->assertSee('ID')
        ->assertSee('Nombre')
        ->assertSee('Fecha y hora')
        ->assertSee('Tipo de pago')
        ->assertSee('Tipo de membresía')
        ->assertSee('$499.00')
        ->assertDontSee('Insumos');

    $html = $vista->getContent();
    preg_match_all('/<details\b[^>]*data-metodo="(efectivo|transferencia|tarjeta)"[^>]*>/', $html, $desplegables);
    expect($desplegables[1])->toBe(['efectivo', 'transferencia', 'tarjeta']);
    expect($desplegables[0][0])->toContain('open');
    expect($desplegables[0][1])->not->toContain('open');
    expect($desplegables[0][2])->not->toContain('open');

    preg_match('/<tbody class="divide-y divide-white\/5">(.*?)<\/tbody>/s', $html, $filas);
    expect($filas[1] ?? '')
        ->toContain('Plan anual')
        ->not->toContain('Aguas')
        ->not->toContain('Salidas');

    $excel = $this->get("/reportes/excel?fecha={$fecha}");
    $excel->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect($excel->headers->get('content-disposition'))
        ->toContain('reporte-membresias-'.str_replace('-', '', $fecha).'_0000-'.str_replace('-', '', $fecha).'_2359.xlsx');

    $libro = new ZipArchive;
    expect($libro->open($excel->baseResponse->getFile()->getPathname()))->toBeTrue();
    $hoja = $libro->getFromName('xl/worksheets/sheet1.xml');
    $estilos = $libro->getFromName('xl/styles.xml');
    $libro->close();

    expect($hoja)
        ->toContain('Plan anual')
        ->toContain('Luis')
        ->toContain('Aguas')
        ->toContain('Salidas');
    expect($estilos)->toContain('fills');
});

test('el reporte filtra membresías por fecha y hora e incluye completos los ajustes de cada fecha', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan de prueba',
        'duracion_dias' => 30,
        'precio' => 100.00,
        'activo' => true,
    ]);
    $fechas = [
        ['nombre' => 'Antes', 'fecha' => '2026-10-28 14:59:59', 'monto' => 10.00],
        ['nombre' => 'Inicio', 'fecha' => '2026-10-28 15:00:00', 'monto' => 100.00],
        ['nombre' => 'Dentro', 'fecha' => '2026-10-29 10:00:59', 'monto' => 50.00],
        ['nombre' => 'Después', 'fecha' => '2026-10-29 10:01:00', 'monto' => 20.00],
    ];

    foreach ($fechas as $datosPago) {
        $cliente = Cliente::create(['nombre' => $datosPago['nombre'], 'apellido' => 'Prueba']);
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
            'usuario_id' => $recepcionista->id,
            'monto' => $datosPago['monto'],
            'metodo_pago' => 'cash',
        ]);

        DB::table('pagos')
            ->where('id', $pago->id)
            ->update(['created_at' => $datosPago['fecha']]);
    }

    foreach ([
        ['fecha' => '2026-10-28', 'concepto' => 'Aguas', 'monto' => '10.00'],
        ['fecha' => '2026-10-29', 'concepto' => 'Aguas', 'monto' => '20.00'],
        ['fecha' => '2026-10-29', 'concepto' => 'Salidas', 'monto' => '-3.00'],
    ] as $indice => $ajuste) {
        DB::table('reporte_ajustes_manuales')->insert([
            ...$ajuste,
            'metodo_pago' => 'efectivo',
            'orden' => $indice,
            'usuario_id' => $recepcionista->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $parametros = [
        'desde' => '2026-10-28',
        'hora_desde' => '15:00',
        'hasta' => '2026-10-29',
        'hora_hasta' => '10:00',
    ];
    $this->actingAs($recepcionista)
        ->get('/reportes?'.http_build_query($parametros))
        ->assertOk()
        ->assertSee('Inicio Prueba')
        ->assertSee('Dentro Prueba')
        ->assertDontSee('Antes Prueba')
        ->assertDontSee('Después Prueba')
        ->assertSee('30.00')
        ->assertSee('-3.00')
        ->assertSee('150.00')
        ->assertSee('27.00')
        ->assertSee('177.00')
        ->assertSee('Los ajustes manuales se incluyen completos')
        ->assertDontSee('Guardar ajustes');

    $excel = $this->get('/reportes/excel?'.http_build_query($parametros))
        ->assertOk();
    expect($excel->headers->get('content-disposition'))
        ->toContain('reporte-membresias-20261028_1500-20261029_1000.xlsx');

    $libro = new ZipArchive;
    expect($libro->open($excel->baseResponse->getFile()->getPathname()))->toBeTrue();
    $hoja = $libro->getFromName('xl/worksheets/sheet1.xml');
    $libro->close();

    expect($hoja)
        ->toContain('Inicio')
        ->toContain('Dentro')
        ->not->toContain('Antes')
        ->not->toContain('Despu');

    $this->get('/reportes?desde=2026-10-29&hora_desde=15:00&hasta=2026-10-29&hora_hasta=14:59')
        ->assertSessionHasErrors([
            'hasta' => 'El momento final debe ser igual o posterior al momento inicial.',
        ]);
});

test('el reporte de membresías filtra la tabla y el Excel por ID de cliente', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $plan = TipoMembresia::create([
        'nombre' => 'Plan individual',
        'duracion_dias' => 30,
        'precio' => 100.00,
        'activo' => true,
    ]);
    $clienteFiltrado = Cliente::create(['nombre' => 'Cliente Elegido', 'apellido' => 'Prueba']);
    $clienteExcluido = Cliente::create(['nombre' => 'Cliente Excluido', 'apellido' => 'Prueba']);
    $membresiaFiltrada = Membresia::create([
        'id_cliente' => $clienteFiltrado->id,
        'tipo_membresia_id' => $plan->id,
        'comienzo' => today(),
        'finalizacion' => today()->addDays(29),
        'estado' => Membresia::ESTADO_ACTIVA,
    ]);
    $membresiaExcluida = Membresia::create([
        'id_cliente' => $clienteExcluido->id,
        'tipo_membresia_id' => $plan->id,
        'comienzo' => today(),
        'finalizacion' => today()->addDays(29),
        'estado' => Membresia::ESTADO_ACTIVA,
    ]);

    foreach (range(1, 31) as $numero) {
        Pago::create([
            'id_cliente' => $clienteFiltrado->id,
            'membresia_id' => $membresiaFiltrada->id,
            'usuario_id' => $recepcionista->id,
            'monto' => 100.00,
            'metodo_pago' => 'cash',
        ]);
    }

    Pago::create([
        'id_cliente' => $clienteExcluido->id,
        'membresia_id' => $membresiaExcluida->id,
        'usuario_id' => $recepcionista->id,
        'monto' => 100.00,
        'metodo_pago' => 'cash',
    ]);

    $fecha = today()->toDateString();
    $respuesta = $this->actingAs($recepcionista)
        ->get('/reportes?'.http_build_query(['fecha' => $fecha, 'cliente_id' => $clienteFiltrado->id]))
        ->assertOk()
        ->assertSee('name="cliente_id" value="'.$clienteFiltrado->id.'"', false)
        ->assertSee('Cliente Elegido Prueba')
        ->assertDontSee('Cliente Excluido Prueba')
        ->assertSee('31 pagos')
        ->assertSee('cliente_id='.$clienteFiltrado->id);

    $excel = $this->get(route('reportes.excel', [
        'fecha' => $fecha,
        'cliente_id' => $clienteFiltrado->id,
    ]))->assertOk();
    $libro = new ZipArchive;

    expect($libro->open($excel->baseResponse->getFile()->getPathname()))->toBeTrue();
    $hoja = $libro->getFromName('xl/worksheets/sheet1.xml');
    $libro->close();

    expect($hoja)
        ->toContain('Cliente Elegido')
        ->not->toContain('Cliente Excluido');
});

test('no permite editar ajustes desde un reporte de varios días', function () {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);

    $this->actingAs($recepcionista)
        ->post('/reportes/ajustes', [
            'fecha' => '2026-10-28',
            'desde' => '2026-10-28',
            'hasta' => '2026-10-29',
            'hora_desde' => '00:00',
            'hora_hasta' => '23:59',
            'ajustes' => [
                'efectivo' => [],
                'transferencia' => [],
                'tarjeta' => [],
            ],
        ])
        ->assertSessionHasErrors([
            'fecha' => 'Los ajustes manuales solo pueden editarse para un solo día.',
        ]);

    $this->assertDatabaseCount('reporte_ajustes_manuales', 0);
    $this->assertDatabaseCount('registros', 0);
});

test('una recepcionista guarda ajustes diarios por método y el total considera importes negativos', function () {
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
    $fecha = today()->toDateString();

    $this->actingAs($recepcionista)
        ->post('/reportes/ajustes', [
            'fecha' => $fecha,
            'ajustes' => [
                'efectivo' => [
                    ['concepto' => 'Aguas', 'monto' => '500.00'],
                    ['concepto' => 'Salidas', 'monto' => '-100.50'],
                    ['concepto' => 'Venta especial', 'monto' => '10.00'],
                ],
                'transferencia' => [
                    ['concepto' => 'Aguas', 'monto' => '100.00'],
                    ['concepto' => 'Salidas', 'monto' => '-100.00'],
                ],
                'tarjeta' => [
                    ['concepto' => 'Aguas', 'monto' => null],
                    ['concepto' => 'Salidas', 'monto' => null],
                ],
            ],
        ])
        ->assertRedirect("/reportes?fecha={$fecha}");

    $this->assertDatabaseCount('reporte_ajustes_manuales', 5);
    $this->assertDatabaseHas('reporte_ajustes_manuales', [
        'fecha' => $fecha,
        'metodo_pago' => 'efectivo',
        'concepto' => 'Salidas',
        'monto' => '-100.50',
    ]);
    $this->assertDatabaseHas('registros', [
        'usuario_id' => $recepcionista->id,
        'accion' => 'reporte.ajustes.actualizados',
    ]);

    $vista = $this->get("/reportes?fecha={$fecha}")
        ->assertOk()
        ->assertSee('Venta especial')
        ->assertSee('908.50');

    preg_match('/<tbody class="divide-y divide-white\/5">(.*?)<\/tbody>/s', $vista->getContent(), $filas);
    expect($filas[1] ?? '')
        ->toContain('Plan anual')
        ->not->toContain('Venta especial')
        ->not->toContain('Aguas')
        ->not->toContain('Salidas');
});

test('el reporte rechaza importes manuales inválidos sin guardar cambios', function (string $monto) {
    $recepcionista = Usuario::create([
        'name' => 'Recepción',
        'username' => 'recepcion',
        'role' => Usuario::ROLE_RECEPCIONISTA,
        'email' => null,
        'password' => 'password',
    ]);
    $fecha = today()->toDateString();

    $this->actingAs($recepcionista)
        ->from("/reportes?fecha={$fecha}")
        ->post('/reportes/ajustes', [
            'fecha' => $fecha,
            'ajustes' => [
                'efectivo' => [
                    ['concepto' => 'Aguas', 'monto' => $monto],
                ],
                'transferencia' => [],
                'tarjeta' => [],
            ],
        ])
        ->assertRedirect("/reportes?fecha={$fecha}")
        ->assertSessionHasErrors('ajustes.efectivo.0.monto');

    $this->assertDatabaseCount('reporte_ajustes_manuales', 0);
    $this->assertDatabaseCount('registros', 0);
})->with([
    'texto no numérico' => 'no es un importe',
    'más de dos decimales' => '10.999',
]);

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
