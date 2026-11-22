<?php

namespace App\Http\Middleware;

use App\Support\ResearchMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProtectResearchAdminWorkflow
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ResearchMode::ready()
            && $request->routeIs(
                'admin.applications.*', 'admin.handover.*', 'admin.monitoring.*', 'admin.post-adoption-demo.*',
                'admin.adopter-profiles.document', 'admin.adoption-profiles.document', 'handover.receipt-proof',
            )
            && ! $request->routeIs(
                'admin.applications.index', 'admin.applications.export', 'admin.handover.index',
                'admin.handover.show', 'admin.monitoring.index', 'admin.monitoring.flagged',
            )) {
            // Numeric IDs can overlap the normal tables; never resolve research actions through them.
            abort(404);
        }

        return $next($request);
    }
}
