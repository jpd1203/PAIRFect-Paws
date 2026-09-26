<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.settings', ['user' => $request->user()]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Password::defaults(),
            ],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        $this->revokeOtherDatabaseSessions($request, $user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        AuditLogService::log(
            $user->id,
            'Account Password Changed',
            'User',
            $user->id,
            'Password changed by the account owner; the persistent login token was rotated.',
        );

        $message = 'Your password has been updated successfully.';

        return redirect()->route('account.settings')
            ->with('success', $message)
            ->with('toast', [
                'type' => 'success',
                'message' => $message,
            ]);
    }

    private function revokeOtherDatabaseSessions(Request $request, User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');
        if ($table === '' || ! Schema::hasTable($table)) {
            return;
        }

        $sessions = DB::table($table)
            ->where('user_id', $user->getAuthIdentifier());
        $currentSessionId = $request->session()->getId();

        if ($currentSessionId !== '') {
            /** @var Builder $sessions */
            $sessions->where('id', '!=', $currentSessionId);
        }

        $sessions->delete();
    }
}
