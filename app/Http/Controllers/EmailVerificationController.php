<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->redirectByRole($request->user());
        }

        return view('auth.verify-email', ['user' => $request->user()]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = User::query()->findOrFail($request->route('id'));

        abort_unless(
            hash_equals(
                sha1($user->getEmailForVerification()),
                (string) $request->route('hash'),
            ),
            403,
        );

        abort_unless($user->is_active, 403, 'This account has been deactivated.');

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if (! $request->user()) {
            $message = 'Your email address has been verified. You can now sign in.';

            return redirect()->route('login')
                ->with('status', $message)
                ->with('success', $message);
        }

        return redirect()->intended($this->routeForRole($user))
            ->with('success', 'Your email address has been verified.');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->redirectByRole($request->user());
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    private function redirectByRole(User $user): RedirectResponse
    {
        return match ($user->role) {
            Role::Administrator, Role::Volunteer => redirect()->route('admin.dashboard'),
            Role::Adopter => redirect()->route('animal.index'),
        };
    }
}
