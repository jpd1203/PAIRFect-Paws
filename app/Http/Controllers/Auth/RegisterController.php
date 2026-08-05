<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone_number' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'You must agree to the terms and conditions under RA 8485 and RA 10173.',
        ]);

        $user = User::create([
            'full_name' => trim("{$validated['first_name']} {$validated['last_name']}"),
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'password' => Hash::make($validated['password']),
            'role' => 'adopter',
            'is_active' => true,
        ]);

        Auth::login($user);

        return redirect()->route('animal.index')->with('toast', [
            'type' => 'success',
            'message' => 'Account created successfully! Welcome to PAIRfect Paws.',
        ]);
    }
}
