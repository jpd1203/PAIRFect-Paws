<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PhilippineLocationService;
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
    public function __construct(private readonly PhilippineLocationService $locations) {}

    public function show(Request $request): View
    {
        return view('account.settings', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $rules = [
            'full_name' => ['required', 'string', 'max:255', 'regex:/^\S+(?:\s+\S+)+$/u'],
        ];

        if ($user->isAdopter()) {
            $rules['phone_number'] = ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9 ()-]{7,50}$/'];
            $rules = array_merge($rules, PhilippineLocationService::validationRules());
        }

        $validated = $request->validate($rules);
        $fullName = preg_replace('/\s+/u', ' ', trim($validated['full_name']));
        $address = $user->isAdopter() ? $this->locations->resolveAddress($validated) : [];

        DB::transaction(function () use ($user, $validated, $fullName, $address): void {
            if ($fullName !== $user->full_name) {
                $parts = explode(' ', $fullName);
                $user->last_name = array_pop($parts);
                $user->first_name = implode(' ', $parts);
            }

            if ($user->isAdopter()) {
                $user->phone_number = $validated['phone_number'] ?? null;
                $user->fill($address);
            }

            $user->saveOrFail();
        });

        return redirect()->route('account.settings')->with('success', 'Profile updated.');
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
