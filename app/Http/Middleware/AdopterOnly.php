<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdopterOnly
{
    /**
     * Allow only Adopters through.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->role !== Role::Adopter) {
            return redirect()->route('access-denied');
        }

        return $next($request);
    }
}
