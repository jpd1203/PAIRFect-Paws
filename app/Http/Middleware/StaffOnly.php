<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffOnly
{
    /**
     * Allow Administrators and Volunteers through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !in_array($request->user()->role, [Role::Administrator, Role::Volunteer])) {
            return redirect()->route('access-denied');
        }

        return $next($request);
    }
}
