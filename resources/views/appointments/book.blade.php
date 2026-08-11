@extends('layouts.app')

@section('title', 'Book an Appointment')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center gap-2 mb-4">
                <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-calendar-plus"></i></div>
                <div>
                    <h4 class="fw-bold mb-0">Online Appointment Booking</h4>
                    <p class="text-muted small mb-0">Available time slots are shown in real time — once a slot is taken, it disappears.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    <form id="bookingForm" novalidate>
                        @csrf

                        @if(auth()->user()->isStaff())
                            <div class="mb-3">
                                <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                                <select class="form-select" id="patient_id" name="patient_id" required>
                                    <option value="">Select patient…</option>
                                    @foreach($patients as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->contact_no }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="dentist_id" class="form-label">Preferred dentist <span class="text-danger">*</span></label>
                                <select class="form-select" id="dentist_id" name="dentist_id" required>
                                    <option value="">Select dentist…</option>
                                    @foreach($dentists as $d)
                                        <option value="{{ $d->id }}">Dr. {{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="appointment_date" class="form-label">Appointment date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="appointment_date" name="appointment_date"
                                       min="{{ now()->toDateString() }}" max="{{ now()->addMonths(2)->toDateString() }}" required>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label for="service_type" class="form-label">Service type <span class="text-danger">*</span></label>
                                <select class="form-select" id="service_type" name="service_type" required>
                                    <option value="">Select service…</option>
                                    @foreach(\App\Models\Appointment::SERVICES as $service)
                                        <option value="{{ $service }}">{{ $service }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="time_slot" class="form-label">Available time slots <span class="text-danger">*</span></label>
                                <div id="slotsArea" class="border rounded p-2 bg-light" style="min-height: 46px;">
                                    <p class="text-muted small mb-0 py-1" id="slotsHint">
                                        <i class="bi bi-info-circle me-1"></i>Select a dentist and date to see available slots.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
                                <i class="bi bi-calendar-check me-1"></i>Book Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- My upcoming bookings (patients) --}}
            @if(auth()->user()->isPatient())
                <div class="card mt-4">
                    <div class="card-header"><i class="bi bi-calendar2-week me-2 text-primary"></i>My Appointments</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>Date</th><th>Time</th><th>Service</th><th>Dentist</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    @forelse(auth()->user()->patientAppointments()->orderByDesc('appointment_date')->orderByDesc('time_slot')->get() as $appt)
                                        <tr>
                                            <td>{{ $appt->appointment_date->format('M d, Y') }}</td>
                                            <td>{{ $appt->formatted_slot }}</td>
                                            <td>{{ $appt->service_type }}</td>
                                            <td>{{ $appt->dentist->name ?? '—' }}</td>
                                            <td>@include('partials.status-badge', ['status' => $appt->status])</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">You have no appointments yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const dentistSelect = document.getElementById('dentist_id');
    const dateInput = document.getElementById('appointment_date');
    const slotsArea = document.getElementById('slotsArea');
    const slotsHint = document.getElementById('slotsHint');
    const submitBtn = document.getElementById('submitBtn');
    const bookingForm = document.getElementById('bookingForm');

    /** Debounce slot fetching while the user tweaks dentist/date. */
    let fetchTimer = null;
    function refreshSlots() {
        clearTimeout(fetchTimer);
        if (!dentistSelect.value || !dateInput.value) {
            slotsArea.innerHTML = '<p class="text-muted small mb-0 py-1"><i class="bi bi-info-circle me-1"></i>Select a dentist and date to see available slots.</p>';
            submitBtn.disabled = true;
            return;
        }
        fetchTimer = setTimeout(async () => {
            slotsArea.innerHTML = '<p class="text-muted small mb-0 py-1"><i class="bi bi-hourglass-split me-1"></i>Checking availability…</p>';
            submitBtn.disabled = true;
            try {
                const params = new URLSearchParams({ dentist_id: dentistSelect.value, date: dateInput.value });
                const res = await apiFetch(`{{ route('appointments.available-slots') }}?${params}`);
                renderSlots(res.slots);
            } catch (e) {
                slotsArea.innerHTML = `<p class="text-danger small mb-0 py-1"><i class="bi bi-exclamation-triangle me-1"></i>${e.message}</p>`;
            }
        }, 250);
    }

    function renderSlots(slots) {
        if (!slots.length) {
            slotsArea.innerHTML = '<p class="text-warning small mb-0 py-1"><i class="bi bi-calendar-x me-1"></i>No available slots for this dentist on this date. Try another day.</p>';
            submitBtn.disabled = true;
            return;
        }
        slotsArea.innerHTML = slots.map(slot => `
            <button type="button" class="btn btn-outline-primary slot-btn mb-1 js-slot" data-slot="${slot}">
                ${formatTime(slot)}
            </button>`).join('');
        submitBtn.disabled = true;
    }

    function formatTime(slot) {
        const [h, m] = slot.split(':').map(Number);
        const suffix = h >= 12 ? 'PM' : 'AM';
        const hour = h % 12 || 12;
        return `${hour}:${String(m).padStart(2, '0')} ${suffix}`;
    }

    // Slot selection
    slotsArea.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-slot');
        if (!btn) return;
        slotsArea.querySelectorAll('.js-slot').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        submitBtn.disabled = false;
    });

    dentistSelect.addEventListener('change', refreshSlots);
    dateInput.addEventListener('change', refreshSlots);

    // Submit booking via AJAX
    bookingForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const activeSlot = slotsArea.querySelector('.js-slot.active');
        if (!activeSlot) {
            showToast('Please choose a time slot.', 'warning');
            return;
        }

        const payload = formToObject(bookingForm);
        payload.time_slot = activeSlot.dataset.slot;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Booking…';

        try {
            const res = await apiFetch('{{ route('appointments.book.store') }}', {
                method: 'POST',
                body: payload
            });
            showToast(res.message, 'success');
            bookingForm.reset();
            slotsArea.innerHTML = '<p class="text-muted small mb-0 py-1"><i class="bi bi-info-circle me-1"></i>Select a dentist and date to see available slots.</p>';
            setTimeout(() => location.reload(), 1500); // refresh "My appointments"
        } catch (err) {
            showToast(err.message, err.status === 409 ? 'warning' : 'danger');
            refreshSlots(); // refresh availability in real time
        } finally {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-calendar-check me-1"></i>Book Appointment';
        }
    });
</script>
@endpush
