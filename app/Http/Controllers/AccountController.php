<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Staff account management — Owner only.
 *
 * The Owner creates and manages Secretary / Dentist / other Owner accounts.
 * Accounts are deactivated instead of deleted so historical records stay intact.
 */
class AccountController extends Controller
{
    /** Roles the Owner may assign. Patients are created via public registration only. */
    public const ASSIGNABLE_ROLES = [
        User::ROLE_DENTIST,
        User::ROLE_SECRETARY,
        User::ROLE_OWNER,
    ];

    public function index(): View
    {
        return view('accounts.index');
    }

    public function data(): JsonResponse
    {
        $rows = User::whereIn('role', [User::ROLE_OWNER, User::ROLE_SECRETARY, User::ROLE_DENTIST])
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                // e() escapes stored user input before client-side rendering.
                'name' => e($u->name),
                'role' => $u->role,
                'email' => e($u->email),
                'contact_no' => e((string) $u->contact_no),
                'license_no' => e((string) $u->license_no),
                'duty_days' => $u->isDentist() ? $u->dutyDayLabel() : '—',
                'is_active' => $u->isActive(),
            ]);

        return response()->json(['data' => $rows->values()]);
    }

    public function create(): View
    {
        return view('accounts.form', ['account' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAccount($request);
        $data['duty_days'] = $this->normaliseDutyDays($data['duty_days'] ?? null);

        User::create([
            ...collect($data)->except(['password', 'password_confirmation'])->all(),
            'password' => $data['password'],
        ]);

        return redirect()->route('accounts.index')->with('success', "Account created for {$data['name']}.");
    }

    public function edit(User $user): View
    {
        abort_if($user->isPatient(), 404, 'Staff account not found.');

        return view('accounts.form', ['account' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isPatient(), 404, 'Staff account not found.');

        $data = $this->validateAccount($request, $user);
        $data['duty_days'] = $this->normaliseDutyDays($data['duty_days'] ?? null);

        $user->update(collect($data)->except(['password', 'password_confirmation'])->all());

        // Optional password reset.
        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        return redirect()->route('accounts.index')->with('success', "Account updated for {$user->name}.");
    }

    /**
     * Activate / deactivate a staff account.
     * An owner cannot deactivate their own account (would lock everyone out).
     */
    public function toggleActive(Request $request, User $user): JsonResponse
    {
        abort_if($user->isPatient(), 404, 'Staff account not found.');
        abort_unless((int) $user->id !== (int) auth()->id(), 403, 'You cannot deactivate your own account.');

        $user->update(['is_active' => ! $user->isActive()]);

        return response()->json([
            'message' => $user->isActive()
                ? "{$user->name} can now sign in again."
                : "{$user->name} has been deactivated and can no longer sign in.",
            'is_active' => $user->isActive(),
        ]);
    }

    /**
     * Shared validation for create/update.
     *
     * @return array<string, mixed>
     */
    protected function validateAccount(Request $request, ?User $existing = null): array
    {
        $emailRule = Rule::unique('users', 'email');
        if ($existing !== null) {
            $emailRule->ignore($existing->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $emailRule],
            'contact_no' => ['required', 'string', 'max:20'],
            'role' => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            'license_no' => [
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_DENTIST),
                'nullable', 'string', 'max:50',
            ],
            // Duty schedule: ISO weekdays the dentist works (see config/clinic.php).
            'duty_days' => [
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_DENTIST),
                'nullable', 'array',
            ],
            'duty_days.*' => ['integer', 'between:1,7'],
            'password' => $existing === null
                ? ['required', 'string', 'min:8', 'confirmed']
                : ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'license_no.required' => 'The license number is required for dentist accounts.',
            'duty_days.required' => 'Pick at least one duty day for a dentist account.',
            'password.confirmed' => 'The password confirmation does not match.',
            'email.unique' => 'An account with this email already exists.',
        ]);
    }

    /**
     * Weekday checkboxes come in as an array; the column stores "1,2,3,5".
     *
     * @param  array<int, mixed>|null  $days
     */
    protected function normaliseDutyDays(?array $days): ?string
    {
        if (empty($days)) {
            return null;
        }

        $days = array_values(array_unique(array_filter(
            array_map('intval', $days),
            fn (int $day) => $day >= 1 && $day <= 7
        )));

        sort($days);

        return $days ? implode(',', $days) : null;
    }
}
