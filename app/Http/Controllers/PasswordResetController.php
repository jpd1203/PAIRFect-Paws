<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    private const LINK_REQUESTED_MESSAGE = 'If an active account exists for that email address, a password reset link has been sent.';

    private const INVALID_LINK_MESSAGE = 'This password reset link is invalid, expired, or no longer available.';

    public function showRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $email = $this->validatedEmail($request);

        $this->requestResetLink($email);

        return back()->with('status', self::LINK_REQUESTED_MESSAGE);
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $this->validatedReset($request);
        $status = $this->resetPassword($validated);

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => self::INVALID_LINK_MESSAGE]);
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with(
            'status',
            'Your password has been reset. You can now sign in with your new password.',
        );
    }

    private function validatedEmail(Request $request): string
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        return $validated['email'];
    }

    /** @return array{email: string, is_active: bool, token: string, password: string} */
    private function validatedReset(Request $request): array
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        return [
            ...$validated,
            'is_active' => true,
        ];
    }

    private function requestResetLink(string $email): void
    {
        Password::broker()->sendResetLink([
            'email' => $email,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array{email: string, is_active: bool, token: string, password: string}  $credentials
     */
    private function resetPassword(array $credentials): string
    {
        return Password::broker()->reset(
            $credentials,
            function (User $user, #[\SensitiveParameter] string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $this->revokeDatabaseSessions($user);

                event(new PasswordReset($user));
            },
        );
    }

    private function revokeDatabaseSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');
        if ($table === '' || ! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->where('user_id', $user->getAuthIdentifier())->delete();
    }
}
