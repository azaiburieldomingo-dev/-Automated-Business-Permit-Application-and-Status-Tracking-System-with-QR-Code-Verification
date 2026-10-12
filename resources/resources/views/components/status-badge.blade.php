@props(['status'])

@php
    $map = [
        \App\Models\Application::STATUS_DRAFT => ['badge-gray', 'Draft'],
        \App\Models\Application::STATUS_SUBMITTED => ['badge-blue', 'Submitted'],
        \App\Models\Application::STATUS_UNDER_REVIEW => ['badge-blue', 'Under Review'],
        \App\Models\Application::STATUS_FOR_CORRECTION => ['badge-yellow', 'For Correction'],
        \App\Models\Application::STATUS_APPROVED_FOR_PAYMENT => ['badge-blue', 'Approved for Payment'],
        \App\Models\Application::STATUS_PAYMENT_PENDING => ['badge-yellow', 'Payment Pending'],
        \App\Models\Application::STATUS_PAYMENT_VERIFIED => ['badge-green', 'Payment Verified'],
        \App\Models\Application::STATUS_PERMIT_RELEASED => ['badge-green', 'Permit Released'],
        \App\Models\Application::STATUS_REJECTED => ['badge-red', 'Rejected'],
    ];
    [$class, $label] = $map[$status] ?? ['badge-gray', $status];
@endphp

<span class="badge {{ $class }}">{{ $label }}</span>
