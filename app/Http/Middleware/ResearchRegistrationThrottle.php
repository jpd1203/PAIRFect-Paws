<?php

namespace App\Http\Middleware;

use App\Support\ResearchMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

final class ResearchRegistrationThrottle
{
    public function handle(Request $request, Closure $next)
    {
        return ResearchMode::ready() ? app(ThrottleRequests::class)->handle($request, $next, 10, 1) : $next($request);
    }
}
