@extends('layouts.app')

@section('title', 'Verify Payment')

@section('content')
    @php
        $receiving = config('permit.payment.accounts.'.$payment->method);
        $breakdown = $payment->breakdown_json ?? [];
        $lines = $breakdown['lines'] ?? [];
    @endphp

    <div class="page-header">
        <div>
            <h1>Verify Payment</h1>
            <p class="muted mb-0">Tracking Number: <strong>{{ $payment->tracking_number }}</strong></p>
        </div>
        <span>
            <span class="badge {{ $payment->status === 'PAID' ? 'badge-green' : ($payment->status === 'FAILED' ? 'badge-red' : 'badge-yellow') }}">{{ $payment->status }}</span>
            <span class="badge {{ $payment->verification_status === 'VERIFIED' ? 'badge-green' : 'badge-gray' }}">{{ $payment->verification_status }}</span>
        </span>
    </div>

    @if ($application)
        <div class="card">
            <h2>Business Information</h2>
            <dl class="kv">
                <dt>Business Name</dt><dd>{{ $application->business_name }}</dd>
                <dt>Business Address</dt><dd>{{ $application->business_address }}</dd>
                <dt>Applicant</dt><dd>{{ $application->applicant_name }}</dd>
                <dt>Application Status</dt><dd><x-status-badge :status="$application->status" /></dd>
            </dl>
        </div>
    @endif

    <div class="card">
        <h2>Payment Details</h2>
        <dl class="kv">
            <dt>Method</dt><dd>{{ \App\Support\PaymentMethods::label($payment->method) }}</dd>
            <dt>Amount</dt><dd>&#8369;{{ number_format((float) $payment->amount, 2) }}</dd>
            <dt>Reference No.</dt><dd style="font-family:monospace;font-size:1rem;font-weight:700">{{ $payment->reference_number ?? '—' }}</dd>
            <dt>Submitted</dt><dd>{{ $payment->created_at?->format('M d, Y g:i A') }}</dd>
            @if ($payment->payer_name || $payment->payer_number)
                <dt>Sender</dt><dd>{{ $payment->payer_name ?? '—' }} @if ($payment->payer_number) · {{ $payment->payer_number }} @endif</dd>
            @endif
            @if (! empty($receiving['number']))
                <dt>Paid to</dt><dd>{{ $receiving['number'] }} @if (! empty($receiving['name'])) ({{ $receiving['name'] }}) @endif</dd>
            @endif
            @if ($payment->proof_path)
                <dt>Receipt</dt>
                <dd><a href="{{ route('treasurer.payments.proof', $payment) }}" target="_blank" rel="noopener">View receipt ({{ $payment->proof_original_name }})</a></dd>
            @endif
            @if ($payment->verified_at)
                <dt>Verified</dt><dd>{{ $payment->verified_at->format('M d, Y g:i A') }}</dd>
            @endif
            @if ($payment->rejection_reason)
                <dt>Not accepted</dt><dd style="color:var(--red-dark)">{{ $payment->rejection_reason }}</dd>
            @endif
        </dl>
    </div>

    @if (! empty($lines))
        <div class="card">
            <h2>Fee Breakdown</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Fee</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr>
                                <td>{{ $line['name'] ?? '—' }}</td>
                                <td class="text-right">&#8369;{{ number_format((float) ($line['amount'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <th>TOTAL</th>
                            <th class="text-right">&#8369;{{ number_format((float) ($breakdown['total'] ?? $payment->amount), 2) }}</th>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($application)
        <x-process-tracker :application="$application" viewer="treasurer" />
    @endif

    <div class="card">
        <h2>Actions</h2>
        @if ($payment->isAwaitingVerification())
            <div class="alert alert-info" style="margin-bottom:14px">
                Open the {{ \App\Support\PaymentMethods::label($payment->method) }} account
                @if (! empty($receiving['number'])) <strong>{{ $receiving['number'] }}</strong> @endif
                and check its transaction history for Ref No. <strong>{{ $payment->reference_number }}</strong>
                with an amount of <strong>&#8369;{{ number_format((float) $payment->amount, 2) }}</strong>.
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start">
                <form method="POST" action="{{ route('treasurer.payments.confirm', $payment) }}" class="inline-form"
                      onsubmit="return confirm('Confirm that ₱{{ number_format((float) $payment->amount, 2) }} with Ref No. {{ $payment->reference_number }} was received?')">
                    @csrf <button type="submit" class="btn btn-success">Confirm Payment</button>
                </form>
                <form method="POST" action="{{ route('treasurer.payments.reject', $payment) }}" class="inline-form"
                      style="display:flex;gap:8px;flex-wrap:wrap;flex:1;min-width:260px"
                      onsubmit="return confirm('Reject this payment and ask the applicant to pay again?')">
                    @csrf
                    <input type="text" name="reason" placeholder="Reason, e.g. Ref No. not found or wrong amount" required style="flex:1;min-width:200px">
                    <button type="submit" class="btn btn-danger">Reject Payment</button>
                </form>
            </div>
            @error('reason') <div class="field-error">{{ $message }}</div> @enderror
        @elseif ($payment->verification_status === 'VERIFIED')
            <div class="alert alert-success mb-0">This payment has been verified. BPLO can now release the permit.</div>
        @else
            <p class="muted mb-0">This payment is not awaiting verification. No action is available.</p>
        @endif
    </div>

    <p><a href="{{ url('/treasurer/payments') }}" class="btn btn-secondary">Back to Payments</a></p>
@endsection
