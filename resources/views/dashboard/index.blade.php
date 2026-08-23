@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Dashboard</h4>
            <p class="text-muted small mb-0">Clinic overview for {{ now()->format('l, F d, Y') }}</p>
        </div>
        @if(in_array(auth()->user()->role, ['Owner', 'Secretary']))
            <a href="{{ route('dashboard.print', ['date' => $date]) }}" target="_blank" class="btn btn-outline-primary no-print">
                <i class="bi bi-printer me-1"></i>Print Daily Report
            </a>
        @endif
    </div>



    {{-- Metric cards --}}
    <div class="row g-3 mb-4">
        @php
            $statLinks = [
                'totalPatients' => auth()->user()->isStaff() ? route('patients.index') : route('appointments.book'),
                'upcomingAppointments' => auth()->user()->isStaff() ? route('appointments.calendar') : route('appointments.book'),
                'pendingBookings' => auth()->user()->isStaff() ? route('appointments.index') : route('appointments.book'),
                'todayAppointments' => route('dashboard'),
            ];
        @endphp
        <div class="col-6 col-lg-3">
            <a href="{{ $statLinks['totalPatients'] }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="fs-3 fw-bold text-primary">{{ $metrics['totalPatients'] }}</div>
                        <div class="text-muted small">{{ auth()->user()->isPatient() ? 'My Appointments' : 'Registered Patients' }}</div>
                    </div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ $statLinks['upcomingAppointments'] }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-calendar2-check"></i></div>
                    <div>
                        <div class="fs-3 fw-bold text-success">{{ $metrics['upcomingAppointments'] }}</div>
                        <div class="text-muted small">Upcoming Appointments</div>
                    </div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ $statLinks['pendingBookings'] }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div class="fs-3 fw-bold text-warning">{{ $metrics['pendingBookings'] }}</div>
                        <div class="text-muted small">Pending Bookings</div>
                    </div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ $statLinks['todayAppointments'] }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-calendar3"></i></div>
                    <div>
                        <div class="fs-3 fw-bold text-info">{{ $metrics['todayAppointments'] }}</div>
                        <div class="text-muted small">Appointments Today</div>
                    </div>
                </div>
            </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Daily schedule --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="bi bi-calendar2-week me-2 text-primary"></i>Daily Schedule
                        @if(auth()->user()->isDentist())
                            <span class="badge text-bg-light text-primary">My schedule</span>
                        @endif
                    </span>
                    <form method="GET" action="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 no-print">
                        <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" style="width: auto;">
                        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i></button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    @unless(auth()->user()->isDentist())<th>Dentist</th>@endunless
                                    <th>Service</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($schedule as $appt)
                                    <tr class="{{ auth()->user()->isStaff() ? 'row-click' : '' }}" data-href="{{ route('patients.show', $appt->patient) }}"
                                        {{ auth()->user()->isStaff() ? 'title="View patient record"' : '' }}>
                                        <td class="fw-semibold">{{ $appt->formatted_slot }}</td>
                                        <td>
                                            @if(auth()->user()->isStaff())
                                                <a href="{{ route('patients.show', $appt->patient) }}" class="text-decoration-none">{{ $appt->patient->name ?? '—' }}</a>
                                            @else
                                                {{ $appt->patient->name ?? '—' }}
                                            @endif
                                        </td>
                                        @unless(auth()->user()->isDentist())
                                            <td>{{ $appt->dentist->name ?? 'Unassigned' }}</td>
                                        @endunless
                                        <td>{{ $appt->service_type }}</td>
                                        <td>
                                            @include('partials.status-badge', ['status' => $appt->status])
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="bi bi-calendar-x d-block fs-3 mb-2"></i>
                                            No appointments scheduled for {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white small text-muted no-print" style="border-top: 1px solid #B4B1B2;">
                    <i class="bi bi-hand-index me-1"></i>{{ auth()->user()->isStaff() ? 'Click a patient name to open their record.' : '' }}
                    Showing {{ $schedule->count() }} appointment(s).
                </div>
            </div>
        </div>

        {{-- Recent SMS activity (staff only — contains other patients' phone numbers) --}}
        @if(auth()->user()->isStaff())
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-chat-left-dots me-2 text-primary"></i>Recent SMS Notifications</span>
                </div>
                <div class="card-body">
                    @forelse($recentSms as $log)
                        <div class="d-flex align-items-start gap-2 mb-3">
                            <div class="stat-icon bg-light text-primary" style="width: 36px; height: 36px; font-size: 1rem; border-radius: .6rem;">
                                <i class="bi bi-phone"></i>
                            </div>
                            <div class="flex-grow-1 small">
                                <div class="fw-semibold text-truncate">{{ $log->recipient_phone }}</div>
                                <div class="text-muted text-truncate" style="max-width: 220px;" title="{{ $log->message }}">{{ $log->message }}</div>
                                <div class="text-muted mt-1" style="font-size: .75rem;">
                                    {{ $log->sent_at ? $log->sent_at->diffForHumans() : '—' }}
                                    <span class="badge text-bg-{{ $log->status === 'Failed' ? 'danger' : 'success' }} ms-1">{{ $log->status }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small text-center py-3 mb-0">
                            <i class="bi bi-inbox d-block fs-3 mb-2"></i>No SMS sent yet.<br>
                            Confirm an appointment to trigger a reminder.
                        </p>
                    @endforelse

                    @if($recentSms->isNotEmpty())
                        <a href="{{ route('sms.index') }}" class="btn btn-sm btn-outline-primary w-100 mt-2">
                            View all SMS logs <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection
