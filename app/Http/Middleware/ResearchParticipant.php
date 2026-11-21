<?php

namespace App\Http\Middleware;

use App\Support\ResearchMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class ResearchParticipant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(ResearchMode::ready(), 404);
        abort_unless(DB::table('research_participants')->where('user_id', $request->user()->id)->exists(), 403, 'This research scenario is limited to enrolled survey participants.');

        return $next($request);
    }
}
