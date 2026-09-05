<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Services\PhilippineLocationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private PhilippineLocationService $locations) {}

    // ─── Login ───────────────────────────────────────────────────────────────

    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Find user first to check is_active before Auth::attempt
        $user = User::where('email', $credentials['email'])->first();

        if ($user && ! $user->is_active) {
            return back()->withErrors([
                'email' => 'This account has been deactivated. Please contact an administrator.',
            ])->onlyInput('email');
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended($this->routeForRole(Auth::user()));
    }

    // ─── Register ─────────────────────────────────────────────────────────────

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            ...PhilippineLocationService::validationRules(),
        ]);
        $address = $this->locations->resolveAddress($validated);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => Role::Adopter->value,
            'is_active' => true,
            ...$address,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        event(new Registered($user));

        return redirect()->route('verification.notice');
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ─── Access Denied ────────────────────────────────────────────────────────

    public function accessDenied()
    {
        return view('auth.access-denied');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function redirectByRole(User $user)
    {
        return redirect()->to($this->routeForRole($user));
    }

    private function routeForRole(User $user): string
    {
        return match ($user->role) {
            Role::Administrator, Role::Volunteer => route('admin.dashboard'),
            Role::Adopter => route('animal.index'),
        };
    }
}
