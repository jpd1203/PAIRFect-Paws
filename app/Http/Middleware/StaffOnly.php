<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StaffOnly
{
    /**
     * Allow Administrators and Volunteers through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Database sessions are removed at deactivation time when supported.
        // This fallback also terminates file/Redis/cookie-backed sessions on
        // the volunteer's very next attempt to use a protected staff route.
        if ($user?->role === Role::Volunteer && ! $user->is_active) {
            Auth::guard()->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been deactivated. Please contact an administrator.',
            ]);
        }

        if (
            ! $user
            || ! $user->is_active
            || ! in_array($user->role, [Role::Administrator, Role::Volunteer], true)
        ) {
            return redirect()->route('access-denied');
        }

        return $next($request);
    }
}
