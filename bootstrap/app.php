<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Intervention\Image\ImageServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API middleware to'g'ri sozlash
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->api(append: [
            // 'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        // Rate limiting (Laravel 12 style)
        $middleware->throttleApi();
        
        // Yoki custom rate limiting
        // $middleware->throttleWith([
        //     'api' => \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by(optional(request()->user())->id ?: request()->ip())
        // ]);
    })
    // ->withProviders([
    //     ImageServiceProvider::class, // Intervention Image provider’ni qo‘shish
    // ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();