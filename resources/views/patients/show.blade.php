@extends('layouts.app')

@section('title', $patient->name . ' · Patient Record')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
        <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Patients
        </a>
    </div>

    <div class="row g-4">
        {{-- Profile / medical history --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="stat-icon bg-primary-subtle text-primary mx-auto mb-3" style="width: 72px; height: 72px; font-size: 2.2rem;">
                        <i class="bi bi-person"></i>
                    </div>
                    <h5 class="fw-bold mb-0">{{ $patient->name }}</h5>
                    <div class="text-muted small mb-3">{{ $patient->email }}</div>
                    <div class="row text-start small g-2">
                        <div class="col-6">
                            <div class="text-muted">Contact No.</div>
                            <div class="fw-semibold">{{ $patient->contact_no ?? '—' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted">Age</div>
                            <div class="fw-semibold">{{ $patient->patientProfile?->age ?? '—' }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted">Address</div>
                            <div class="fw-semibold">{{ $patient->patientProfile?->address ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><i class="bi bi-file-earmark-medical me-2 text-primary"></i>Medical History</div>
                <div class="card-body">
                    <p class="mb-0 small">{{ $patient->patientProfile?->medical_history ?: 'No medical history on file.' }}</p>
                </div>
            </div>
        </div>

        {{-- Consultation history --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <span><i class="bi bi-journal-medical me-2 text-primary"></i>Consultation &amp; Treatment History
                        ({{ $patient->patientAppointments->count() }})</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Service</th>
                                    <th>Dentist</th>
                                    <th>Status</th>
                                    <th>Treatment</th>
                                    @if(auth()->user()->isDentist())<th class="no-print">Actions</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($patient->patientAppointments as $appt)
                                    <tr>
                                        <td>{{ $appt->appointment_date->format('M d, Y') }}</td>
                                        <td>{{ $appt->formatted_slot }}</td>
                                        <td>{{ $appt->service_type }}</td>
                                        <td>{{ $appt->dentist->name ?? '—' }}</td>
                                        <td>@include('partials.status-badge', ['status' => $appt->status])</td>
                                        <td>
                                            @if($appt->treatmentRecord)
                                                <button type="button" class="btn btn-sm btn-link p-0" data-bs-toggle="modal"
                                                        data-bs-target="#treatmentViewModal"
                                                        data-details="{{ $appt->treatmentRecord->treatment_details }}"
                                                        data-notes="{{ $appt->treatmentRecord->clinical_notes ?? '' }}"
                                                        data-date="{{ $appt->appointment_date->format('M d, Y') }}">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </button>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        @if(auth()->user()->isDentist())
                                            <td class="no-print">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                        data-bs-toggle="modal" data-bs-target="#treatmentModal"
                                                        data-appointment-id="{{ $appt->id }}"
                                                        data-date="{{ $appt->appointment_date->format('M d, Y') }}"
                                                        data-slot="{{ $appt->formatted_slot }}"
                                                        data-service="{{ $appt->service_type }}"
                                                        data-details="{{ $appt->treatmentRecord?->treatment_details ?? '' }}"
                                                        data-notes="{{ $appt->treatmentRecord?->clinical_notes ?? '' }}">
                                                    <i class="bi bi-pencil-square me-1"></i>{{ $appt->treatmentRecord ? 'Update' : 'Add' }} Treatment
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-calendar-x d-block fs-3 mb-2"></i>
                                            No consultations recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Treatment record modal (Dentist only) --}}
    @if(auth()->user()->isDentist())
        <div class="modal fade" id="treatmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('patients.treatment.store', $patient) }}" id="treatmentForm">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-primary"></i>Treatment Record</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="appointment_id" id="treatmentAppointmentId">
                            <div class="alert alert-light border small mb-3" id="treatmentApptInfo"></div>

                            <div class="mb-3">
                                <label for="treatment_details" class="form-label">Treatment details <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="treatment_details" name="treatment_details" rows="3"
                                          placeholder="e.g. Scaling and polishing performed; no complications." required></textarea>
                            </div>
                            <div class="mb-2">
                                <label for="clinical_notes" class="form-label">Clinical notes</label>
                                <textarea class="form-control" id="clinical_notes" name="clinical_notes" rows="3"
                                          placeholder="e.g. Patient advised to avoid hot drinks for 24 hours."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Treatment record viewer modal --}}
    <div class="modal fade" id="treatmentViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-journal-medical me-2 text-primary"></i>Treatment Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-3" id="viewApptDate"></div>
                    <div class="mb-3">
                        <div class="fw-semibold small text-muted">Treatment Details</div>
                        <div id="viewDetails" class="border rounded p-3 bg-light"></div>
                    </div>
                    <div>
                        <div class="fw-semibold small text-muted">Clinical Notes</div>
                        <div id="viewNotes" class="border rounded p-3 bg-light"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // ---- Dentist treatment modal ----
    const treatmentModal = document.getElementById('treatmentModal');
    if (treatmentModal) {
        treatmentModal.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            document.getElementById('treatmentAppointmentId').value = btn.dataset.appointmentId;
            document.getElementById('treatment_details').value = btn.dataset.details || '';
            document.getElementById('clinical_notes').value = btn.dataset.notes || '';
            document.getElementById('treatmentApptInfo').innerHTML =
                `<i class="bi bi-calendar3 me-1"></i>${btn.dataset.date} &middot; ${btn.dataset.slot} &middot; ${btn.dataset.service}`;
        });
    }

    // ---- Treatment viewer modal ----
    document.getElementById('treatmentViewModal').addEventListener('show.bs.modal', (e) => {
        const btn = e.relatedTarget;
        document.getElementById('viewApptDate').textContent = btn.dataset.date;
        document.getElementById('viewDetails').textContent = btn.dataset.details;
        document.getElementById('viewNotes').textContent = btn.dataset.notes || 'No clinical notes.';
    });
</script>
@endpush
