<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Prescription #{{ $prescription->id }} · {{ $prescription->patient->name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #FAF7F5;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .sheet {
            max-width: 800px;
            margin: 2rem auto;
            background: #fff;
            border: 1px solid #B4B1B2;
            padding: 2.5rem 3rem;
        }

        .clinic-name {
            font-family: Georgia, 'Times New Roman', serif;
            color: #736261;
            letter-spacing: .04em;
        }

        .rx-symbol {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 3rem;
            color: #736261;
            line-height: 1;
        }

        .med-item { margin-bottom: 1rem; }
        .med-item .drug { font-weight: 600; }

        .sig-line {
            display: inline-block;
            border-top: 1px solid #736261;
            min-width: 260px;
            padding-top: .25rem;
            text-align: center;
            margin-top: 4.5rem;
        }

        @media print {
            body { background: #fff; }
            .sheet { border: none; margin: 0; max-width: none; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="text-end mb-3 no-print" style="max-width: 800px; margin-inline: auto;">
        @auth
            @if(auth()->user()->isStaff())
                <a href="{{ route('prescriptions.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
            @endif
        @endauth
        <button onclick="window.print()" class="btn btn-sm btn-primary">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>

    <div class="sheet">
        <div class="d-flex align-items-center gap-3 pb-3 mb-4" style="border-bottom: 2px solid #C89B27;">
            <img src="{{ asset('logo.png') }}" alt="Logo" style="width: 78px; height: 78px; object-fit: contain;">
            <div>
                <h4 class="clinic-name fw-bold mb-0">{{ config('app.name', 'Dental Clinic') }}</h4>
                <div class="small text-muted">Online Records &amp; Appointment System</div>
                <div class="small text-muted">Mon–Sat · 9:00 AM – 5:00 PM · Contact: (02) 8123-4567</div>
            </div>
        </div>

        {{-- Patient block --}}
        <div class="row small mb-4">
            <div class="col-6"><strong>Patient:</strong> {{ $prescription->patient->name }}</div>
            <div class="col-3"><strong>Age:</strong> {{ $prescription->patient->patientProfile?->age ?? '—' }}</div>
            <div class="col-3"><strong>Date:</strong> {{ $prescription->date_issued->format('M d, Y') }}</div>
            <div class="col-6"><strong>Address:</strong> {{ $prescription->patient->patientProfile?->address ?? '—' }}</div>
            @if($prescription->appointment)
                <div class="col-6"><strong>Consultation:</strong> {{ $prescription->appointment->appointment_date->format('M d, Y') }} · {{ $prescription->appointment->service_type }}</div>
            @endif
        </div>

        {{-- Diagnosis --}}
        <p class="mb-4 small"><strong>Diagnosis:</strong> {{ $prescription->diagnosis }}</p>

        {{-- Rx body --}}
        <div class="d-flex gap-3">
            <span class="rx-symbol">&#8477;</span>
            <div class="flex-grow-1 pt-2">
                @forelse($prescription->items as $i => $item)
                    <div class="med-item ps-2" style="border-left: 3px solid #B4B1B2;">
                        <div>{{ $i + 1 }}. <span class="drug">{{ $item->drug_name }}</span></div>
                        <div class="small ms-3">
                            @if($item->dosage){{ $item->dosage }}@endif
                            @if($item->frequency)&nbsp;&middot;&nbsp;{{ $item->frequency }}@endif
                            @if($item->duration)&nbsp;&middot;&nbsp;for {{ $item->duration }}@endif
                            @if($item->quantity)&nbsp;&middot;&nbsp;Qty: {{ $item->quantity }}@endif
                            @if(!collect([$item->dosage, $item->frequency, $item->duration, $item->quantity])->filter()->count())
                                &mdash;
                            @endif
                        </div>
                        @if($item->instructions)
                            <div class="small fst-italic ms-3">Sig: {{ $item->instructions }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted small">No medications listed.</p>
                @endforelse
            </div>
        </div>

        @if($prescription->notes)
            <div class="border rounded p-3 mt-4 small bg-white">
                <strong>Notes:</strong><br>{{ $prescription->notes }}
            </div>
        @endif

        {{-- Doctor signature --}}
        <div class="text-end mt-4">
            <div class="sig-line">
                <div class="fw-semibold">{{ $prescription->dentist->name }}, DMD</div>
                <div class="small text-muted">License No. {{ $prescription->dentist->license_no ?? '—' }}</div>
                <div class="small text-muted">Attending Dentist</div>
            </div>
        </div>

        <div class="text-center text-muted small mt-4">
            This prescription is system-generated by {{ config('app.name', 'Dental Clinic') }} e-Prescription module.
        </div>
    </div>
</body>
</html>
