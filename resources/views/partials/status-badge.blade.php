@php
    $classes = [
        'Pending'   => 'badge-status-pending',
        'Confirmed' => 'badge-status-confirmed',
        'Completed' => 'badge-status-completed',
        'Cancelled' => 'badge-status-cancelled',
    ];
    $icons = [
        'Pending'   => 'bi-hourglass-split',
        'Confirmed' => 'bi-check2-circle',
        'Completed' => 'bi-check2-all',
        'Cancelled' => 'bi-x-circle',
    ];
    $hints = [
        'Pending'   => 'Waiting for the secretary to confirm this booking.',
        'Confirmed' => 'Confirmed by the clinic. An SMS reminder was sent.',
        'Completed' => 'Finished — treatment record has been saved.',
        'Cancelled' => 'This appointment was cancelled.',
    ];
@endphp

<span class="badge rounded-pill badge-status {{ $classes[$status] ?? 'badge-status-cancelled' }}" title="{{ $hints[$status] ?? '' }}">
    <i class="bi {{ $icons[$status] ?? 'bi-question-circle' }} me-1"></i>{{ $status }}
</span>
