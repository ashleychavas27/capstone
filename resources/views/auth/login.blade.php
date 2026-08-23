@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card mt-5 shadow">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="{{ asset('logo.png') }}" alt="Logo" class="stat-icon bg-primary-subtle mx-auto mb-3 rounded-circle" style="object-fit: cover;">
                    <h4 class="fw-bold mb-1">{{ config('app.name', 'Dental Clinic') }}</h4>
                    <p class="text-muted small mb-0">Sign in to your account</p>
                </div>

                <form method="POST" action="{{ route('login.attempt') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                               value="{{ old('email') }}" required autofocus autocomplete="email">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                                   name="password" required autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary js-toggle-password"
                                    data-target="password" aria-label="Show or hide password" title="Show / hide password">
                                <i class="bi bi-eye"></i>
                            </button>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                    </button>
                </form>

                <p class="text-center text-muted small mt-3 mb-0">
                    Don't have an account? <a href="{{ route('register') }}" class="text-decoration-none">Register here</a>
                </p>
            </div>
        </div>

        @if(config('app.debug'))
            <div class="alert alert-light small mt-3 border no-print">
                <strong>Demo accounts</strong> (password: <code>password</code>):<br>
                Owner: owner@clinic.test<br>
                Secretary: secretary@clinic.test<br>
                Dentist: dentist1@clinic.test<br>
                Patient: ana.villanueva@clinic.test
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
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
</script>
@endpush
