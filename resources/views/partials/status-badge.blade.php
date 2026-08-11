@php
    $classes = [
        'Pending'   => 'text-bg-warning',
        'Confirmed' => 'text-bg-success',
        'Completed' => 'text-bg-primary',
        'Cancelled' => 'text-bg-secondary',
    ];
    $icons = [
        'Pending'   => 'bi-hourglass-split',
        'Confirmed' => 'bi-check2-circle',
        'Completed' => 'bi-check2-all',
        'Cancelled' => 'bi-x-circle',
    ];
@endphp

<span class="badge rounded-pill badge-status {{ $classes[$status] ?? 'text-bg-secondary' }}">
    <i class="bi {{ $icons[$status] ?? 'bi-question-circle' }} me-1"></i>{{ $status }}
</span>
