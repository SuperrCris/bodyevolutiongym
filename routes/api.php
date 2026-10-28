<?php

use App\Http\Controllers\Api\BiometricController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EmpleadoBiometriaController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\MembershipPlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['web', 'auth', 'rol:recepcionista'])->group(function (): void {
    Route::get('clientes', [CustomerController::class, 'index']);
    Route::post('clientes', [CustomerController::class, 'store']);
    Route::get('clientes/{customer}', [CustomerController::class, 'show']);
    Route::get('planes-de-membresia', [MembershipPlanController::class, 'index']);
    Route::post('clientes/{cliente}/membresias/renovar', [MembershipController::class, 'renew']);
    Route::post('biometricos/verificar', [BiometricController::class, 'verificar']);
    Route::post('biometricos/capturar', [BiometricController::class, 'capturar']);
    Route::get('biometricos/scan', [BiometricController::class, 'scan']);
    Route::post('clientes/{cliente}/biometricos/enrolar/iniciar', [BiometricController::class, 'iniciarEnrolar']);
    Route::get('clientes/{cliente}/biometricos/enrolar/progreso', [BiometricController::class, 'progresoEnrolar']);
    Route::post('clientes/{cliente}/biometricos/enrolar/finalizar', [BiometricController::class, 'finalizarEnrolar']);
    Route::post('clientes/{cliente}/biometricos/enrolar', [BiometricController::class, 'enrolar']);

    Route::post('biometrics/verify', [BiometricController::class, 'verificar']);
    Route::post('biometrics/capture', [BiometricController::class, 'capturar']);
    Route::get('biometrics/scan', [BiometricController::class, 'scan']);
    Route::post('customers/{cliente}/biometrics/enroll/start', [BiometricController::class, 'iniciarEnrolar']);
    Route::get('customers/{cliente}/biometrics/enroll/progress', [BiometricController::class, 'progresoEnrolar']);
    Route::post('customers/{cliente}/biometrics/enroll/finish', [BiometricController::class, 'finalizarEnrolar']);
    Route::post('customers/{cliente}/biometrics/enroll', [BiometricController::class, 'enrolar']);

    Route::middleware('rol:administrador')->group(function (): void {
        Route::post('planes-de-membresia', [MembershipPlanController::class, 'store']);
        Route::post('empleados/{empleado}/biometricos/enrolar/iniciar', [EmpleadoBiometriaController::class, 'iniciarEnrolar'])
            ->name('empleados.huella.iniciar');
        Route::get('empleados/{empleado}/biometricos/enrolar/progreso', [EmpleadoBiometriaController::class, 'progresoEnrolar'])
            ->name('empleados.huella.progreso');
        Route::post('empleados/{empleado}/biometricos/enrolar/finalizar', [EmpleadoBiometriaController::class, 'finalizarEnrolar'])
            ->name('empleados.huella.finalizar');
    });
});
