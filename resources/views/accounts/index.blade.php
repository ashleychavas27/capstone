@extends('layouts.app')

@section('title', 'Staff Accounts')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Staff Accounts</h4>
            <p class="text-muted small mb-0">Create and manage Owner, Secretary &amp; Dentist logins</p>
        </div>
        <a href="{{ route('accounts.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>New Account
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="accountsTable" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Contact No.</th>
                            <th>License No.</th>
                            <th>Duty days</th>
                            <th>Status</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-light border small mt-3 no-print">
        <i class="bi bi-info-circle me-1"></i>
        Deactivating an account blocks its login immediately but keeps all past appointments and records. Patient
        accounts are created through public registration and are managed from the Patients page.
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        const roleBadges = {
            Owner: 'badge-status-confirmed',
            Secretary: 'badge-status-completed',
            Dentist: 'badge-status-pending'
        };

        const table = $('#accountsTable').DataTable({
            ...dtDefaults,
            ajax: {
                url: '{{ route('accounts.data') }}',
                dataSrc: 'data',
                error: () => showToast('Failed to load accounts.', 'danger')
            },
            order: [[1, 'asc']],
            columns: [
                { data: 'name' },
                {
                    data: 'role',
                    render: (d) => `<span class="badge rounded-pill badge-status ${roleBadges[d] || 'text-bg-secondary'}">${d}</span>`
                },
                { data: 'email' },
                { data: 'contact_no', render: (d) => d || '—' },
                { data: 'license_no', render: (d) => d || '—' },
                { data: 'duty_days', render: (d) => d || '—' },
                {
                    data: 'is_active',
                    render: (active) => active
                        ? '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Active</span>'
                        : '<span class="text-danger"><i class="bi bi-slash-circle me-1"></i>Deactivated</span>'
                },
                {
                    data: null,
                    className: 'text-end no-print',
                    orderable: false,
                    render: (d) => `
                        <div class="btn-group btn-group-sm">
                            <a class="btn btn-outline-primary" href="/accounts/${d.id}/edit" title="Edit account">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            ${Number(d.id) !== {{ auth()->id() }} ? `
                                <button class="btn btn-outline-secondary js-toggle" data-id="${d.id}" data-active="${d.is_active}"
                                        title="${d.is_active ? 'Deactivate' : 'Reactivate'} account">
                                    <i class="bi ${d.is_active ? 'bi-slash-circle' : 'bi-arrow-counterclockwise'}"></i>
                                </button>` : ''}
                        </div>`
                }
            ],
            language: {
                ...dtDefaults.language,
                emptyTable: 'No staff accounts yet.'
            }
        });

        $('#accountsTable').on('click', '.js-toggle', function () {
            const btn = $(this);
            const id = btn.data('id');
            const isActive = btn.data('active') === true || btn.data('active') === 'true';
            confirmAction(
                isActive ? 'Deactivate this account? The user will no longer be able to sign in.' : 'Reactivate this account?',
                async () => {
                    try {
                        const res = await apiFetch(`/accounts/${id}/toggle-active`, { method: 'PATCH' });
                        showToast(res.message);
                        table.ajax.reload();
                    } catch (e) {
                        showToast(e.message, 'danger');
                    }
                }
            );
        });
    });
</script>
@endpush
