@extends('layouts.app')

@section('title', 'Schedule Calendar')

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <style>
        #calendar {
            background: #fff;
            border-radius: .9rem;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--silver);
        }

        /* Calendar Header & Typography */
        .fc .fc-toolbar-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.35rem;
            color: var(--taupe);
            font-weight: 700;
        }

        .fc .fc-col-header-cell-cushion {
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--gold-dark);
            padding: 8px 4px !important;
            text-decoration: none !important;
        }

        .fc .fc-daygrid-day-number {
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            color: #736261;
            padding: 6px 8px !important;
            text-decoration: none !important;
            font-variant-numeric: tabular-nums;
        }

        /* Toolbar Button Spacing & Independent Capsule Styling */
        .fc .fc-toolbar-chunk {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .fc .fc-button-group {
            display: inline-flex;
            gap: 5px;
        }

        .fc .fc-button-group > .fc-button {
            border-radius: 0.5rem !important;
            margin: 0 !important;
            flex: unset;
        }

        .fc .fc-button-primary {
            background-color: #fff;
            border: 1px solid var(--silver) !important;
            color: var(--taupe);
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: capitalize;
            border-radius: 0.5rem !important;
            padding: 0.38rem 0.75rem !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            transition: all 0.15s ease;
        }

        .fc .fc-prev-button, .fc .fc-next-button {
            padding: 0.38rem 0.55rem !important;
            min-width: 34px;
        }

        .fc .fc-button-primary:hover {
            background-color: var(--bg-alt);
            border-color: var(--silver) !important;
            color: var(--gold-dark);
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: var(--gold) !important;
            border-color: var(--gold) !important;
            color: #fff !important;
            box-shadow: 0 2px 4px rgba(200, 155, 39, 0.25);
        }

        .fc .fc-day-today { background: rgba(200, 155, 39, .06) !important; }

        /* Event Chips / Badges */
        .fc-daygrid-event {
            border-radius: 6px !important;
            margin: 2px 3px !important;
            padding: 3px 6px !important;
            border: none !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            cursor: pointer;
        }

        .fc-daygrid-event:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.12);
        }

        .fc-event-pill {
            display: flex;
            align-items: center;
            gap: 4px;
            width: 100%;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            line-height: 1.25;
        }

        .fc-event-pill .event-time {
            font-size: 0.72rem;
            font-weight: 700;
            white-space: nowrap;
            opacity: 0.92;
        }

        .fc-event-pill .event-patient {
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-grow: 1;
        }

        .legend-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: .35rem;
        }
    </style>
@endpush

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
        <div>
            <h4 class="fw-bold mb-0">Schedule Calendar</h4>
            <p class="text-muted small mb-0">{{ $filterToSelf ? 'Your confirmed and pending appointments' : 'All clinic appointments by status' }}</p>
        </div>

        @if(! $filterToSelf)
            <select id="dentistFilter" class="form-select form-select-sm w-auto">
                <option value="">All dentists</option>
                @foreach($dentists as $dentist)
                    <option value="{{ $dentist->id }}">{{ $dentist->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="d-flex flex-wrap gap-3 small text-muted mb-3 no-print align-items-center">
        <span><span class="legend-dot" style="background:#C89B27;"></span>Confirmed</span>
        <span><span class="legend-dot" style="background:#D1987F;"></span>Pending</span>
        <span><span class="legend-dot" style="background:#B4B1B2;"></span>Completed</span>
        <span><span class="legend-dot" style="background:#9A8F8E;"></span>Cancelled</span>
        <span class="ms-auto text-muted small"><i class="bi bi-info-circle me-1"></i>Click any event for complete details.</span>
    </div>

    <div id="calendar"></div>

    {{-- Event details modal --}}
    <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-event me-2 text-primary"></i>Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="eventModalBody"></div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    @if($canManage)
                        <a href="{{ route('appointments.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-gear me-1"></i>Manage Schedule
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const calendarEl = document.getElementById('calendar');
        const dentistFilter = document.getElementById('dentistFilter');

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            displayEventTime: false, // Prevents duplicate '9a 09:00 AM' prefixes
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            events: {
                url: '{{ route('appointments.calendar-events') }}',
                extraParams: () => ({
                    dentist_id: dentistFilter ? dentistFilter.value : ''
                }),
                failure: () => showToast('Failed to load schedule events.', 'danger')
            },
            eventContent: function (arg) {
                const p = arg.event.extendedProps;
                const time = p.time_slot || '';
                const patient = p.patient || 'Patient';
                const service = p.service_type || '';

                const container = document.createElement('div');
                container.className = 'fc-event-pill';
                container.title = `${time} · ${patient} (${service}) - ${p.status}`;
                container.innerHTML = `
                    <span class="event-time">${escapeHtml(time)}</span>
                    <span class="event-patient">${escapeHtml(patient)}</span>
                `;
                return { domNodes: [container] };
            },
            loading: (isLoading) => {
                calendarEl.style.opacity = isLoading ? '.55' : '1';
            },
            eventClick: function (info) {
                info.jsEvent.preventDefault();
                const p = info.event.extendedProps;
                const badgeMap = {
                    Pending: 'badge-status-pending', Confirmed: 'badge-status-confirmed',
                    Completed: 'badge-status-completed', Cancelled: 'badge-status-cancelled'
                };
                document.getElementById('eventModalBody').innerHTML = `
                    <div class="d-flex align-items-center gap-3 mb-3 p-3 bg-light rounded border">
                        <div class="avatar rounded-circle d-flex align-items-center justify-content-center fw-bold"
                             style="width: 42px; height: 42px; background: var(--gold); color: #fff; font-size: 0.9rem;">
                            ${(p.patient || 'P').split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() || '').join('')}
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">${escapeHtml(p.patient)}</h6>
                            <span class="badge rounded-pill ${badgeMap[p.status] || 'text-bg-secondary'} mt-1">${p.status}</span>
                        </div>
                    </div>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Service</dt><dd class="col-sm-8 fw-semibold">${escapeHtml(p.service_type)}</dd>
                        <dt class="col-sm-4 text-muted">Dentist</dt><dd class="col-sm-8">${escapeHtml(p.dentist)}</dd>
                        <dt class="col-sm-4 text-muted">Date &amp; Time</dt><dd class="col-sm-8 fw-bold text-dark">${p.time_slot} (${info.event.start.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })})</dd>
                    </dl>`;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('eventModal')).show();
            }
        });

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
        }

        if (dentistFilter) {
            dentistFilter.addEventListener('change', () => calendar.refetchEvents());
        }

        calendar.render();
    });
</script>
@endpush
