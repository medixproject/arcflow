@php
    $pillLabels = [
        'pending' => 'Awaiting payment', 'detected' => 'Detected', 'confirming' => 'Confirming',
        'paid' => 'Paid', 'confirmed' => 'Confirmed', 'underpaid' => 'Partially paid',
        'overpaid' => 'Overpaid', 'late_review' => 'Needs review', 'expired' => 'Expired', 'cancelled' => 'Cancelled',
    ];
    $pillKey = preg_replace('/[^a-z_]/', '', strtolower((string) $status));
@endphp
<span class="status {{ $pillKey }}">{{ $pillLabels[$pillKey] ?? ucfirst(str_replace('_', ' ', $pillKey)) }}</span>
