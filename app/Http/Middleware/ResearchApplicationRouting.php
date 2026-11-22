<?php

namespace App\Http\Middleware;

use App\Support\ResearchMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResearchApplicationRouting
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ResearchMode::ready()) {
            if ($request->routeIs('monitoring.index', 'monitoring.my-checkins', 'monitoring.submit-report')) {
                return to_route('research.index', ['section' => 'check-ins']);
            }
            if ($request->routeIs('application.index', 'applications.mine')) {
                return to_route('research.index');
            }
            if ($request->routeIs('application.apply', 'applications.create')) {
                return to_route('research.preview', $request->route('pet'));
            }
            abort_unless($request->routeIs('research.*'), 403, 'Only simulated applications and check-ins are available in research mode.');
        }

        return $next($request);
    }
}
