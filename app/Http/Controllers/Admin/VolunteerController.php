<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class VolunteerController extends Controller
{
    public function index()
    {
        $volunteers = User::staff()->orderBy('full_name')->get();

        return view('admin.volunteer.index', compact('volunteers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['Admin', 'Volunteer'])],
            'password' => ['required', Password::min(10)],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = true;

        $volunteer = User::create($data);

        AuditLog::record(Auth::user(), "added {$volunteer->full_name} as a {$volunteer->role}");

        return back()->with('toast', ['type' => 'success', 'message' => "{$volunteer->full_name} added."]);
    }

    public function update(Request $request, User $volunteer)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['Admin', 'Volunteer'])],
            'is_active' => ['required', 'boolean'],
        ]);

        // Never let the last active admin deactivate/demote themselves into a lockout
        if ($volunteer->id === Auth::id() && (!$data['is_active'] || $data['role'] !== 'Admin')) {
            $otherActiveAdmins = User::staff()->where('role', 'Admin')->where('is_active', true)->where('id', '!=', $volunteer->id)->exists();
            if (!$otherActiveAdmins) {
                return back()->with('toast', ['type' => 'error', 'message' => 'You cannot remove the last active admin account.']);
            }
        }

        $volunteer->update($data);

        AuditLog::record(Auth::user(), "updated volunteer record for {$volunteer->full_name}");

        return back()->with('toast', ['type' => 'success', 'message' => "{$volunteer->full_name}'s record updated."]);
    }
}
