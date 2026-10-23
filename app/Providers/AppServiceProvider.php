<?php

namespace App\Providers;

use App\Domain\Biometria\VerificadorBiometrico;
use App\Domain\Biometria\VerificadorBiometricoSdk;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VerificadorBiometrico::class, VerificadorBiometricoSdk::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
