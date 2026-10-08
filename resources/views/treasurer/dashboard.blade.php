@extends('layouts.app')

@section('title', 'Treasurer Dashboard')

@section('content')
    <div class="page-header">
        <h1>Treasurer Dashboard</h1>
        <a href="{{ url('/treasurer/payments') }}" class="btn">All Payments</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><div class="value">{{ $counts['awaiting'] }}</div><div class="label">Awaiting Verification</div></div>
        <div class="stat-card"><div class="value">{{ $counts['verified_today'] }}</div><div class="label">Verified Today</div></div>
        <div class="stat-card"><div class="value">{{ $counts['total_verified'] }}</div><div class="label">Total Verified</div></div>
        <div class="stat-card"><div class="value">&#8369;{{ number_format($counts['total_collected'], 2) }}</div><div class="label">Total Collected</div></div>
    </div>

    <div class="card">
        <h2>Payments Awaiting Verification</h2>
        @if ($pending->isEmpty())
            <p class="muted">No payments are awaiting verification right now.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>Tracking No.</th><th>Business</th><th>Method</th><th>Reference No.</th><th class="text-right">Amount</th><th>Submitted</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($pending as $payment)
                            <tr>
                                <td>{{ $payment->tracking_number }}</td>
                                <td>{{ $payment->application?->business_name ?? '—' }}</td>
                                <td>{{ \App\Support\PaymentMethods::label($payment->method) }}</td>
                                <td style="font-family:monospace">{{ $payment->reference_number ?? '—' }}</td>
                                <td class="text-right">&#8369;{{ number_format((float) $payment->amount, 2) }}</td>
                                <td>{{ $payment->created_at?->format('M d, Y g:i A') }}</td>
                                <td><a href="{{ route('treasurer.payments.show', $payment) }}" class="btn btn-sm">Verify</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
