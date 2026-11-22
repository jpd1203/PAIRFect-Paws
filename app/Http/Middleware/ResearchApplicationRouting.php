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
            if ($request->routeIs('monitoring.submit-report')) {
                return to_route('monitoring.my-checkins');
            }
            if ($request->routeIs('applications.mine')) {
                return to_route('application.index');
            }
            if ($request->routeIs('application.apply', 'applications.create')) {
                return to_route('research.preview', $request->route('pet'));
            }
            abort_unless(
                $request->routeIs(
                    'research.*',
                    'application.index',
                    'monitoring.index',
                    'monitoring.my-checkins',
                    'adopter.handover.my',
                ),
                403,
            );
        }

        return $next($request);
    }
}
