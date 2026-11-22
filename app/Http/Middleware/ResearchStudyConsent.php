<?php

namespace App\Http\Middleware;

use App\Support\ResearchMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResearchStudyConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ResearchMode::ready()) {
            return $next($request);
        }
        $user = $request->user();
        if (! $user) {
            return to_route('login');
        }
        abort_if($user->isAdopter() && ! $user->is_active, 403);
        if ($user->isAdopter() && ! ResearchMode::hasConsent($user)) {
            return to_route('study.intro');
        }

        return $next($request);
    }
}
