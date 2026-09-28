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

    public function showLogin(Request $request)
    {
        $redirectTo = $request->query('redirect');
        if (! $this->isValidRedirect($redirectTo)) {
            $redirectTo = session('url.intended');
        }

        if (Auth::check()) {
            if ($redirectTo && $this->isValidRedirect($redirectTo)) {
                return redirect()->to($redirectTo);
            }
            return $this->redirectByRole(Auth::user());
        }

        if ($redirectTo && $this->isValidRedirect($redirectTo)) {
            session(['url.intended' => $redirectTo]);
        }

        $intendedPet = null;
        if ($redirectTo && preg_match('#/apply/(\d+)#', $redirectTo, $matches)) {
            $intendedPet = \App\Models\Pet::find($matches[1]);
        }

        return view('auth.login', compact('intendedPet', 'redirectTo'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $throttleKey = strtolower($credentials['email']) . '|' . $request->ip();

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('email');
        }

        // Find user first to check is_active before Auth::attempt
        $user = User::where('email', $credentials['email'])->first();

        if ($user && ! $user->is_active) {
            return back()->withErrors([
                'email' => 'This account has been deactivated. Please contact an administrator.',
            ])->onlyInput('email');
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey);
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $redirectTo = $request->input('redirect');
        if (! $this->isValidRedirect($redirectTo)) {
            $redirectTo = session()->pull('url.intended');
        }

        if ($redirectTo && $this->isValidRedirect($redirectTo)) {
            if (Auth::user()->role !== Role::Adopter) {
                return redirect()->route('admin.dashboard');
            }
            return redirect()->to($redirectTo);
        }

        return redirect()->intended($this->routeForRole(Auth::user()));
    }

    // ─── Register ─────────────────────────────────────────────────────────────

    public function showRegister(Request $request)
    {
        return redirect()->route('login', array_merge(['tab' => 'register'], $request->query()));
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            ...PhilippineLocationService::validationRules(),
        ]);
        $address = $this->locations->resolveAddress($validated);

        $redirectTo = $request->input('redirect');
        if (! $this->isValidRedirect($redirectTo)) {
            $redirectTo = session()->pull('url.intended');
        }

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

        if (app()->environment('local')) {
            $user->markEmailAsVerified();

            if ($redirectTo && $this->isValidRedirect($redirectTo)) {
                return redirect()->to($redirectTo);
            }

            return redirect()->to($this->routeForRole($user));
        }

        if ($redirectTo && $this->isValidRedirect($redirectTo)) {
            session(['url.intended' => $redirectTo]);
        }

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

    private function isValidRedirect(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_starts_with($url, '/\\')) {
            return true;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $targetHost = parse_url($url, PHP_URL_HOST);

        return $targetHost !== null && in_array($targetHost, array_filter([$appHost, '127.0.0.1', 'localhost']), true);
    }
}

