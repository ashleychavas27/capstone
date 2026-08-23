@extends('layouts.app')

@section('title', 'Book an Appointment')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex align-items-center gap-2 mb-4">
                <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-calendar-plus"></i></div>
                <div>
                    <h4 class="fw-bold mb-0">Online Appointment Booking</h4>
                    <p class="text-muted small mb-0">Pick a dentist, date and time — available slots update in real time.</p>
                </div>
            </div>

            @if(auth()->user()->isPatient())
                {{-- Next appointment banner --}}
                @php
                    $next = auth()->user()->patientAppointments()
                        ->whereDate('appointment_date', '>=', now()->toDateString())
                        ->where('status', '!=', \App\Models\Appointment::STATUS_CANCELLED)
                        ->orderBy('appointment_date')->orderBy('time_slot')
                        ->first();
                @endphp
                @if($next)
                    <div class="alert alert-light border d-flex align-items-center gap-3 no-print" role="status">
                        <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-calendar2-check"></i></div>
                        <div>
                            <strong>Next appointment:</strong>
                            {{ $next->appointment_date->format('l, M d, Y') }} at {{ $next->formatted_slot }} —
                            {{ $next->service_type }}
                            <span class="ms-1">@include('partials.status-badge', ['status' => $next->status])</span>
                        </div>
                    </div>
                @endif
            @endif

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-body p-4">
                            <form id="bookingForm" novalidate>
                                @csrf

                                @if(auth()->user()->isStaff())
                                    <div class="mb-3 position-relative" id="patientSearchContainer">
                                        <label for="patientSearchInput" class="form-label d-flex justify-content-between align-items-center">
                                            <span>Patient <span class="text-danger">*</span></span>
                                            <span class="text-muted small" id="patientSearchTip"><i class="bi bi-search me-1"></i>Search name or phone</span>
                                        </label>

                                        <div class="input-group">
                                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-person"></i></span>
                                            <input type="text" class="form-control border-start-0 ps-0" id="patientSearchInput"
                                                   placeholder="Type patient name, phone or email (e.g. Daniel, 0918)…"
                                                   autocomplete="off" required>
                                            <button type="button" class="btn btn-outline-secondary border-start-0 d-none" id="clearPatientBtn" title="Clear selection">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>

                                        {{-- Hidden input containing selected patient ID for form submission --}}
                                        <input type="hidden" id="patient_id" name="patient_id" required>

                                        {{-- Floating search results dropdown --}}
                                        <div id="patientDropdown" class="dropdown-menu shadow w-100 p-1 position-absolute"
                                             style="display: none; max-height: 280px; overflow-y: auto; z-index: 1060; top: 100%; left: 0;">
                                            <div id="patientResultsList"></div>
                                        </div>

                                        {{-- Selected patient card banner --}}
                                        <div id="selectedPatientCard" class="mt-2 p-2 bg-light border rounded d-none align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                                     style="width: 32px; height: 32px; font-size: 0.75rem; background: var(--gold); color: #fff;" id="selectedAvatar">
                                                    --
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-dark small" id="selectedName">—</div>
                                                    <div class="text-muted" style="font-size: 0.72rem;" id="selectedContact">—</div>
                                                </div>
                                            </div>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle small">Selected</span>
                                        </div>
                                    </div>
                                @endif

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="service_type" class="form-label"><span class="fw-semibold">1.</span> Service type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="service_type" name="service_type" required>
                                            <option value="">Select service…</option>
                                            @foreach(\App\Models\Appointment::SERVICES as $service)
                                                <option value="{{ $service }}">{{ $service }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="dentist_id" class="form-label"><span class="fw-semibold">2.</span> Preferred dentist <span class="text-danger">*</span></label>
                                        <select class="form-select" id="dentist_id" name="dentist_id" required>
                                            <option value="">Select dentist…</option>
                                            @foreach($dentists as $d)
                                                <option value="{{ $d->id }}">Dr. {{ $d->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <label for="appointment_date" class="form-label"><span class="fw-semibold">3.</span> Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="appointment_date" name="appointment_date"
                                               min="{{ now()->toDateString() }}" max="{{ now()->addMonths(2)->toDateString() }}" required>
                                        <div class="form-text">
                                            Clinic hours: Mon–Sat, 9:00 AM – 5:00 PM
                                            <span class="text-muted">(closed Sundays · lunch break 12–1 PM)</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <span class="form-label d-block"><span class="fw-semibold">4.</span> Available time slots <span class="text-danger">*</span></span>
                                    <div id="slotsArea" class="border rounded p-2" style="min-height: 46px;" role="group" aria-label="Available time slots">
                                        <p class="text-muted small mb-0 py-1" id="slotsHint">
                                            <i class="bi bi-info-circle me-1"></i>Select a dentist and date to see available slots.
                                        </p>
                                    </div>
                                </div>

                                <div class="alert alert-warning small mt-3 mb-0 d-none" id="bookingError" role="alert"></div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Live booking summary --}}
                <div class="col-lg-4">
                    <div class="card sticky-top" style="top: 5.5rem;">
                        <div class="card-header"><i class="bi bi-card-checklist me-2 text-primary"></i>Booking Summary</div>
                        <div class="card-body">
                            <dl class="row mb-0 small" id="summaryList">
                                <dt class="col-5 text-muted fw-normal">Service</dt><dd class="col-7" data-summary="service">—</dd>
                                <dt class="col-5 text-muted fw-normal">Dentist</dt><dd class="col-7" data-summary="dentist">—</dd>
                                <dt class="col-5 text-muted fw-normal">Date</dt><dd class="col-7" data-summary="date">—</dd>
                                <dt class="col-5 text-muted fw-normal">Time</dt><dd class="col-7" data-summary="time">—</dd>
                                @if(auth()->user()->isStaff())
                                    <dt class="col-5 text-muted fw-normal">Patient</dt><dd class="col-7" data-summary="patient">—</dd>
                                @endif
                            </dl>

                            <button type="submit" form="bookingForm" class="btn btn-primary w-100 mt-3" id="submitBtn" disabled>
                                <i class="bi bi-calendar-check me-1"></i>Confirm Booking
                            </button>
                            <p class="text-muted small text-center mb-0 mt-2">
                                @if(auth()->user()->isStaff())
                                    <i class="bi bi-shield-check me-1"></i>The patient will receive an SMS confirmation once booked.
                                @else
                                    <i class="bi bi-shield-check me-1"></i>You'll get an SMS once the clinic confirms.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Inline success feedback (no page reload) --}}
            <div class="alert alert-success mt-4 d-none no-print" id="bookingSuccess" role="status">
                <h5 class="alert-heading"><i class="bi bi-check-circle me-1"></i>Appointment booked!</h5>
                <p class="mb-2" id="bookingSuccessText"></p>
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary">Go to Dashboard</a>
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
                                <tbody id="myAppointmentsBody">
                                    @forelse(auth()->user()->patientAppointments()->with('dentist')->orderByDesc('appointment_date')->orderByDesc('time_slot')->get() as $appt)
                                        <tr>
                                            <td>{{ $appt->appointment_date->format('M d, Y') }}</td>
                                            <td>{{ $appt->formatted_slot }}</td>
                                            <td>{{ $appt->service_type }}</td>
                                            <td>{{ $appt->dentist->name ?? '—' }}</td>
                                            <td>@include('partials.status-badge', ['status' => $appt->status])</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">
                                            <i class="bi bi-calendar-x d-block fs-3 mb-2"></i>
                                            You have no appointments yet — fill out the form above to book your first visit.
                                        </td></tr>
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
    const serviceSelect = document.getElementById('service_type');
    const patientSelect = document.getElementById('patient_id');
    const slotsArea = document.getElementById('slotsArea');
    const submitBtn = document.getElementById('submitBtn');
    const bookingForm = document.getElementById('bookingForm');
    const bookingError = document.getElementById('bookingError');

    @php
        $patientData = ($patients ?? collect())->map(function ($p) {
            $parts = explode(' ', trim($p->name));
            $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
            return [
                'id' => $p->id,
                'name' => $p->name,
                'contact_no' => $p->contact_no ?? '',
                'email' => $p->email ?? '',
                'initials' => $initials ?: '--',
            ];
        })->values();
    @endphp
    const allPatients = {!! json_encode($patientData) !!};

    let selectedPatient = null;
    const patientSearchInput = document.getElementById('patientSearchInput');
    const patientDropdown = document.getElementById('patientDropdown');
    const patientResultsList = document.getElementById('patientResultsList');
    const clearPatientBtn = document.getElementById('clearPatientBtn');
    const selectedPatientCard = document.getElementById('selectedPatientCard');
    const selectedAvatar = document.getElementById('selectedAvatar');
    const selectedName = document.getElementById('selectedName');
    const selectedContact = document.getElementById('selectedContact');

    // ---- Fuzzy / Typo-Tolerant String Distance (Damerau-Levenshtein) ----
    function levenshtein(a, b) {
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
                        matrix[i - 1][j - 1] + 1, // substitution
                        matrix[i][j - 1] + 1,     // insertion
                        matrix[i - 1][j] + 1      // deletion
                    );
                }
            }
        }
        return matrix[b.length][a.length];
    }

    /**
     * Scores how well a patient matches a search query.
     * Supports exact match, prefix match, phone match, and typo-tolerant fuzzy matching.
     */
    function computeMatchScore(patient, query) {
        const q = query.trim().toLowerCase();
        if (!q) return 1;

        const name = (patient.name || '').toLowerCase();
        const contactDigits = (patient.contact_no || '').replace(/\D/g, '');
        const queryDigits = q.replace(/\D/g, '');
        const email = (patient.email || '').toLowerCase();

        // 1. Direct substring matches
        if (name === q) return 1000;
        if (name.startsWith(q)) return 600;
        if (name.includes(q)) return 400;

        // 2. Contact phone match
        if (queryDigits && contactDigits.includes(queryDigits)) return 500;

        // 3. Email match
        if (email.includes(q)) return 300;

        // 4. Word-level prefix matching
        const words = name.split(/\s+/);
        for (const word of words) {
            if (word.startsWith(q)) return 450;
            if (word.includes(q)) return 250;
        }

        // 5. Typo-tolerant distance matching (handles spelling mistakes)
        if (q.length >= 3) {
            // Check word edit distance
            for (const word of words) {
                const maxTypos = q.length <= 4 ? 1 : 2;
                const dist = levenshtein(q, word.slice(0, q.length + 1));
                if (dist <= maxTypos) {
                    return 200 - dist * 40;
                }
            }

            // Check full name edit distance
            const nameSub = name.slice(0, q.length + 2);
            const dist = levenshtein(q, nameSub);
            if (dist <= (q.length <= 4 ? 1 : 2)) {
                return 180 - dist * 40;
            }

            // Fuzzy subsequence match (e.g. "dnl crz" -> "daniel cruz")
            let qIdx = 0;
            for (let i = 0; i < name.length && qIdx < q.length; i++) {
                if (name[i] === q[qIdx]) qIdx++;
            }
            if (qIdx === q.length) return 120;
        }

        return 0; // No match
    }

    const MAX_PATIENT_RESULTS = 8; // Limit to 8 items max so it never overflows

    function filterPatients(query = '') {
        if (!query.trim()) {
            return allPatients.slice(0, MAX_PATIENT_RESULTS);
        }

        const scored = [];
        for (const p of allPatients) {
            const score = computeMatchScore(p, query);
            if (score > 0) {
                scored.push({ patient: p, score });
            }
        }

        scored.sort((a, b) => b.score - a.score);
        return scored.slice(0, MAX_PATIENT_RESULTS).map(item => item.patient);
    }

    function renderPatientResults(query = '') {
        if (!patientDropdown) return;
        const results = filterPatients(query);

        if (!results.length) {
            patientResultsList.innerHTML = `
                <div class="px-3 py-2 text-muted small text-center">
                    <i class="bi bi-person-x d-block fs-5 mb-1"></i>
                    No patients found matching "<strong>${escapeHtml(query)}</strong>"
                </div>`;
            patientDropdown.style.display = 'block';
            return;
        }

        let html = '';
        results.forEach((p, idx) => {
            html += `
                <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-2 rounded js-patient-option ${idx === 0 ? 'active-candidate' : ''}" data-id="${p.id}">
                    <span class="avatar rounded-circle d-flex align-items-center justify-content-center fw-bold"
                          style="width: 28px; height: 28px; font-size: 0.7rem; background: var(--gold); color: #fff;">
                        ${p.initials}
                    </span>
                    <div class="text-truncate">
                        <div class="fw-semibold small text-dark">${escapeHtml(p.name)}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">${escapeHtml(p.contact_no || 'No contact')} · ${escapeHtml(p.email)}</div>
                    </div>
                </button>`;
        });

        if (allPatients.length > MAX_PATIENT_RESULTS) {
            html += `<div class="dropdown-divider my-1"></div>
                     <div class="px-2 py-1 text-muted text-center" style="font-size: 0.68rem;">
                         Showing top ${results.length} results · Type more for specific match
                     </div>`;
        }

        patientResultsList.innerHTML = html;
        patientDropdown.style.display = 'block';
    }

    function selectPatient(patient) {
        selectedPatient = patient;
        if (patientSelect) patientSelect.value = patient ? patient.id : '';
        if (patientSearchInput) {
            patientSearchInput.value = patient ? `${patient.name} (${patient.contact_no})` : '';
            patientSearchInput.classList.remove('is-invalid');
        }
        if (patientDropdown) patientDropdown.style.display = 'none';

        if (patient) {
            if (clearPatientBtn) clearPatientBtn.classList.remove('d-none');
            if (selectedPatientCard) {
                selectedAvatar.textContent = patient.initials;
                selectedName.textContent = patient.name;
                selectedContact.textContent = `${patient.contact_no || 'No phone'} · ${patient.email}`;
                selectedPatientCard.classList.remove('d-none');
                selectedPatientCard.classList.add('d-flex');
            }
        } else {
            if (clearPatientBtn) clearPatientBtn.classList.add('d-none');
            if (selectedPatientCard) {
                selectedPatientCard.classList.add('d-none');
                selectedPatientCard.classList.remove('d-flex');
            }
        }

        refreshSummary();
        checkReady();
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    if (patientSearchInput) {
        patientSearchInput.addEventListener('focus', () => {
            renderPatientResults(selectedPatient ? '' : patientSearchInput.value);
        });

        patientSearchInput.addEventListener('input', () => {
            if (selectedPatient) {
                selectedPatient = null;
                if (patientSelect) patientSelect.value = '';
                if (clearPatientBtn) clearPatientBtn.classList.add('d-none');
                if (selectedPatientCard) {
                    selectedPatientCard.classList.add('d-none');
                    selectedPatientCard.classList.remove('d-flex');
                }
                refreshSummary();
                checkReady();
            }
            renderPatientResults(patientSearchInput.value);
        });

        patientSearchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                patientDropdown.style.display = 'none';
            }
        });

        if (clearPatientBtn) {
            clearPatientBtn.addEventListener('click', () => {
                selectPatient(null);
                patientSearchInput.value = '';
                patientSearchInput.focus();
                renderPatientResults('');
            });
        }

        if (patientResultsList) {
            patientResultsList.addEventListener('click', (e) => {
                const btn = e.target.closest('.js-patient-option');
                if (!btn) return;
                const id = Number(btn.dataset.id);
                const found = allPatients.find(p => p.id === id);
                if (found) selectPatient(found);
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const container = document.getElementById('patientSearchContainer');
            if (container && !container.contains(e.target)) {
                if (patientDropdown) patientDropdown.style.display = 'none';
            }
        });
    }

    // ---- Live booking summary ----
    function updateSummary(key, value) {
        const el = document.querySelector(`[data-summary="${key}"]`);
        if (el) el.textContent = value;
    }

    function refreshSummary() {
        updateSummary('service', serviceSelect.value || '—');
        updateSummary('dentist', dentistSelect.selectedOptions[0]?.textContent.trim() || '—');
        updateSummary('date', dateInput.value ? new Date(dateInput.value + 'T00:00').toLocaleDateString([], { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' }) : '—');
        updateSummary('time', slotsArea.querySelector('.js-slot.active')?.dataset.label ?? '—');
        if (patientSelect) {
            updateSummary('patient', selectedPatient?.name || (patientSelect.value ? 'Selected' : '—'));
        }
    }

    function checkReady() {
        const hasSlot = !!slotsArea.querySelector('.js-slot.active');
        const baseReady = dentistSelect.value && dateInput.value && serviceSelect.value && hasSlot;
        submitBtn.disabled = !(baseReady && (!patientSelect || patientSelect.value));
    }

    [dentistSelect, dateInput, serviceSelect].forEach((el) => {
        el?.addEventListener('change', () => { refreshSummary(); checkReady(); });
    });

    /** Debounce slot fetching while the user tweaks dentist/date. */
    let fetchTimer = null;
    function refreshSlots() {
        clearTimeout(fetchTimer);
        if (!dentistSelect.value || !dateInput.value) {
            slotsArea.innerHTML = '<p class="text-muted small mb-0 py-1"><i class="bi bi-info-circle me-1"></i>Select a dentist and date to see available slots.</p>';
            checkReady();
            return;
        }
        fetchTimer = setTimeout(async () => {
            slotsArea.innerHTML = '<p class="text-muted small mb-0 py-1"><i class="bi bi-hourglass-split me-1"></i>Checking availability…</p>';
            try {
                const params = new URLSearchParams({ dentist_id: dentistSelect.value, date: dateInput.value });
                const res = await apiFetch(`{{ route('appointments.available-slots') }}?${params}`);
                renderSlots(res.slots);
            } catch (e) {
                slotsArea.innerHTML = `<p class="text-danger small mb-0 py-1"><i class="bi bi-exclamation-triangle me-1"></i>${e.message}</p>`;
            }
            checkReady();
        }, 250);
    }

    function renderSlots(slots) {
        if (!slots.length) {
            const day = new Date(dateInput.value + 'T00:00').getDay();
            const reason = day === 0
                ? 'The clinic is closed on Sundays. Please pick another day.'
                : 'No free slots left for this dentist on this date. Try another day.';
            slotsArea.innerHTML = `<p class="text-warning small mb-0 py-1"><i class="bi bi-calendar-x me-1"></i>${reason}</p>`;
            return;
        }
        slotsArea.innerHTML = slots.map(slot => `
            <button type="button" class="btn btn-outline-primary slot-btn mb-1 js-slot" data-slot="${slot}" data-label="${formatTime(slot)}"
                    aria-pressed="false">${formatTime(slot)}</button>`).join('');
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
        slotsArea.querySelectorAll('.js-slot').forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
        refreshSummary();
        checkReady();
    });

    dentistSelect.addEventListener('change', refreshSlots);
    dateInput.addEventListener('change', refreshSlots);

    // Submit booking via AJAX — inline success instead of page reload.
    bookingForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        bookingError.classList.add('d-none');

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

            const success = document.getElementById('bookingSuccess');
            const a = res.appointment;
            document.getElementById('bookingSuccessText').textContent =
                `${a.service_type} on ${a.date} at ${a.time_slot}. Status: Pending — the clinic will confirm shortly.`;
            success.classList.remove('d-none');
            success.scrollIntoView({ behavior: 'smooth', block: 'center' });

            bookingForm.reset();
            renderSlotsReset();

            // Refresh "My appointments" list without a full page reload.
            setTimeout(() => location.reload(), 2500);
        } catch (err) {
            showToast(err.message, err.status === 409 ? 'warning' : 'danger');
            bookingError.textContent = err.message;
            bookingError.classList.remove('d-none');
            refreshSlots(); // refresh availability in real time
        } finally {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-calendar-check me-1"></i>Confirm Booking';
        }
    });

    function renderSlotsReset() {
        slotsArea.innerHTML = '<p class="text-muted small mb-0 py-1"><i class="bi bi-info-circle me-1"></i>Select a dentist and date to see available slots.</p>';
        ['service', 'dentist', 'date', 'time'].forEach(updateSummaryBlank);
        if (typeof selectPatient === 'function') selectPatient(null);
        if (document.querySelector('[data-summary="patient"]')) updateSummary('patient', '—');
    }
    function updateSummaryBlank(key) { updateSummary(key, '—'); }
</script>
@endpush
