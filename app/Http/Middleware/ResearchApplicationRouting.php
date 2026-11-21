<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResearchApplicationRouting
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('research.enabled')) {
            if ($request->routeIs('application.index', 'applications.mine', 'monitoring.index', 'monitoring.my-checkins', 'monitoring.submit-report')) {
                return to_route('research.index');
            }
            if ($request->routeIs('application.apply', 'applications.create')) {
                return to_route('research.preview', $request->route('pet'));
            }
            abort_if($request->routeIs('application.submit', 'applications.store'), 403, 'Formal applications are disabled in research mode. Use the simulated application form.');
        }

        return $next($request);
    }
}
