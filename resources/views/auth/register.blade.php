@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card mt-4 shadow">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="stat-icon bg-primary-subtle text-primary mx-auto mb-3"><i class="bi bi-person-plus"></i></div>
                    <h4 class="fw-bold mb-1">Create Patient Account</h4>
                    <p class="text-muted small mb-0">Register to book appointments online</p>
                </div>

                <form method="POST" action="{{ route('register.attempt') }}">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                                   value="{{ old('name') }}" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="contact_no" class="form-label">Contact number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('contact_no') is-invalid @enderror" id="contact_no"
                                   name="contact_no" value="{{ old('contact_no') }}" placeholder="09XX XXX XXXX" required>
                            <div class="form-text">Used for SMS appointment reminders.</div>
                            @error('contact_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                                   value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="age" class="form-label">Age</label>
                            <input type="number" min="1" max="120" class="form-control @error('age') is-invalid @enderror"
                                   id="age" name="age" value="{{ old('age') }}">
                            @error('age')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" class="form-control @error('address') is-invalid @enderror" id="address"
                               name="address" value="{{ old('address') }}">
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="medical_history" class="form-label">Medical history</label>
                        <textarea class="form-control @error('medical_history') is-invalid @enderror" id="medical_history"
                                  name="medical_history" rows="2"
                                  placeholder="Allergies, existing conditions, medications, etc.">{{ old('medical_history') }}</textarea>
                        @error('medical_history')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                                   name="password" required autocomplete="new-password">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="password_confirmation" class="form-label">Confirm password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-check me-1"></i>Create Account
                    </button>
                </form>

                <p class="text-center text-muted small mt-3 mb-0">
                    Already have an account? <a href="{{ route('login') }}" class="text-decoration-none">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
