<?php

namespace App\Http\Middleware;

use App\Support\ResearchMode;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;

final class ResearchAwareAdopterVerification
{
    public function handle(Request $request, Closure $next)
    {
        if (ResearchMode::allowsAdopter($request->user())) {
            return $next($request);
        }

        return app(EnsureEmailIsVerified::class)->handle($request, $next);
    }
}
