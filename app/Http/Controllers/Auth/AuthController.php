<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Please enter your email address.',
            'password.required' => 'Please enter your password.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'The provided credentials do not match our records.'])
                ->onlyInput('email');
        }

        // Deactivated staff accounts may no longer sign in.
        if (! Auth::user()->isActive()) {
            Auth::logout();
            $request->session()->invalidate();

            return back()
                ->withErrors(['email' => 'This account has been deactivated. Please contact the clinic owner.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Welcome back, '.Auth::user()->name.'!');
    }

    public function showRegisterForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'contact_no' => ['required', 'string', 'max:20'],
            'age' => ['nullable', 'integer', 'between:1,120'],
            'address' => ['nullable', 'string', 'max:500'],
            'medical_history' => ['nullable', 'string', 'max:2000'],
        ], [
            'email.unique' => 'An account with this email already exists.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        // Public registrations always create Patient accounts.
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'contact_no' => $data['contact_no'],
            'role' => User::ROLE_PATIENT,
        ]);

        PatientProfile::create([
            'user_id' => $user->id,
            'age' => $data['age'] ?? null,
            'address' => $data['address'] ?? null,
            'medical_history' => $data['medical_history'] ?? null,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', 'Account created successfully. Welcome, '.$user->name.'!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
