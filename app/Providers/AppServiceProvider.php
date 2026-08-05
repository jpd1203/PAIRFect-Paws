<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Brute-force login protection: 5 attempts/minute, keyed by email+IP
        RateLimiter::for('login', function ($request) {
            $key = strtolower((string) $request->input('email')).'|'.$request->ip();
            return Limit::perMinute(5)->by($key)->response(function () {
                return response('Too many login attempts. Please try again in a minute.', 429);
            });
        });

        // Prevent scripted spam of post-adoption reports
        RateLimiter::for('report-submit', function ($request) {
            return Limit::perMinute(6)->by($request->user()?->id ?: $request->ip());
        });

        // Force HTTPS URLs when generating links behind a reverse proxy in production
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
