@extends('layouts.app')

@section('title', $prescription ? 'Edit Prescription' : 'New Prescription')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
        <div>
            <h4 class="fw-bold mb-0">{{ $prescription ? 'Edit e-Prescription' : 'New e-Prescription' }}</h4>
            <p class="text-muted small mb-0">{{ $prescription ? 'Update the details of this reseta' : 'Issue a reseta for a patient' }}</p>
        </div>
        <a href="{{ route('prescriptions.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Prescriptions
        </a>
    </div>

    <form method="POST"
          action="{{ $prescription ? route('prescriptions.update', $prescription) : route('prescriptions.store') }}"
          id="prescriptionForm">
        @csrf
        @if($prescription)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-person-vcard me-2 text-primary"></i>1. Patient &amp; Consultation</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                            <select name="patient_id" id="patient_id" class="form-select @error('patient_id') is-invalid @enderror" required>
                                <option value="">Select patient…</option>
                                @foreach($patients as $patient)
                                    <option value="{{ $patient->id }}"
                                        @selected(old('patient_id', ($prescription?->patient_id ?? $selectedPatient?->id)) == $patient->id)>
                                        {{ $patient->name }} ({{ $patient->contact_no ?? 'no contact' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('patient_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="appointment_id" class="form-label">Linked appointment <span class="text-muted small">(optional)</span></label>
                            <select name="appointment_id" id="appointment_id" class="form-select">
                                <option value="">None</option>
                                @foreach($appointments as $appt)
                                    <option value="{{ $appt->id }}"
                                        data-patient="{{ $appt->patient_id }}"
                                        @selected(old('appointment_id', $prescription?->appointment_id) == $appt->id)>
                                        {{ $appt->appointment_date->format('M d, Y') }} · {{ $appt->formatted_slot }} · {{ $appt->patient->name }} · {{ $appt->service_type }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="date_issued" class="form-label">Date issued <span class="text-danger">*</span></label>
                            <input type="date" name="date_issued" id="date_issued"
                                   value="{{ old('date_issued', ($prescription?->date_issued ?? now())->format('Y-m-d')) }}"
                                   class="form-control @error('date_issued') is-invalid @enderror" required>
                            @error('date_issued')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="diagnosis" class="form-label">Diagnosis <span class="text-danger">*</span></label>
                            <textarea name="diagnosis" id="diagnosis" rows="2"
                                      class="form-control @error('diagnosis') is-invalid @enderror"
                                      placeholder="e.g. Chronic gingivitis; dental caries #14" required>{{ old('diagnosis', $prescription?->diagnosis) }}</textarea>
                            @error('diagnosis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-0">
                            <label for="notes" class="form-label">Additional notes</label>
                            <textarea name="notes" id="notes" rows="3"
                                      class="form-control @error('notes') is-invalid @enderror"
                                      placeholder="e.g. Return after 7 days for follow-up.">{{ old('notes', $prescription?->notes) }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-capsule me-2 text-primary"></i>2. Medications (Rx)</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addMedRow">
                            <i class="bi bi-plus-lg me-1"></i>Add Medication
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="medicationRows">
                            @if($prescription && $prescription->items->isNotEmpty())
                                @foreach($prescription->items as $item)
                                    <fieldset class="med-row border rounded p-3 mb-3 position-relative">
                                        <button type="button" class="btn-close position-absolute top-0 end-0 m-2 js-remove-med" aria-label="Remove medication"></button>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="form-label small mb-1">Drug / medicine <span class="text-danger">*</span></label>
                                                <input type="text" name="drug_name[]" value="{{ $item->drug_name }}" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small mb-1">Dosage</label>
                                                <input type="text" name="dosage[]" value="{{ $item->dosage }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Frequency</label>
                                                <input type="text" name="frequency[]" value="{{ $item->frequency }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Duration</label>
                                                <input type="text" name="duration[]" value="{{ $item->duration }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Quantity</label>
                                                <input type="text" name="quantity[]" value="{{ $item->quantity }}" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small mb-1">Instructions (sig.)</label>
                                                <input type="text" name="instructions[]" value="{{ $item->instructions }}" class="form-control form-control-sm">
                                            </div>
                                        </div>
                                    </fieldset>
                                @endforeach
                            @else
                                {{-- One empty starter row rendered by JS on load --}}
                            @endif
                        </div>

                        <template id="medRowTemplate">
                            <fieldset class="med-row border rounded p-3 mb-3 position-relative">
                                <button type="button" class="btn-close position-absolute top-0 end-0 m-2 js-remove-med" aria-label="Remove medication"></button>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">Drug / medicine <span class="text-danger">*</span></label>
                                        <input type="text" name="drug_name[]" class="form-control form-control-sm" placeholder="e.g. Amoxicillin 500mg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">Dosage <span class="text-muted small">(e.g. 500 mg)</span></label>
                                        <input type="text" name="dosage[]" class="form-control form-control-sm" placeholder="e.g. 500 mg">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Frequency</label>
                                        <input type="text" name="frequency[]" class="form-control form-control-sm" placeholder="e.g. 3x a day">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Duration</label>
                                        <input type="text" name="duration[]" class="form-control form-control-sm" placeholder="e.g. 7 days">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Quantity</label>
                                        <input type="text" name="quantity[]" class="form-control form-control-sm" placeholder="e.g. 21 capsules">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small mb-1">Instructions (sig.)</label>
                                        <input type="text" name="instructions[]" class="form-control form-control-sm" placeholder="e.g. Take 1 capsule 3 times daily after meals">
                                    </div>
                                </div>
                            </fieldset>
                        </template>

                        @error('drug_name.*')
                            <div class="alert alert-danger py-2 small mb-0 mt-2">{{ $message }}</div>
                        @enderror

                        <p class="text-muted small mb-0 mt-2">
                            <i class="bi bi-info-circle me-1"></i>List at least one medication. Include dosage and sig. exactly as they should appear on the printed reseta.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 no-print">
            <a href="{{ route('prescriptions.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2 me-1"></i>{{ $prescription ? 'Save Changes' : 'Issue Prescription' }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    $(function () {
        const template = document.getElementById('medRowTemplate');
        const container = $('#medicationRows');

        const addRow = () => container.append(template.content.cloneNode(true));

        // Starter row when creating a new prescription.
        if (container.children('.med-row').length === 0) {
            addRow();
        }

        $('#addMedRow').on('click', addRow);

        container.on('click', '.js-remove-med', function () {
            if (container.children('.med-row').length > 1) {
                $(this).closest('.med-row').remove();
            } else {
                showToast('A prescription needs at least one medication.', 'warning');
            }
        });

        // When a linked appointment is picked, auto-select its patient.
        $('#appointment_id').on('change', function () {
            const option = this.selectedOptions[0];
            if (option && option.dataset.patient) {
                $('#patient_id').val(option.dataset.patient);
            }
        });

        // On validation errors, scroll to and focus the first problem field.
        const firstError = document.querySelector('#prescriptionForm .is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus({ preventScroll: true });
        }
    });
</script>
@endpush
