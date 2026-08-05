<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the /admin panel to staff accounts (Admin or Volunteer role).
 * An authenticated adopter hitting an admin route gets a 403, not a redirect
 * to the admin login (which would leak that the route exists) — but since
 * they're already authenticated we show a clear "not authorized" page
 * rather than silently redirecting, which would be confusing.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (!$user || !in_array($user->role, ['Admin', 'Volunteer'], true)) {
            abort(403, 'You are not authorized to access the admin panel.');
        }

        if (($user->is_active ?? true) === false) {
            Auth::guard('web')->logout();
            abort(403, 'Your staff account has been deactivated. Contact an administrator.');
        }

        return $next($request);
    }
}
