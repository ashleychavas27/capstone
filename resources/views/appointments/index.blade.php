@extends('layouts.app')

@section('title', 'Appointment Schedule')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Appointment Schedule</h4>
            <p class="text-muted small mb-0">Manage bookings — confirming an appointment auto-sends an SMS reminder</p>
        </div>
        <button type="button" class="btn btn-primary no-print" data-bs-toggle="modal" data-bs-target="#createAppointmentModal">
            <i class="bi bi-plus-lg me-1"></i>New Appointment
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-2 mb-3 no-print">
                <div class="col-auto">
                    <select id="filterStatus" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach(\App\Models\Appointment::STATUSES as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <input type="date" id="filterDate" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-secondary" id="clearFilters">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="appointmentsTable" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th>Dentist</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Create appointment modal --}}
    <div class="modal fade" id="createAppointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('appointments.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-calendar-plus me-2 text-primary"></i>Schedule New Appointment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Patient <span class="text-danger">*</span></label>
                                <select name="patient_id" class="form-select" required>
                                    <option value="">Select patient…</option>
                                    @foreach($patients as $patient)
                                        <option value="{{ $patient->id }}">{{ $patient->name }} ({{ $patient->contact_no }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Dentist</label>
                                <select name="dentist_id" class="form-select" id="staffDentistSelect">
                                    <option value="">Unassigned</option>
                                    @foreach($dentists as $dentist)
                                        <option value="{{ $dentist->id }}">{{ $dentist->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" name="appointment_date" class="form-control" min="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Time slot <span class="text-danger">*</span></label>
                                <select name="time_slot" class="form-select" required>
                                    @foreach(\App\Models\Appointment::SLOTS as $slot)
                                        <option value="{{ $slot }}">{{ \Carbon\Carbon::createFromFormat('H:i', $slot)->format('h:i A') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Service type <span class="text-danger">*</span></label>
                                <input type="text" name="service_type" class="form-control" list="serviceList"
                                       placeholder="e.g. Teeth Cleaning" required>
                                <datalist id="serviceList">
                                    @foreach(\App\Models\Appointment::SERVICES as $service)
                                        <option value="{{ $service }}">
                                    @endforeach
                                </datalist>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        const table = $('#appointmentsTable').DataTable({
            ...dtDefaults,
            ajax: {
                url: '{{ route('appointments.data') }}',
                data: (d) => ({
                    status: $('#filterStatus').val(),
                    date: $('#filterDate').val()
                }),
                dataSrc: 'data',
                error: () => showToast('Failed to load appointments.', 'danger')
            },
            order: [[0, 'desc'], [1, 'asc']],
            columns: [
                { data: 'date' },
                { data: 'time_slot' },
                { data: 'patient' },
                { data: 'patient_contact' },
                { data: 'dentist' },
                { data: 'service_type' },
                {
                    data: 'status',
                    render: (d) => {
                        const map = {
                            Pending: 'badge-status-pending', Confirmed: 'badge-status-confirmed',
                            Completed: 'badge-status-completed', Cancelled: 'badge-status-cancelled'
                        };
                        return `<span class="badge rounded-pill badge-status ${map[d] || 'text-bg-secondary'}">${d}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-end no-print',
                    orderable: false,
                    render: (d) => `
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-success js-confirm" data-id="${d.id}" ${d.status === 'Confirmed' ? 'disabled' : ''}
                                    title="Confirm & send SMS reminder">
                                <i class="bi bi-check2-circle"></i>
                            </button>
                            <button class="btn btn-outline-primary js-complete" data-id="${d.id}" ${d.status === 'Completed' ? 'disabled' : ''}
                                    title="Mark completed">
                                <i class="bi bi-check2-all"></i>
                            </button>
                            <button class="btn btn-outline-secondary js-cancel" data-id="${d.id}" ${d.status === 'Cancelled' ? 'disabled' : ''}
                                    title="Cancel appointment">
                                <i class="bi bi-x-circle"></i>
                            </button>
                            <button class="btn btn-outline-danger js-delete" data-id="${d.id}" title="Remove">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>`
                }
            ],
            language: {
                ...dtDefaults.language,
                emptyTable: 'No appointments match the current filters.'
            }
        });

        $('#filterStatus, #filterDate').on('change', () => table.ajax.reload());
        $('#clearFilters').on('click', () => {
            $('#filterStatus').val('');
            $('#filterDate').val('');
            table.ajax.reload();
        });

        // ---- Status updates via AJAX (with SMS trigger on Confirm) ----
        const setStatus = async (id, status) => {
            const btn = document.querySelector(`.js-${status === 'Confirmed' ? 'confirm' : status === 'Completed' ? 'complete' : 'cancel'}[data-id="${id}"]`);
            if (btn) btn.disabled = true;
            try {
                const res = await apiFetch(`/appointments/${id}/status`, {
                    method: 'PATCH',
                    body: { status }
                });
                showToast(res.message + (res.sms_triggered ? ` · SMS reminder ${res.sms_status === 'Failed' ? 'FAILED' : 'sent'} 📱` : ''), res.sms_triggered && res.sms_status !== 'Failed' ? 'success' : 'info');
                table.ajax.reload();
            } catch (e) {
                showToast(e.message, 'danger');
                if (btn) btn.disabled = false;
            }
        };

        $('#appointmentsTable').on('click', '.js-confirm', function () {
            setStatus($(this).data('id'), 'Confirmed');
        });
        $('#appointmentsTable').on('click', '.js-complete', function () {
            setStatus($(this).data('id'), 'Completed');
        });
        $('#appointmentsTable').on('click', '.js-cancel', function () {
            confirmAction('Cancel this appointment?', () => setStatus($(this).data('id'), 'Cancelled'));
        });

        // ---- Delete ----
        $('#appointmentsTable').on('click', '.js-delete', function () {
            const id = $(this).data('id');
            confirmAction('Permanently remove this appointment?', async () => {
                try {
                    await apiFetch(`/appointments/${id}`, { method: 'DELETE' });
                    showToast('Appointment removed.');
                    table.ajax.reload();
                } catch (e) {
                    showToast(e.message, 'danger');
                }
            });
        });
    });
</script>
@endpush
