<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name', 'Dental Clinic') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🦷</text></svg>">

    {{-- Bootstrap 5 (CDN) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    {{-- DataTables (CDN) --}}
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6fb;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        /* ------------------------------------------------------------------
           Modern app navbar
           ------------------------------------------------------------------ */
        .navbar-app {
            background: linear-gradient(135deg, rgba(29, 40, 92, .97) 0%, rgba(45, 82, 214, .97) 100%);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 .5rem 1.5rem rgba(23, 37, 84, .16);
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .navbar-app .brand-tile {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: .7rem;
            font-size: 1.1rem;
        }

        .navbar-app .nav-link {
            color: rgba(255, 255, 255, .78);
            font-weight: 500;
            padding: .45rem 1rem;
            border-radius: 2rem;
            transition: color .15s ease, background-color .15s ease, box-shadow .15s ease;
        }

        .navbar-app .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, .12);
        }

        .navbar-app .nav-link.active {
            color: #1d285c;
            background: #fff;
            box-shadow: 0 .25rem .8rem rgba(15, 23, 42, .25);
        }

        .navbar-app .navbar-toggler {
            border-color: rgba(255, 255, 255, .35);
        }

        .navbar-app .navbar-toggler:focus {
            box-shadow: 0 0 0 .2rem rgba(255, 255, 255, .2);
        }

        .navbar-app .avatar {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #fff;
            color: #1d285c;
            font-size: .8rem;
            font-weight: 700;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, .25);
        }

        .navbar-app .role-badge {
            background: rgba(255, 255, 255, .16);
            color: #fff;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .03em;
            border: 1px solid rgba(255, 255, 255, .18);
        }

        .navbar-app .dropdown-menu {
            border: none;
            border-radius: .8rem;
            box-shadow: 0 .6rem 1.6rem rgba(15, 23, 42, .18);
        }

        .navbar-app .dropdown-item {
            border-radius: .5rem;
        }

        .navbar-app .dropdown-item:hover,
        .navbar-app .dropdown-item:focus {
            background-color: #eef2ff;
        }

        .card {
            border: none;
            border-radius: .9rem;
            box-shadow: 0 .25rem .9rem rgba(13, 110, 253, .08);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #eef1f6;
            font-weight: 600;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: .8rem;
            font-size: 1.35rem;
        }

        .table > :not(caption) > * > * { vertical-align: middle; }

        .badge-status { font-size: .78rem; font-weight: 600; letter-spacing: .02em; }

        .slot-btn {
            width: 100%;
            padding: .55rem .25rem;
            font-size: .85rem;
            border-radius: .55rem;
        }

        .toast-container { z-index: 1090; }

        /* ------------------------------------------------------------------
           Print-friendly report styles
           ------------------------------------------------------------------ */
        .print-header { display: none; }

        @media print {
            body { background: #fff !important; }
            .no-print, .navbar, footer, .toast-container { display: none !important; }
            .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
            .print-header { display: block; }
            .container { max-width: 100% !important; }
            a { text-decoration: none; color: #000 !important; }
            .table td, .table th { font-size: .8rem; padding: .35rem .5rem; }
        }
    </style>

    @stack('styles')
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-app sticky-top no-print">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
            <span class="brand-tile"><i class="bi bi-clipboard2-pulse"></i></span>
            <span>{{ config('app.name', 'Dental Clinic') }}</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            @auth
                <ul class="navbar-nav me-auto gap-lg-1">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>

                    @if(auth()->user()->isStaff())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('patients.*') ? 'active' : '' }}" href="{{ route('patients.index') }}">
                                <i class="bi bi-people me-1"></i>Patients
                            </a>
                        </li>
                    @endif

                    @if(in_array(auth()->user()->role, ['Owner', 'Secretary']))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('appointments.index') ? 'active' : '' }}" href="{{ route('appointments.index') }}">
                                <i class="bi bi-calendar2-week me-1"></i>Schedule
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('sms.*') ? 'active' : '' }}" href="{{ route('sms.index') }}">
                                <i class="bi bi-chat-left-dots me-1"></i>SMS Logs
                            </a>
                        </li>
                    @endif

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('appointments.book') ? 'active' : '' }}" href="{{ route('appointments.book') }}">
                            <i class="bi bi-calendar-plus me-1"></i>Book Appointment
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-lg-auto mt-2 mt-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar">{{ collect(explode(' ', auth()->user()->name))->slice(0, 2)->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))->join('') }}</span>
                            <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                            <span class="badge rounded-pill role-badge">{{ auth()->user()->role }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-lg-end">
                            <li><span class="dropdown-item-text small text-muted">{{ auth()->user()->email }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            @endauth

            @guest
                <ul class="navbar-nav ms-lg-auto gap-lg-2 mt-2 mt-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-light rounded-pill fw-semibold text-primary px-3 mt-1 mt-lg-0" href="{{ route('register') }}">
                            <i class="bi bi-person-plus me-1"></i>Register
                        </a>
                    </li>
                </ul>
            @endguest
        </div>
    </div>
</nav>

<main class="container py-4">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
            <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Please fix the following:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @yield('content')
</main>

<footer class="text-center text-muted small py-3 no-print">
    {{ config('app.name', 'Dental Clinic') }} · Online Records &amp; Appointment System · Capstone Project
</footer>

{{-- Toast container for AJAX feedback --}}
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

{{-- Bootstrap 5 bundle --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
{{-- jQuery + DataTables --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
    // ------------------------------------------------------------------
    // Global AJAX helpers (vanilla JS + fetch)
    // ------------------------------------------------------------------
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    /** Minimal fetch wrapper that sends JSON + CSRF and handles errors. */
    async function apiFetch(url, options = {}) {
        const opts = { ...options };
        opts.headers = { 'X-CSRF-TOKEN': csrfToken, ...(opts.headers || {}) };

        if (opts.method && opts.method !== 'GET' && !(opts.body instanceof FormData)) {
            opts.headers['Content-Type'] = 'application/json';
            if (opts.body && typeof opts.body !== 'string') {
                opts.body = JSON.stringify(opts.body);
            }
        }

        const res = await fetch(url, opts);
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            const detail = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : (data.message || 'Something went wrong.');
            const err = new Error(detail);
            err.status = res.status;
            throw err;
        }
        return data;
    }

    /** Bootstrap toast notification. */
    function showToast(message, type = 'success') {
        const colors = { success: 'text-bg-success', danger: 'text-bg-danger', warning: 'text-bg-warning', info: 'text-bg-info' };
        const icons = { success: 'bi-check-circle', danger: 'bi-x-circle', warning: 'bi-exclamation-triangle', info: 'bi-info-circle' };
        const el = document.createElement('div');
        el.className = `toast align-items-center border-0 ${colors[type] || colors.info}`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <div class="d-flex">
                <div class="toast-body"><i class="bi ${icons[type] || icons.info} me-2"></i>${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>`;
        document.getElementById('toastContainer').appendChild(el);
        const toast = new bootstrap.Toast(el, { delay: 4000 });
        toast.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    /** Confirm-then-act helper for destructive AJAX actions. */
    function confirmAction(message, action) {
        if (window.confirm(message)) {
            action();
        }
    }

    /** Serialize a form to a plain object (for JSON body). */
    function formToObject(form) {
        const data = {};
        new FormData(form).forEach((value, key) => { data[key] = value; });
        return data;
    }

    /** Common DataTables config for bootstrap5 styling. */
    const dtDefaults = {
        responsive: true,
        pageLength: 10,
        language: { search: '<i class="bi bi-search me-1"></i>' },
    };
</script>

@stack('scripts')
</body>
</html>
