@extends('layouts.app')

@section('title', 'Treasurer Dashboard')

@section('content')
    <div class="page-header">
        <h1>Treasurer Dashboard</h1>
        <a href="{{ url('/treasurer/payments') }}" class="btn">All Payments</a>
    </div>

    <div class="stat-grid">
        <x-stat-card :value="$counts['awaiting']" label="Awaiting Verification" icon="⏳" accent="amber" />
        <x-stat-card :value="$counts['verified_today']" label="Verified Today" icon="✅" accent="green" />
        <x-stat-card :value="$counts['total_verified']" label="Total Verified" icon="📑" accent="mint" />
        <x-stat-card :value="'&#8369;'.number_format($counts['total_collected'], 2)" label="Total Collected" icon="💰" accent="green" />
    </div>

    <div class="card">
        <h2 class="section">Payments Awaiting Verification</h2>
        @if ($pending->isEmpty())
            <x-empty-state icon="💸" title="No payments to verify"
                message="Payments submitted by applicants will appear here for you to confirm." />
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
