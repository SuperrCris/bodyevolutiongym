<?php

namespace App\Providers;

use App\Domain\Biometria\VerificadorBiometrico;
use App\Domain\Biometria\VerificadorBiometricoSdk;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', function (Request $request): Limit {
            $identificador = Str::transliterate(
                Str::lower($request->input('username', '')).'|'.$request->ip(),
            );

            return Limit::perMinute(5)->by($identificador);
        });

        RateLimiter::for('fingerprint-login', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip() ?? 'unknown');
        });
    }
}
