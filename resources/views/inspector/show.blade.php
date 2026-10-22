@extends('layouts.app')

@section('title', 'Permit Details')

@section('content')
    @php
        $isExpired = $permit->valid_until !== null && $permit->valid_until->isPast();
        $isRevoked = $permit->status === \App\Models\Permit::STATUS_REVOKED;
        if ($isRevoked) {
            $indicator = ['REVOKED', 'badge-red'];
        } elseif ($isExpired) {
            $indicator = ['EXPIRED', 'badge-yellow'];
        } else {
            $indicator = ['ACTIVE', 'badge-green'];
        }
    @endphp

    <div class="page-header">
        <div>
            <h1>Permit Details</h1>
            <p class="muted mb-0">Permit Number: <strong>{{ $permit->permit_number }}</strong></p>
        </div>
        <span class="badge {{ $indicator[1] }}">{{ $indicator[0] }}</span>
    </div>

    <div class="card">
        <h2>Business Information</h2>
        <dl class="kv">
            <dt>Business Name</dt><dd>{{ $permit->business_name }}</dd>
            <dt>Owner</dt><dd>{{ $permit->owner_name }}</dd>
            <dt>Business Address</dt><dd>{{ $permit->business_address }}</dd>
            <dt>Nature</dt><dd>{{ $permit->business_nature }}</dd>
            <dt>Status</dt><dd>{{ $permit->status }}</dd>
            <dt>Issued</dt><dd>{{ $permit->issued_at?->format('M d, Y') ?? '—' }}</dd>
            <dt>Valid Until</dt><dd>{{ $permit->valid_until?->format('M d, Y') ?? '—' }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>Verification</h2>
        <p class="muted">Open the public verification page for this permit (also what the QR code points to).</p>
        <dl class="kv">
            <dt>Verification Link</dt>
            <dd style="word-break:break-all">{{ $permit->verifyUrl() }}</dd>
        </dl>
        <a href="{{ $permit->verifyUrl() }}" class="btn btn-mint" target="_blank" rel="noopener">Open Verification Page</a>
    </div>

    <div class="card">
        <h2>Scan History</h2>
        @if ($permit->qrVerifications->isEmpty())
            <p class="muted">No scans recorded for this permit yet.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>Time</th><th>Result</th><th>IP Address</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($permit->qrVerifications as $scan)
                            @php
                                $badge = match ($scan->result) {
                                    'VALID' => 'badge-green',
                                    'EXPIRED' => 'badge-yellow',
                                    'REVOKED', 'NOT_FOUND' => 'badge-red',
                                    default => 'badge-gray',
                                };
                            @endphp
                            <tr>
                                <td>{{ $scan->created_at?->format('M d, Y g:i A') }}</td>
                                <td><span class="badge {{ $badge }}">{{ $scan->result }}</span></td>
                                <td>{{ $scan->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($permit->application)
        <x-process-tracker :application="$permit->application" viewer="inspector" />
    @endif

    <p><a href="{{ url('/inspector/permits') }}" class="btn btn-secondary">Back to Permits</a></p>
@endsection
