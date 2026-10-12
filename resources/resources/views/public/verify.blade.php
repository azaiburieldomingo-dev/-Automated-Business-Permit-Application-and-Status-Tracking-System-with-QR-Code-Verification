@extends('layouts.guest')

@section('title', 'Verify Permit — '.config('permit.municipality'))

@php
    $map = [
        'VALID' => ['verify-valid', '✓ VALID PERMIT', 'This permit record is registered in the '.config('permit.municipality').' system.'],
        'EXPIRED' => ['verify-expired', '⚠ EXPIRED PERMIT', 'This permit record is registered but is no longer active.'],
        'REVOKED' => ['verify-revoked', '✕ REVOKED PERMIT', 'This permit record has been revoked and is no longer valid.'],
        'NOT_FOUND' => ['verify-notfound', '✕ INVALID / NOT FOUND', 'This QR code does not correspond to a valid registered permit record.'],
    ];
    [$class, $label, $message] = $map[$result] ?? $map['NOT_FOUND'];
@endphp

@section('content')
    <div style="max-width:560px;margin:0 auto">
        <h1 class="text-center">PERMIT VERIFICATION</h1>
        <div class="verify-result {{ $class }}">
            <div class="status">{{ $label }}</div>
            <p class="mb-0">{{ $message }}</p>
        </div>

        @if ($permit && $result !== 'NOT_FOUND')
            {{-- Data-minimized per RA 10173: no email, contact, or DTI/SEC data. --}}
            <div class="card">
                <dl class="kv">
                    <dt>Permit Number</dt><dd>{{ $permit->permit_number }}</dd>
                    <dt>Business Name</dt><dd>{{ $permit->business_name }}</dd>
                    <dt>Business Address</dt><dd>{{ $permit->business_address }}</dd>
                    <dt>Status</dt><dd>{{ $permit->status }}</dd>
                    <dt>Date Issued</dt><dd>{{ $permit->issued_at?->format('F d, Y') }}</dd>
                    <dt>Valid Until</dt><dd>{{ $permit->valid_until?->format('F d, Y') }}</dd>
                </dl>
            </div>
        @endif

        <p class="text-center"><a href="{{ url('/') }}">{{ config('permit.municipality') }}</a></p>
    </div>
@endsection
