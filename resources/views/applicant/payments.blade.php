@extends('layouts.app')

@section('title', 'Payment History')

@section('content')
    <div class="page-header">
        <h1>Payment History</h1>
        <a href="{{ route('applications.show', $application) }}" class="btn btn-secondary">Back</a>
    </div>

    <div class="card">
        @if ($application->payments->isEmpty())
            <p class="muted">No payments recorded.</p>
        @else
            @foreach ($application->payments as $payment)
                <div class="card">
                    <div class="flex-between">
                        <h3 class="mb-0">&#8369;{{ number_format((float) $payment->amount, 2) }} — {{ \App\Support\PaymentMethods::label($payment->method) }}</h3>
                        <span class="badge {{ $payment->status === 'PAID' ? 'badge-green' : ($payment->status === 'FAILED' ? 'badge-red' : 'badge-yellow') }}">{{ $payment->status }}</span>
                    </div>
                    <dl class="kv" style="margin-top:12px">
                        <dt>Reference</dt><dd>{{ $payment->reference_number ?? '—' }}</dd>
                        <dt>Verification</dt><dd>{{ $payment->verification_status }}</dd>
                        <dt>Payment Date</dt><dd>{{ $payment->payment_date?->format('M d, Y g:i A') ?? '—' }}</dd>
                        <dt>Verified At</dt><dd>{{ $payment->verified_at?->format('M d, Y g:i A') ?? '—' }}</dd>
                        @if ($payment->proof_path)
                            <dt>Receipt</dt><dd><a href="{{ route('payments.proof', $payment) }}" target="_blank" rel="noopener">View my receipt</a></dd>
                        @endif
                        @if ($payment->rejection_reason)
                            <dt>Not accepted</dt><dd style="color:var(--red-dark)">{{ $payment->rejection_reason }}</dd>
                        @endif
                    </dl>
                    @if ($payment->transactions->isNotEmpty())
                        <h4>Transaction Log</h4>
                        <ul class="timeline">
                            @foreach ($payment->transactions as $txn)
                                <li>
                                    <strong>{{ $txn->event }}</strong> <span class="muted">({{ $txn->gateway }})</span>
                                    <div class="meta">{{ $txn->created_at?->format('M d, Y g:i A') }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
@endsection
