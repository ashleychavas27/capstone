@extends('layouts.app')

@section('title', 'e-Prescriptions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">e-Prescription</h4>
            <p class="text-muted small mb-0">Digital reseta records issued by the dentist</p>
        </div>
        @if(auth()->user()->isDentist())
            <a href="{{ route('prescriptions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>New Prescription
            </a>
        @endif
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="prescriptionsTable" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Date Issued</th>
                            <th>Patient</th>
                            <th>Dentist</th>
                            <th>Diagnosis</th>
                            <th>Meds</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        const canDelete = @json(auth()->user()->isOwner() || auth()->user()->isDentist());

        const table = $('#prescriptionsTable').DataTable({
            ...dtDefaults,
            ajax: {
                url: '{{ route('prescriptions.data') }}',
                dataSrc: 'data',
                error: () => showToast('Failed to load prescriptions.', 'danger')
            },
            order: [[0, 'desc']],
            columns: [
                { data: 'date_issued' },
                { data: 'patient' },
                { data: 'dentist' },
                { data: 'diagnosis' },
                { data: 'medications', className: 'text-center' },
                {
                    data: null,
                    className: 'text-end no-print',
                    orderable: false,
                    render: (d) => `
                        <div class="btn-group btn-group-sm">
                            <a class="btn btn-outline-secondary" href="/prescriptions/${d.id}/print" target="_blank" title="Print reseta">
                                <i class="bi bi-printer"></i>
                            </a>
                            ${d.can_edit ? `<a class="btn btn-outline-primary" href="/prescriptions/${d.id}/edit" title="Edit"><i class="bi bi-pencil-square"></i></a>` : ''}
                            ${canDelete ? `<button class="btn btn-outline-danger js-delete" data-id="${d.id}" title="Delete"><i class="bi bi-trash"></i></button>` : ''}
                        </div>`
                }
            ],
            language: {
                ...dtDefaults.language,
                emptyTable: 'No prescriptions issued yet.'
            }
        });

        $('#prescriptionsTable').on('click', '.js-delete', function () {
            const id = $(this).data('id');
            confirmAction('Permanently delete this prescription?', async () => {
                try {
                    await apiFetch(`/prescriptions/${id}`, { method: 'DELETE' });
                    showToast('Prescription deleted.');
                    table.ajax.reload();
                } catch (e) {
                    showToast(e.message, 'danger');
                }
            });
        });
    });
</script>
@endpush
