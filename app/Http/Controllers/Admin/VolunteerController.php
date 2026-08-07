<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class VolunteerController extends Controller
{
    /**
     * GET /admin/volunteers — list all staff accounts (admin only)
     */
    public function index()
    {
        $staff = User::whereIn('role', [Role::Administrator->value, Role::Volunteer->value])
            ->orderBy('role')
            ->orderBy('last_name')
            ->get();

        return view('admin.volunteer.index', compact('staff'));
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
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'password'   => ['required', 'confirmed', Password::defaults()],
            'role'       => 'required|in:Administrator,Volunteer',
            'branch_id'  => 'nullable|exists:branches,id',
        ]);

        User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'role'       => $validated['role'],
            'branch_id'  => $validated['branch_id'],
            'is_active'  => true, // Active by default
        ]);

        return redirect()->route('admin.volunteers.index')
            ->with('success', 'Staff account created successfully.');
    }
}
