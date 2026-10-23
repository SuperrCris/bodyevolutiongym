<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientesController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/clientes');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/clientes', [ClientesController::class, 'index'])->name('clientes.index');
    Route::post('/clientes', [ClientesController::class, 'store'])->name('clientes.store');
    Route::put('/clientes/{cliente}', [ClientesController::class, 'update'])->name('clientes.update');
    Route::post('/clientes/{cliente}/renovar', [ClientesController::class, 'renew'])->name('clientes.renew');
    Route::get('/clientes/resumen', [ClientesController::class, 'resumen'])->name('clientes.resumen');
});
