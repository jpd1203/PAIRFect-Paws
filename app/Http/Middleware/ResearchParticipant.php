<?php

namespace App\Http\Middleware;

use App\Support\ResearchMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResearchParticipant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(ResearchMode::ready(), 404);
        abort_unless(ResearchMode::allowsAdopter($request->user()), 403);

        return $next($request);
    }
}
