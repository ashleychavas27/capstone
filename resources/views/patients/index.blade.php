@extends('layouts.app')

@section('title', 'Patient Records')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Patient Records</h4>
            <p class="text-muted small mb-0">Searchable registry of all registered patients</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="patientsTable" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact No.</th>
                            <th>Age</th>
                            <th>Address</th>
                            <th>Appointments</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Rows injected client-side from /patients/data --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        const table = $('#patientsTable').DataTable({
            ...dtDefaults,
            ajax: {
                url: '{{ route('patients.data') }}',
                dataSrc: 'data',
                error: function (xhr) {
                    showToast('Failed to load patients.', 'danger');
                }
            },
            order: [[1, 'asc']],
            columns: [
                { data: 'id', width: '50px' },
                { data: 'name' },
                { data: 'email' },
                { data: 'contact_no' },
                { data: 'age', render: (d) => d ?? '—' },
                {
                    data: 'address',
                    render: (d) => d ? `<span class="d-inline-block text-truncate" style="max-width:200px" title="${d}">${d}</span>` : '—'
                },
                { data: 'total_appointments', className: 'text-center' },
                {
                    data: 'actions',
                    className: 'text-end no-print',
                    orderable: false,
                    render: (url) => `
                        <a href="${url}" class="btn btn-sm btn-outline-primary" title="View record">
                            <i class="bi bi-eye"></i> View
                        </a>`
                }
            ],
            language: {
                ...dtDefaults.language,
                emptyTable: 'No patients found.',
                loadingRecords: 'Loading patients…'
            }
        });

        // Re-apply our custom search styling (dtDefaults.language.search uses an icon).
        $('#patientsTable_filter input').attr('placeholder', 'Search name, email, contact…').addClass('form-control-sm');

        // Click anywhere on a row to open the patient record.
        $('#patientsTable tbody').on('click', 'tr', function (e) {
            if ($(e.target).closest('a, button').length) return;
            const data = table.row(this).data();
            if (data?.actions) window.location.href = data.actions;
        });
    });
</script>
@endpush
