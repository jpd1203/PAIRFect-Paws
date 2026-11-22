<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;

final class ResearchAwareAdopterVerification
{
    public function handle(Request $request, Closure $next)
    {
        return app(EnsureEmailIsVerified::class)->handle($request, $next);
    }
}
