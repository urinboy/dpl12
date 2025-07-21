<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API Rate Limiter aniqlash
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        // Guest foydalanuvchilar uchun kam limit
        RateLimiter::for('guest', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Authenticated foydalanuvchilar uchun ko'p limit
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(100)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
