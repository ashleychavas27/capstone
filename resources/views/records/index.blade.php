@extends('layouts.app')

@section('title', 'My Records')

@section('content')
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-journal-medical"></i></div>
        <div>
            <h4 class="fw-bold mb-0">My Records</h4>
            <p class="text-muted small mb-0">Your appointment history, diagnostic records and e-prescriptions.</p>
        </div>
    </div>

    <div class="row g-4">
        @forelse($appointments as $appt)
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>
                            <i class="bi bi-calendar2-check me-2 text-primary"></i>
                            {{ $appt->appointment_date->format('l, M d, Y') }} at {{ $appt->formatted_slot }}
                        </span>
                        <span class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="text-muted small">{{ $appt->service_type }} · {{ $appt->dentist->name ?? '—' }}</span>
                            @include('partials.status-badge', ['status' => $appt->status])
                        </span>
                    </div>
                    <div class="card-body">
                        @if($appt->treatmentRecord)
                            <div class="border rounded p-3 mb-3 bg-light">
                                <h6 class="fw-bold text-primary mb-2"><i class="bi bi-clipboard2-pulse me-1"></i>Diagnostic / Treatment Record</h6>
                                <p class="mb-1 small">{{ $appt->treatmentRecord->treatment_details }}</p>
                                @if($appt->treatmentRecord->clinical_notes)
                                    <p class="mb-0 text-muted small"><em>Notes: {{ $appt->treatmentRecord->clinical_notes }}</em></p>
                                @endif
                            </div>
                        @else
                            <p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i>No diagnostic record yet for this visit.</p>
                        @endif

                        @if($appt->prescriptions->isNotEmpty())
                            @foreach($appt->prescriptions as $rx)
                                <div class="border rounded p-3">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                        <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-file-earmark-text me-1"></i>e-Prescription · {{ $rx->date_issued->format('M d, Y') }}</h6>
                                        <a href="{{ route('records.prescription.print', $rx) }}" target="_blank" class="btn btn-sm btn-outline-primary no-print">
                                            <i class="bi bi-printer me-1"></i>Print / View
                                        </a>
                                    </div>
                                    <p class="small mb-2"><strong>Diagnosis:</strong> {{ $rx->diagnosis }}</p>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr><th>#</th><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Instructions</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach($rx->items as $i => $item)
                                                    <tr>
                                                        <td>{{ $i + 1 }}</td>
                                                        <td class="fw-semibold">{{ $item->drug_name }}</td>
                                                        <td>{{ $item->dosage ?? '—' }}</td>
                                                        <td>{{ $item->frequency ?? '—' }}</td>
                                                        <td>{{ $item->duration ?? '—' }}</td>
                                                        <td class="small">{{ $item->instructions ?? '—' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @if($rx->notes)
                                        <p class="text-muted small mb-0 mt-2"><strong>Notes:</strong> {{ $rx->notes }}</p>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-journal-x d-block fs-3 mb-2"></i>
                        You have no appointment records yet. Book an appointment to get started.
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection