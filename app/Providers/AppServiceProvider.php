<?php

namespace App\Providers;

use App\Domain\Biometrics\BiometricVerifier;
use App\Domain\Biometrics\SdkBiometricVerifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BiometricVerifier::class, SdkBiometricVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
