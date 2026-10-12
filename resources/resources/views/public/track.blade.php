@extends('layouts.guest')

@section('title', 'Track Application — '.config('permit.municipality'))

@section('content')
    <div class="page-header">
        <h1>Track Your Application</h1>
    </div>

    <div class="card">
        <p class="muted">Enter your tracking number and either the email or last name used on the application.</p>
        <form method="POST" action="{{ url('/track') }}">
            @csrf
            <div class="form-row">
                <div class="form-group">
                    <label for="tracking_number">Tracking Number</label>
                    <input type="text" id="tracking_number" name="tracking_number" value="{{ old('tracking_number', $tracking_number ?? '') }}" placeholder="{{ config('permit.tracking_prefix') }}-{{ date('Y') }}-000001" required>
                    @error('tracking_number') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="verifier">Email or Last Name</label>
                    <input type="text" id="verifier" name="verifier" value="{{ old('verifier') }}" required>
                    @error('verifier') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>
            <button type="submit" class="btn">Track</button>
        </form>
    </div>

    @isset($notFound)
        @if ($notFound)
            <div class="alert alert-warning">No application matched that tracking number and verifier.</div>
        @endif
    @endisset

    @if (!empty($application))
        <div class="card">
            <div class="flex-between">
                <h2 class="mb-0">Status</h2>
                <x-status-badge :status="$application->status" />
            </div>
            <p class="muted" style="margin-top:8px">Tracking Number: <strong>{{ $application->tracking_number }}</strong> · Business: {{ $application->business_name }}</p>
        </div>

        <div class="card">
            <h2>Timeline</h2>
            <ul class="timeline">
                @foreach ($application->statusHistories as $history)
                    <li>
                        <strong>{{ $history->to_status }}</strong>
                        <div class="meta">{{ $history->created_at?->format('M d, Y g:i A') }}</div>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($application->remarks->isNotEmpty())
            <div class="card">
                <h2>Remarks &amp; Correction Notes</h2>
                <ul class="timeline">
                    @foreach ($application->remarks->sortByDesc('created_at') as $remark)
                        <li>
                            <span class="badge {{ $remark->type === 'REJECTION' ? 'badge-red' : 'badge-yellow' }}">{{ $remark->type }}</span>
                            <div>{{ $remark->body }}</div>
                            <div class="meta">{{ $remark->created_at?->format('M d, Y g:i A') }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <h2>Payment</h2>
            @php($latestPayment = $application->payments->sortByDesc('created_at')->first())
            @if ($latestPayment)
                <dl class="kv">
                    <dt>Status</dt><dd>{{ $latestPayment->status }}</dd>
                    <dt>Verification</dt><dd>{{ $latestPayment->verification_status }}</dd>
                </dl>
            @else
                <p class="muted">No payment recorded yet.</p>
            @endif
        </div>

        <div class="card">
            <h2>Permit</h2>
            @if ($application->permit)
                <p>Permit <strong>{{ $application->permit->permit_number }}</strong> — status {{ $application->permit->status }}.</p>
            @else
                <p class="muted">No permit released yet.</p>
            @endif
        </div>
    @endif

    <div class="card" id="verify">
        <h2>Verify a Permit</h2>
        <p class="muted">Scan the QR code on an issued Mayor's Permit to verify its authenticity, or enter the verification link printed on the permit.</p>
        <a href="{{ route('verify.lookup') }}" class="btn btn-secondary">Verify Permit</a>
    </div>
@endsection
