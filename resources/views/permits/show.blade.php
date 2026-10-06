@extends('layouts.app')

@section('title', 'Mayor\'s Permit')

@section('content')
    <div class="page-header">
        <h1>Mayor's Permit</h1>
        <div>
            <a href="{{ route('applications.permit.download', $application) }}" class="btn">Download PDF</a>
            <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
        </div>
    </div>

    <div class="card">
        <div class="flex-between" style="align-items:flex-start">
            <div>
                <h2>{{ config('permit.municipality') }}</h2>
                <p class="muted mb-0">{{ config('permit.province') }}</p>
            </div>
            <div class="qr-box">
                {!! $qrSvg !!}
                <p class="muted" style="font-size:0.8rem">Scan to verify</p>
            </div>
        </div>

        <dl class="kv" style="margin-top:20px">
            <dt>Permit Number</dt><dd>{{ $permit->permit_number }}</dd>
            <dt>Business Name</dt><dd>{{ $permit->business_name }}</dd>
            <dt>Owner</dt><dd>{{ $permit->owner_name }}</dd>
            <dt>Business Address</dt><dd>{{ $permit->business_address }}</dd>
            <dt>Nature of Business</dt><dd>{{ $permit->business_nature }}</dd>
            <dt>Date Issued</dt><dd>{{ $permit->issued_at?->format('F d, Y') }}</dd>
            <dt>Valid Until</dt><dd>{{ $permit->valid_until?->format('F d, Y') }}</dd>
            <dt>Status</dt><dd>{{ $permit->status }}</dd>
        </dl>

        <p class="muted" style="margin-top:16px">
            Verify this permit at <a href="{{ $verifyUrl }}">{{ $verifyUrl }}</a>
        </p>
    </div>
@endsection
