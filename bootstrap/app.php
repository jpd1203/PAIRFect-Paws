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
        // Local development trusts loopback only. Azure App Service terminates
        // TLS at a reverse proxy, so TRUSTED_PROXIES=* can be used there to
        // honor X-Forwarded-* headers and generate HTTPS URLs correctly.
        $trustedProxies = trim(
            (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')
        );

        if ($trustedProxies === '*') {
            $middleware->trustProxies(at: '*');
        } else {
            $middleware->trustProxies(
                at: array_values(array_filter(array_map(
                    'trim',
                    explode(',', $trustedProxies),
                )))
            );
        }

        $middleware->alias([
            'admin' => AdminOnly::class,
            'staff' => StaffOnly::class,
            'adopter' => AdopterOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
