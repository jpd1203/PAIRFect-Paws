<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class VolunteerController extends Controller
{
    /**
     * GET /admin/volunteers — list all staff accounts (admin only)
     */
    public function index()
    {
        $staff = User::whereIn('role', [Role::Administrator->value, Role::Volunteer->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $volunteers = $staff;
        $branches = Branch::query()->orderBy('name')->get();

        return view('admin.volunteer.index', compact('staff', 'volunteers', 'branches'));
    }

    /**
     * GET /admin/volunteers/create — display form to create staff account
     */
    public function create()
    {
        $branches = Branch::all();

        return view('admin.volunteer.create', compact('branches'));
    }

    /**
     * POST /admin/volunteers — store new staff account
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|in:Administrator,Volunteer',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $isAdministrator = $validated['role'] === Role::Administrator->value;

        $staff = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'branch_id' => $validated['branch_id'],
            'is_active' => true, // Active by default
            'email_verified_at' => $isAdministrator ? now() : null,
        ]);

        event(new Registered($staff));

        return redirect()->route('admin.volunteers.index')
            ->with(
                'success',
                $isAdministrator
                    ? 'Administrator account created and verified. It can sign in immediately.'
                    : 'Volunteer account created. A verification email has been sent to the new volunteer.',
            );
    }

    /**
     * Update a volunteer profile and its active status. Administrator accounts
     * are deliberately outside this endpoint so a volunteer-management action
     * cannot demote or deactivate an administrator.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === Role::Volunteer, 404);

        $validated = $request->validate([
            'full_name' => [
                'sometimes',
                'required',
                'string',
                'max:511',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $parts = preg_split('/\s+/u', Str::squish((string) $value), 2);

                    if (! is_array($parts) || count($parts) < 2) {
                        $fail('Enter both a first name and a last name.');

                        return;
                    }

                    if (mb_strlen($parts[0]) > 255 || mb_strlen($parts[1]) > 255) {
                        $fail('The first name and last name may not exceed 255 characters each.');
                    }
                },
            ],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'role' => ['sometimes', 'required', Rule::in([Role::Volunteer->value])],
            'branch_id' => ['sometimes', 'nullable', 'exists:branches,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        [$firstName, $lastName] = $this->updatedName($user, $validated);
        $isActive = (bool) $validated['is_active'];
        $actorId = $request->user()?->id;

        DB::transaction(function () use (
            $user,
            $validated,
            $firstName,
            $lastName,
            $isActive,
            $actorId,
        ): void {
            $volunteer = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($volunteer->role === Role::Volunteer, 404);

            $wasActive = $volunteer->is_active;
            $updates = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'is_active' => $isActive,
            ];

            if (array_key_exists('branch_id', $validated)) {
                $updates['branch_id'] = $validated['branch_id'];
            }

            // Invalidates persistent-login cookies as well as server sessions.
            if (! $isActive) {
                $updates['remember_token'] = Str::random(60);
            }

            $volunteer->forceFill($updates)->save();

            if (! $isActive) {
                $this->revokeDatabaseSessions($volunteer);
            }

            $action = match (true) {
                $wasActive && ! $isActive => 'Volunteer Account Deactivated',
                ! $wasActive && $isActive => 'Volunteer Account Reactivated',
                default => 'Volunteer Profile Updated',
            };

            AuditLogService::log(
                $actorId,
                $action,
                'User',
                $volunteer->id,
                'Volunteer profile saved with status '.($isActive ? 'Active' : 'Inactive').'.',
            );
        });

        return redirect()->route('admin.volunteers.index')->with(
            'success',
            $isActive
                ? 'Volunteer profile updated and activated.'
                : 'Volunteer profile updated and deactivated. Existing sessions were revoked.',
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{string, string}
     */
    private function updatedName(User $user, array $validated): array
    {
        $firstName = $user->first_name;
        $lastName = $user->last_name;

        if (array_key_exists('full_name', $validated)) {
            /** @var array{0: string, 1: string} $parts */
            $parts = preg_split('/\s+/u', Str::squish($validated['full_name']), 2);
            [$firstName, $lastName] = $parts;
        }

        if (array_key_exists('first_name', $validated)) {
            $firstName = Str::squish($validated['first_name']);
        }

        if (array_key_exists('last_name', $validated)) {
            $lastName = Str::squish($validated['last_name']);
        }

        return [$firstName, $lastName];
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
