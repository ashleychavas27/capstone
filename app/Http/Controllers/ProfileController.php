<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Self-service profile: any signed-in user can update their own contact
 * details and password. Dentists can additionally maintain their own
 * license number; Owner/Secretary manage other dentists' licenses via
 * the Accounts module.
 */
class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', ['user' => Auth::user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_no' => ['required', 'string', 'max:20'],
            'license_no' => [
                Rule::requiredIf($user->isDentist()),
                'nullable', 'string', 'max:50',
            ],
        ], [
            'license_no.required' => 'Please keep your PRC license number up to date.',
        ]);

        $user->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        Auth::user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Password changed successfully.');
    }
}
