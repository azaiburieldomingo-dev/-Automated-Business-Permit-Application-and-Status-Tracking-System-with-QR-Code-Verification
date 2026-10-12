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
                <a href="{{ $verifyUrl }}" target="_blank" rel="noopener" title="Open the verification page" aria-label="Open the permit verification page">
                    {!! $qrSvg !!}
                </a>
                <p class="muted" style="font-size:0.8rem">Scan with a phone camera, or click to open</p>
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

        <div class="verify-link" style="margin-top:18px">
            <label for="verify-url" style="margin-bottom:4px">Verification link</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <input id="verify-url" type="text" value="{{ $verifyUrl }}" readonly style="flex:1;min-width:240px;font-family:monospace;font-size:0.85rem" onclick="this.select()">
                <a href="{{ $verifyUrl }}" target="_blank" rel="noopener" class="btn">Open Verification Page</a>
                <button type="button" class="btn btn-secondary" id="copy-verify-url">Copy Link</button>
            </div>
        </div>

        @if (preg_match('#^https?://(localhost|127\.0\.0\.1)(:|/|$)#i', $verifyUrl))
            <div class="alert alert-warning" style="margin-top:14px">
                This QR code points to <strong>localhost</strong>, so it only opens on this computer.
                Set <code>PERMIT_PUBLIC_URL</code> to an address phones can reach.
            </div>
        @endif
    </div>

    <script>
        document.getElementById('copy-verify-url').addEventListener('click', function () {
            var input = document.getElementById('verify-url');
            input.select();
            var done = function () { this.textContent = 'Copied'; }.bind(this);
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(done);
            } else {
                document.execCommand('copy');
                done();
            }
        });
    </script>
@endsection
