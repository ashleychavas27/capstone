@extends('layouts.app')

@section('title', $account ? 'Edit Staff Account' : 'New Staff Account')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
        <div>
            <h4 class="fw-bold mb-0">{{ $account ? 'Edit Staff Account' : 'New Staff Account' }}</h4>
            <p class="text-muted small mb-0">{{ $account ? 'Update login details for '.$account->name : 'Only the Owner can create staff accounts' }}</p>
        </div>
        <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Accounts
        </a>
    </div>

    <form method="POST"
          action="{{ $account ? route('accounts.update', $account) : route('accounts.store') }}"
          id="accountForm">
        @csrf
        @if($account)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-person-badge me-2 text-primary"></i>1. Account Details</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name', $account?->name) }}"
                                   class="form-control @error('name') is-invalid @enderror" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email address (used to sign in) <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" value="{{ old('email', $account?->email) }}"
                                   class="form-control @error('email') is-invalid @enderror" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="contact_no" class="form-label">Contact number <span class="text-danger">*</span></label>
                            <input type="tel" name="contact_no" id="contact_no" value="{{ old('contact_no', $account?->contact_no) }}"
                                   placeholder="0917 123 4567"
                                   class="form-control @error('contact_no') is-invalid @enderror" required>
                            @error('contact_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-0">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" id="role" class="form-select @error('role') is-invalid @enderror" required>
                                <option value="">Select role…</option>
                                @foreach(\App\Http\Controllers\AccountController::ASSIGNABLE_ROLES as $role)
                                    <option value="{{ $role }}" @selected(old('role', $account?->role) === $role)>
                                        {{ $role }}
                                        @if($role === 'Dentist') — can view patients, calendar &amp; issue prescriptions
                                        @elseif($role === 'Secretary') — manages schedule &amp; SMS notifications
                                        @elseif($role === 'Owner') — full access including accounts
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-shield-lock me-2 text-primary"></i>2. Credentials</div>
                    <div class="card-body">
                        {{-- Dentist license number --}}
                        <div class="mb-3 d-none" id="licenseField">
                            <label for="license_no" class="form-label">PRC License No. <span class="text-danger">*</span></label>
                            <input type="text" name="license_no" id="license_no" value="{{ old('license_no', $account?->license_no) }}"
                                   placeholder="e.g. 0072145"
                                   class="form-control @error('license_no') is-invalid @enderror">
                            <div class="form-text">Appears on printed prescriptions (reseta). The dentist can also update this from My Profile.</div>
                            @error('license_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">
                                Password @unless($account)<span class="text-danger">*</span>@endelse<span class="text-muted small">(leave blank to keep current)</span>@endunless
                            </label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" minlength="8"
                                       {{ $account ? '' : 'required' }} autocomplete="new-password"
                                       class="form-control @error('password') is-invalid @enderror">
                                <button type="button" class="btn btn-outline-secondary js-toggle-password"
                                        data-target="password" aria-label="Show or hide password" title="Show / hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text">At least 8 characters.</div>
                        </div>

                        <div class="mb-0">
                            <label for="password_confirmation" class="form-label">Confirm password @unless($account)<span class="text-danger">*</span>@endunless</label>
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                   {{ $account ? '' : 'required' }} autocomplete="new-password"
                                   class="form-control @error('password') is-invalid @enderror">
                        </div>

                        @if($account)
                            <div class="alert alert-light border small mt-3 mb-0">
                                <i class="bi bi-info-circle me-1"></i>Status:
                                <strong>{{ $account->isActive() ? 'Active' : 'Deactivated' }}</strong> — toggle it from the Accounts list.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 no-print">
            <a href="{{ route('accounts.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2 me-1"></i>{{ $account ? 'Save Changes' : 'Create Account' }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    $(function () {
        // Show license field only when the selected role is Dentist.
        const roleSelect = document.getElementById('role');
        const licenseField = document.getElementById('licenseField');

        function syncLicense() {
            const isDentist = roleSelect.value === 'Dentist';
            licenseField.classList.toggle('d-none', !isDentist);
            document.getElementById('license_no').required = isDentist;
        }

        roleSelect.addEventListener('change', () => { syncLicense(); });
        syncLicense();
        // Re-check after server-side validation errors re-render with old input.
        if ("{{ old('role') }}") syncLicense();

        // Show / hide password toggle.
        document.querySelectorAll('.js-toggle-password').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.querySelector('i').classList.toggle('bi-eye', !show);
                btn.querySelector('i').classList.toggle('bi-eye-slash', show);
            });
        });

        // On validation errors, scroll to and focus the first problem field.
        const firstError = document.querySelector('#accountForm .is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus({ preventScroll: true });
        }
    });
</script>
@endpush
