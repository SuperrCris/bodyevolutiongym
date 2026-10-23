<?php

use App\Http\Controllers\Api\BiometricController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\MembershipPlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('customers', [CustomerController::class, 'index']);
    Route::post('customers', [CustomerController::class, 'store']);
    Route::get('customers/{customer}', [CustomerController::class, 'show']);
    Route::get('membership-plans', [MembershipPlanController::class, 'index']);
    Route::post('membership-plans', [MembershipPlanController::class, 'store']);
    Route::post('customers/{customer}/memberships/renew', [MembershipController::class, 'renew']);
    Route::post('biometrics/verify', [BiometricController::class, 'verify']);
});
