<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\EgresosController;
use App\Http\Controllers\EmpleadosController;
use App\Http\Controllers\RegistrosController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\TiposMembresiaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/clientes');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::post('/login/huella', [AuthController::class, 'fingerprint'])
        ->middleware('throttle:fingerprint-login')
        ->name('login.fingerprint');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::middleware('rol:recepcionista')->group(function (): void {
        Route::get('/clientes', [ClientesController::class, 'index'])->name('clientes.index');
        Route::post('/clientes', [ClientesController::class, 'store'])->name('clientes.store');
        Route::put('/clientes/{cliente}', [ClientesController::class, 'update'])->name('clientes.update');
        Route::post('/clientes/{cliente}/renovar', [ClientesController::class, 'renovar'])->name('clientes.renovar');
        Route::get('/pagos/{pago}/recibo', [ClientesController::class, 'receipt'])->name('pagos.recibo');
        Route::post('/pagos/{pago}/imprimir', [ClientesController::class, 'printReceipt'])->name('pagos.imprimir');
        Route::get('/clientes/resumen', [ClientesController::class, 'resumen'])->name('clientes.resumen');
        Route::get('/egresos', [EgresosController::class, 'index'])->name('egresos.index');
        Route::post('/egresos', [EgresosController::class, 'store'])->name('egresos.store');
        Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes.index');
        Route::get('/reportes/csv', [ReportesController::class, 'exportar'])->name('reportes.csv');
    });

    Route::middleware('rol:administrador')->group(function (): void {
        Route::put('/membresias/{membresia}/vigencia', [ClientesController::class, 'actualizarVigencia'])
            ->name('membresias.vigencia.update');
        Route::get('/tipos-de-membresia', [TiposMembresiaController::class, 'index'])->name('tipos-membresia.index');
        Route::post('/tipos-de-membresia', [TiposMembresiaController::class, 'store'])->name('tipos-membresia.store');
        Route::put('/tipos-de-membresia/{tipoMembresia}', [TiposMembresiaController::class, 'update'])
            ->name('tipos-membresia.update');
        Route::get('/empleados', [EmpleadosController::class, 'index'])->name('empleados.index');
        Route::post('/empleados', [EmpleadosController::class, 'store'])->name('empleados.store');
        Route::put('/empleados/{empleado}', [EmpleadosController::class, 'update'])->name('empleados.update');
        Route::get('/registros', [RegistrosController::class, 'index'])->name('registros.index');
    });
});
