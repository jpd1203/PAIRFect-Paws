<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerificationLinkMatchesUser
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! hash_equals((string) $user->getKey(), (string) $request->route('id'))) {
            return redirect()->route('verification.notice')->with(
                'verification_error',
                "This verification link belongs to a different account. You are currently signed in as {$user->email}. Log out, sign in with the email address that received the link, and open it again."
            );
        }

        return $next($request);
    }
}
