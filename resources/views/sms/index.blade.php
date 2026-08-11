@extends('layouts.app')

@section('title', 'SMS Notifications')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">SMS Notifications</h4>
            <p class="text-muted small mb-0">Automated reminders &amp; message history (sms_logs)</p>
        </div>
        <button type="button" class="btn btn-primary no-print" data-bs-toggle="modal" data-bs-target="#sendSmsModal">
            <i class="bi bi-send me-1"></i>Send SMS
        </button>
    </div>

    <div class="alert alert-info small d-flex align-items-center gap-2 no-print">
        <i class="bi bi-lightbulb"></i>
        <span>Reminders are sent automatically whenever an appointment is marked <strong>Confirmed</strong>. This page lets you review the history or send a manual message.</span>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="smsTable" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Recipient</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Sent At</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Manual send modal --}}
    <div class="modal fade" id="sendSmsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('sms.send') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-send me-2 text-primary"></i>Send SMS</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="recipient_phone" class="form-label">Recipient phone <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="recipient_phone" name="recipient_phone"
                                   placeholder="09XX XXX XXXX" list="patientPhones" required>
                            <datalist id="patientPhones">
                                @foreach($patients as $patient)
                                    <option value="{{ $patient->contact_no }}">{{ $patient->name }}</option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="mb-2">
                            <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="4" maxlength="160"
                                      placeholder="Your message (max 160 characters)" required></textarea>
                            <div class="form-text text-end" id="charCount">0 / 160</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#smsTable').DataTable({
            ...dtDefaults,
            order: [[0, 'desc']],
            ajax: {
                url: '{{ route('sms.data') }}',
                dataSrc: 'data',
                error: () => showToast('Failed to load SMS logs.', 'danger')
            },
            columns: [
                { data: 'id' },
                { data: 'recipient_phone' },
                { data: 'message', render: (d) => `<span class="d-inline-block text-truncate" style="max-width:380px" title="${d}">${d}</span>` },
                {
                    data: 'status',
                    render: (d) => `<span class="badge rounded-pill text-bg-${d === 'Failed' ? 'danger' : 'success'}">${d}</span>`
                },
                { data: 'sent_at' }
            ],
            language: { ...dtDefaults.language, emptyTable: 'No SMS messages logged yet.' }
        });

        // Character counter
        const msg = document.getElementById('message');
        msg.addEventListener('input', () => {
            document.getElementById('charCount').textContent = `${msg.value.length} / 160`;
        });
    });
</script>
@endpush
