<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name', 'Dental Clinic') }}</title>
    <link rel="icon" href="{{ asset('logo.png') }}">

    {{-- Fonts: Playfair Display (headings) + Inter (body) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    {{-- Bootstrap 5 (CDN) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    {{-- DataTables (CDN) --}}
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

    <style>
        /* ------------------------------------------------------------------
           Clinic palette
           ------------------------------------------------------------------ */
        :root {
            --gold: #C89B27;        /* primary accent — CTAs, active nav */
            --gold-dark: #AE8520;
            --peach: #D1987F;       /* secondary accent — soft blocks */
            --peach-dark: #BC8068;
            --taupe: #736261;       /* headings & body text */
            --silver: #B4B1B2;      /* borders & dividers */
            --bg-alt: #FAF7F5;      /* warm off-white section background */
        }

        body {
            background-color: var(--bg-alt);
            color: var(--taupe);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        h1, h2, h3, h4, h5, h6, .card-header, .navbar-brand {
            font-family: 'Playfair Display', Georgia, serif;
            color: var(--taupe);
        }

        /* ------------------------------------------------------------------
           Navbar — plain white, warm accents
           ------------------------------------------------------------------ */
        .navbar-app {
            background: #fff;
            border-bottom: 1px solid var(--silver);
        }

        .navbar-app .brand-tile {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: 2px solid var(--gold);
            border-radius: 50%;
            padding: 1px;
            box-shadow: 0 2px 6px rgba(200, 155, 39, 0.18);
        }

        .navbar-app .brand-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.1;
            color: var(--taupe);
            letter-spacing: -0.01em;
        }

        .navbar-app .brand-subtitle {
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--gold-dark);
            line-height: 1;
        }

        .navbar-app .nav-link {
            color: var(--taupe);
            font-weight: 500;
            padding: .45rem 1rem;
            border-radius: 2rem;
        }

        .navbar-app .nav-link:hover {
            color: var(--gold-dark);
            background-color: var(--bg-alt);
        }

        .navbar-app .nav-link.active {
            color: #fff;
            background-color: var(--gold);
        }

        .navbar-app .navbar-toggler {
            border-color: var(--silver);
        }

        .navbar-app .navbar-toggler:focus {
            box-shadow: 0 0 0 .2rem rgba(200, 155, 39, .25);
        }

        .navbar-app .avatar {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--gold);
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: .8rem;
            font-weight: 700;
        }

        .navbar-app .role-badge {
            background: var(--bg-alt);
            color: var(--taupe);
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .03em;
            border: 1px solid var(--silver);
            font-family: 'Inter', sans-serif;
        }

        .navbar-app .dropdown-menu {
            border: 1px solid var(--silver);
            border-radius: .8rem;
        }

        .navbar-app .dropdown-item {
            border-radius: .5rem;
        }

        .navbar-app .dropdown-item:hover,
        .navbar-app .dropdown-item:focus {
            background-color: var(--bg-alt);
            color: var(--taupe);
        }

        /* ------------------------------------------------------------------
           Cards, tables, forms
           ------------------------------------------------------------------ */
        .card {
            border: 1px solid var(--silver);
            border-radius: .9rem;
            box-shadow: none;
            background: #fff;
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid var(--silver);
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

        .table-light { --bs-table-bg: var(--bg-alt); }

        input, select, textarea, .form-control, .form-select, input[type="date"] {
            font-family: 'Inter', system-ui, -apple-system, sans-serif !important;
            font-variant-numeric: tabular-nums;
            border-color: var(--silver);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 .2rem rgba(200, 155, 39, .15);
        }

        /* ------------------------------------------------------------------
           Buttons — gold primary, peach secondary
           ------------------------------------------------------------------ */
        .btn {
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            --bs-btn-bg: var(--gold);
            --bs-btn-border-color: var(--gold);
            --bs-btn-hover-bg: var(--gold-dark);
            --bs-btn-hover-border-color: var(--gold-dark);
            --bs-btn-active-bg: #96731B;
            --bs-btn-active-border-color: #96731B;
            --bs-btn-disabled-bg: var(--gold);
            --bs-btn-disabled-border-color: var(--gold);
        }

        .btn-outline-primary {
            --bs-btn-color: var(--gold-dark);
            --bs-btn-border-color: var(--gold);
            --bs-btn-hover-bg: var(--gold);
            --bs-btn-hover-border-color: var(--gold);
            --bs-btn-hover-color: #fff;
            --bs-btn-active-bg: var(--gold-dark);
            --bs-btn-active-border-color: var(--gold-dark);
        }

        .btn-secondary {
            --bs-btn-bg: var(--peach);
            --bs-btn-border-color: var(--peach);
            --bs-btn-hover-bg: var(--peach-dark);
            --bs-btn-hover-border-color: var(--peach-dark);
            --bs-btn-active-bg: #AA7158;
            --bs-btn-active-border-color: #AA7158;
            --bs-btn-disabled-bg: var(--peach);
            --bs-btn-disabled-border-color: var(--peach);
        }

        .btn-outline-secondary {
            --bs-btn-color: var(--taupe);
            --bs-btn-border-color: var(--silver);
            --bs-btn-hover-bg: var(--bg-alt);
            --bs-btn-hover-border-color: var(--silver);
            --bs-btn-hover-color: var(--taupe);
            --bs-btn-active-bg: var(--silver);
            --bs-btn-active-border-color: var(--silver);
        }

        /* Icons / labels that use Bootstrap primary text tone. */
        .text-primary { color: var(--gold) !important; }

        .text-muted { color: #9A8F8E !important; }

        a:not(.btn):not(.nav-link):not(.dropdown-item) { color: var(--gold-dark); }

        /* Status badges in the clinic palette */
        .badge-status { font-size: .78rem; font-weight: 600; letter-spacing: .02em; }

        .badge-status-pending   { background: var(--peach); color: #fff; }
        .badge-status-confirmed { background: var(--gold); color: #fff; }
        .badge-status-completed { background: var(--silver); color: #fff; }
        .badge-status-cancelled { background: #9A8F8E; color: #fff; }

        .slot-btn {
            width: 100%;
            padding: .55rem .25rem;
            font-size: .85rem;
            border-radius: .55rem;
        }

        .toast-container { z-index: 1090; }

        /* Visible keyboard focus everywhere (accessibility basic). */
        a:focus-visible,
        button:focus-visible,
        .form-control:focus-visible,
        .form-select:focus-visible {
            outline: 2px solid var(--gold);
            outline-offset: 2px;
            border-radius: .25rem;
        }

        /* Clickable table rows */
        tr.row-click { cursor: pointer; }
        tr.row-click:hover { background-color: var(--bg-alt); }

        /* Icon-only buttons get a tooltip via title; keep them subtle. */
        .btn-icon { line-height: 1; }

        /* ------------------------------------------------------------------
           Chat bot widget
           ------------------------------------------------------------------ */
        .chat-fab {
            position: fixed;
            right: 1.5rem;
            bottom: 1.5rem;
            z-index: 1080;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: none;
            background: var(--gold);
            color: #fff;
            font-size: 1.35rem;
            box-shadow: 0 4px 14px rgba(200, 155, 39, .35);
            transition: transform 0.15s ease, background 0.15s ease;
        }

        .chat-fab:hover { background: var(--gold-dark); color: #fff; transform: scale(1.05); }

        .chat-panel {
            position: fixed;
            right: 1.5rem;
            bottom: 5.25rem;
            z-index: 1080;
            width: min(360px, calc(100vw - 2.5rem));
            height: 440px;
            display: none;
            flex-direction: column;
            background: #fff;
            border: 1px solid var(--silver);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }

        .chat-panel.open { display: flex; }

        .chat-panel .chat-header {
            background: var(--gold);
            color: #fff;
            padding: .75rem 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
            font-weight: 600;
            font-family: 'Playfair Display', Georgia, serif;
        }

        .chat-panel .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: var(--bg-alt);
        }

        .chat-msg {
            max-width: 85%;
            padding: .5rem .75rem;
            border-radius: .8rem;
            margin-bottom: .5rem;
            font-size: .85rem;
            line-height: 1.35;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .chat-msg.user {
            margin-left: auto;
            background: var(--peach);
            color: #fff;
            border-bottom-right-radius: .2rem;
        }

        .chat-msg.bot {
            background: #fff;
            border: 1px solid var(--silver);
            border-bottom-left-radius: .2rem;
        }

        .chat-panel form {
            display: flex;
            gap: .5rem;
            padding: .75rem;
            border-top: 1px solid var(--silver);
            background: #fff;
        }

        /* ------------------------------------------------------------------
           Print-friendly report styles
           ------------------------------------------------------------------ */
        .print-header { display: none; }

        @media print {
            body { background: #fff !important; }
            .no-print, .navbar, footer, .toast-container, .chat-fab, .chat-panel { display: none !important; }
            .card { box-shadow: none !important; border: 1px solid var(--silver) !important; }
            .print-header { display: block; }
            .container { max-width: 100% !important; }
            a { text-decoration: none; color: #000 !important; }
            .table td, .table th { font-size: .8rem; padding: .35rem .5rem; }
        }
    </style>

    @stack('styles')
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-app sticky-top no-print py-2">
    <div class="container-fluid px-3 px-xl-5">
        <a class="navbar-brand d-flex align-items-center gap-2 py-0" href="{{ route('dashboard') }}">
            <img src="{{ asset('logo.png') }}" alt="Logo" class="brand-tile rounded-circle" style="object-fit: cover;">
            <div class="d-flex flex-column">
                <span class="brand-title">Datorin-Dajay</span>
                <span class="brand-subtitle">Dental Clinic · Dingle</span>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            @auth
                <ul class="navbar-nav mx-auto gap-lg-1 mt-2 mt-lg-0 align-items-lg-center">
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
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('appointments.calendar') ? 'active' : '' }}" href="{{ route('appointments.calendar') }}">
                                <i class="bi bi-calendar3 me-1"></i>Calendar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('prescriptions.*') ? 'active' : '' }}" href="{{ route('prescriptions.index') }}">
                                <i class="bi bi-file-earmark-text me-1"></i>e-Prescription
                            </a>
                        </li>
                    @endif

                    @if(auth()->user()->isOwner())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}" href="{{ route('accounts.index') }}">
                                <i class="bi bi-person-badge me-1"></i>Accounts
                            </a>
                        </li>
                    @endif

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('appointments.book') ? 'active' : '' }}" href="{{ route('appointments.book') }}">
                            <i class="bi bi-calendar-plus me-1"></i>Book Appointment
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-lg-0 mt-2 mt-lg-0 align-items-lg-center">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar">{{ collect(explode(' ', auth()->user()->name))->slice(0, 2)->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))->join('') }}</span>
                            <span class="d-none d-sm-inline fw-medium">{{ auth()->user()->name }}</span>
                            <span class="badge rounded-pill role-badge">{{ auth()->user()->role }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 220px;">
                            <li class="px-3 py-2 bg-light rounded-top">
                                <div class="small fw-semibold text-dark">{{ auth()->user()->name }}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">{{ auth()->user()->email }}</div>
                            </li>

                            @if(in_array(auth()->user()->role, ['Owner', 'Secretary']))
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><h6 class="dropdown-header text-uppercase text-muted fw-bold" style="font-size: 0.68rem; letter-spacing: 0.06em;">Administration</h6></li>
                                <li>
                                    <a class="dropdown-item py-2 {{ request()->routeIs('appointments.index') ? 'active' : '' }}" href="{{ route('appointments.index') }}">
                                        <i class="bi bi-calendar2-week me-2 text-primary"></i>All Schedule & List
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 {{ request()->routeIs('sms.*') ? 'active' : '' }}" href="{{ route('sms.index') }}">
                                        <i class="bi bi-chat-left-dots me-2 text-primary"></i>SMS Logs & Reminders
                                    </a>
                                </li>
                            @endif

                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a class="dropdown-item py-2 {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}">
                                    <i class="bi bi-person-gear me-2 text-primary"></i>My Profile
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item py-2 text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </button>
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
                        <a class="btn btn-sm btn-primary rounded-pill fw-semibold px-3 mt-1 mt-lg-0" href="{{ route('register') }}">
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

{{-- Shared confirmation dialog (replaces browser confirm popups) --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center pt-4">
                <div class="stat-icon bg-light text-primary mx-auto mb-3"><i class="bi bi-question-circle"></i></div>
                <p class="mb-0" id="confirmMessage">Are you sure?</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmOkBtn">Yes, continue</button>
            </div>
        </div>
    </div>
</div>

{{-- Chat bot assistant --}}
@auth
    <div class="chat-panel no-print" id="chatPanel" aria-hidden="true">
        <div class="chat-header">
            <i class="bi bi-chat-heart"></i> Clinic Assistant
            <button type="button" class="btn-close btn-close-white ms-auto" id="chatClose" aria-label="Close chat"></button>
        </div>
        <div class="chat-messages" id="chatMessages">
            <div class="chat-msg bot">Hi! 👋 Ask me about our services, clinic hours, or how to book an appointment.</div>
        </div>
        <form id="chatForm">
            <input type="text" class="form-control form-control-sm" id="chatInput"
                   placeholder="Type a message…" maxlength="1000" autocomplete="off" required>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send"></i></button>
        </form>
    </div>

    <button type="button" class="chat-fab no-print" id="chatFab" title="Clinic Assistant" aria-label="Open clinic assistant chat">
        <i class="bi bi-chat-dots"></i>
    </button>
@endauth

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

    /** Confirm-then-act helper: shows the in-app dialog, runs action() on confirm. */
    function confirmAction(message, action) {
        const modalEl = document.getElementById('confirmModal');
        const okBtn = document.getElementById('confirmOkBtn');
        document.getElementById('confirmMessage').textContent = message;

        const onOk = () => {
            modal.removeEventListener('hidden.bs.modal', onDismiss);
            modal.hide();
            action();
        };
        const onDismiss = () => okBtn.removeEventListener('click', onOk);

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        okBtn.addEventListener('click', onOk, { once: true });
        modalEl.addEventListener('hidden.bs.modal', onDismiss, { once: true });
        modal.show();
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
        language: {
            search: '<i class="bi bi-search me-1"></i>',
            searchPlaceholder: 'Search (typo-friendly)…'
        },
    };

    // ---- Global DataTables Typo-Tolerant (Fuzzy) Search Extension ----
    function dtLevenshtein(a, b) {
        if (a.length === 0) return b.length;
        if (b.length === 0) return a.length;
        const matrix = [];
        for (let i = 0; i <= b.length; i++) matrix[i] = [i];
        for (let j = 0; j <= a.length; j++) matrix[0][j] = j;

        for (let i = 1; i <= b.length; i++) {
            for (let j = 1; j <= a.length; j++) {
                if (b.charAt(i - 1) === a.charAt(j - 1)) {
                    matrix[i][j] = matrix[i - 1][j - 1];
                } else {
                    matrix[i][j] = Math.min(
                        matrix[i - 1][j - 1] + 1,
                        matrix[i][j - 1] + 1,
                        matrix[i - 1][j] + 1
                    );
                }
            }
        }
        return matrix[b.length][a.length];
    }

    function dtFuzzyMatch(needle, haystack) {
        const n = needle.trim().toLowerCase();
        if (!n) return true;
        const h = (haystack || '').toLowerCase();

        // 1. Direct substring
        if (h.includes(n)) return true;

        // 2. Tokenized words
        const nWords = n.split(/\s+/).filter(Boolean);
        const hWords = h.split(/\s+/).filter(Boolean);

        return nWords.every(word => {
            if (h.includes(word)) return true;

            // Character subsequence (e.g. "dnl" in "daniel")
            let wIdx = 0;
            for (let i = 0; i < h.length && wIdx < word.length; i++) {
                if (h[i] === word[wIdx]) wIdx++;
            }
            if (wIdx === word.length) return true;

            // Typo tolerance if word length >= 3
            if (word.length >= 3) {
                const maxErrors = word.length <= 4 ? 1 : 2;
                return hWords.some(hWord => {
                    const sub = hWord.slice(0, word.length + 1);
                    return dtLevenshtein(word, sub) <= maxErrors;
                });
            }

            return false;
        });
    }

    if (window.jQuery && $.fn && $.fn.dataTable) {
        $(document).on('init.dt', function (e, settings) {
            const api = new $.fn.dataTable.Api(settings);
            const $search = $(api.table().container()).find('.dataTables_filter input');
            $search.off('keyup.DT search.DT input.DT paste.DT cut.DT');
            $search.on('input', function () {
                settings._fuzzyTerm = this.value;
                api.draw();
            });
        });

        $.fn.dataTable.ext.search.push(function (settings, searchData) {
            const term = settings._fuzzyTerm;
            if (!term || !term.trim()) return true;
            return dtFuzzyMatch(term, searchData.join(' '));
        });
    }

    // ------------------------------------------------------------------
    // Chat bot widget
    // ------------------------------------------------------------------
    (function () {
        const fab = document.getElementById('chatFab');
        if (!fab) return;

        const panel = document.getElementById('chatPanel');
        const messages = document.getElementById('chatMessages');
        const form = document.getElementById('chatForm');
        const input = document.getElementById('chatInput');

        const toggleChat = () => {
            panel.classList.toggle('open');
            panel.setAttribute('aria-hidden', panel.classList.contains('open') ? 'false' : 'true');
            if (panel.classList.contains('open')) input.focus();
        };

        fab.addEventListener('click', toggleChat);
        document.getElementById('chatClose').addEventListener('click', toggleChat);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && panel.classList.contains('open')) toggleChat();
        });

        const appendMsg = (sender, text) => {
            const div = document.createElement('div');
            div.className = `chat-msg ${sender}`;
            div.textContent = text;
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
            return div;
        };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = input.value.trim();
            if (!message) return;

            appendMsg('user', message);
            input.value = '';
            input.disabled = true;
            const pending = appendMsg('bot', 'Typing…');

            try {
                const res = await apiFetch('{{ route('chatbot.send') }}', {
                    method: 'POST',
                    body: { message }
                });
                pending.textContent = res.reply;
            } catch (err) {
                pending.textContent = 'Sorry, something went wrong. Please try again.';
            } finally {
                input.disabled = false;
                input.focus();
                messages.scrollTop = messages.scrollHeight;
            }
        });
    })();

    // ------------------------------------------------------------------
    // Small a11y helpers
    // ------------------------------------------------------------------
    (function () {
        // Mark the active nav item for screen readers.
        document.querySelectorAll('.navbar-app .nav-link.active').forEach((link) => {
            link.setAttribute('aria-current', 'page');
        });
    })();
</script>

@stack('scripts')
</body>
</html>
