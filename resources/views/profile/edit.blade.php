@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex align-items-center gap-2 mb-4">
                <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-person-gear"></i></div>
                <div>
                    <h4 class="fw-bold mb-0">My Profile</h4>
                    <p class="text-muted small mb-0">Signed in as {{ $user->role }} · {{ $user->email }}</p>
                </div>
            </div>

            <div class="row g-4">
                {{-- Profile details --}}
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header"><i class="bi bi-person-vcard me-2 text-primary"></i>Profile Details</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('profile.update') }}">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label for="name" class="form-label">Full name</label>
                                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}"
                                           class="form-control @error('name') is-invalid @enderror" required>
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="mb-3">
                                    <label for="contact_no" class="form-label">Contact number</label>
                                    <input type="tel" name="contact_no" id="contact_no" value="{{ old('contact_no', $user->contact_no) }}"
                                           placeholder="0917 123 4567"
                                           class="form-control @error('contact_no') is-invalid @enderror" required>
                                    <div class="form-text">Used for SMS appointment notifications.</div>
                                    @error('contact_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                @if($user->isDentist())
                                    <div class="mb-0">
                                        <label for="license_no" class="form-label">PRC License No.</label>
                                        <input type="text" name="license_no" id="license_no" value="{{ old('license_no', $user->license_no) }}"
                                               placeholder="e.g. 0072145"
                                               class="form-control @error('license_no') is-invalid @enderror" required>
                                        <div class="form-text">Appears on prescriptions you issue. Keep this up to date.</div>
                                        @error('license_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                @endif

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="bi bi-check2 me-1"></i>Save Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Password change --}}
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header"><i class="bi bi-shield-lock me-2 text-primary"></i>Change Password</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('profile.password') }}">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current password</label>
                                    <input type="password" name="current_password" id="current_password"
                                           class="form-control @error('current_password') is-invalid @enderror" required autocomplete="current-password">
                                    @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New password</label>
                                    <input type="password" name="password" id="new_password" minlength="8"
                                           class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                                    <div class="form-text">At least 8 characters.</div>
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="mb-0">
                                    <label for="new_password_confirmation" class="form-label">Confirm new password</label>
                                    <input type="password" name="password_confirmation" id="new_password_confirmation"
                                           class="form-control" required autocomplete="new-password">
                                </div>

                                <button type="submit" class="btn btn-outline-secondary mt-3">
                                    <i class="bi bi-key me-1"></i>Update Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
