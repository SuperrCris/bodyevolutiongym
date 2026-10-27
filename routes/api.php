<?php

use App\Http\Controllers\Api\BiometricController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\MembershipPlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('clientes', [CustomerController::class, 'index']);
    Route::post('clientes', [CustomerController::class, 'store']);
    Route::get('clientes/{customer}', [CustomerController::class, 'show']);
    Route::get('planes-de-membresia', [MembershipPlanController::class, 'index']);
    Route::post('planes-de-membresia', [MembershipPlanController::class, 'store']);
    Route::post('clientes/{cliente}/membresias/renovar', [MembershipController::class, 'renew']);
    Route::middleware(['web', 'auth'])->group(function (): void {
        Route::post('biometricos/verificar', [BiometricController::class, 'verificar']);
        Route::post('biometricos/capturar', [BiometricController::class, 'capturar']);
        Route::get('biometricos/scan', [BiometricController::class, 'scan']);
        Route::post('clientes/{cliente}/biometricos/enrolar', [BiometricController::class, 'enrolar']);

        Route::post('biometrics/verify', [BiometricController::class, 'verificar']);
        Route::post('biometrics/capture', [BiometricController::class, 'capturar']);
        Route::get('biometrics/scan', [BiometricController::class, 'scan']);
        Route::post('customers/{cliente}/biometrics/enroll', [BiometricController::class, 'enrolar']);
    });
});
