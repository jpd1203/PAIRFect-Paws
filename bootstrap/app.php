<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\AdopterOnly;
use App\Http\Middleware\StaffOnly;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Local/ngrok uses loopback; Azure's TLS-terminating proxy is configured
        // separately through TRUSTED_PROXIES without forcing HTTPS locally.
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')),
        )));
        $middleware->trustProxies(at: $trustedProxies);

        $middleware->alias([
            'admin' => AdminOnly::class,
            'staff' => StaffOnly::class,
            'adopter' => AdopterOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
