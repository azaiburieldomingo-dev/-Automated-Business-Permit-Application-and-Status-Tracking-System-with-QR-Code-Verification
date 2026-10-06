@extends('layouts.app')

@section('title', 'Application Status')

@section('content')
    <div class="page-header">
        <div>
            <h1>Application Status</h1>
            <p class="muted mb-0">Tracking Number: <strong>{{ $application->tracking_number ?? '— (draft)' }}</strong></p>
        </div>
        <div>
            <x-status-badge :status="$application->status" />
        </div>
    </div>

    @if (in_array($application->status, [\App\Models\Application::STATUS_DRAFT, \App\Models\Application::STATUS_FOR_CORRECTION]))
        <div class="alert alert-info">
            This application is editable. <a href="{{ route('applications.edit', $application) }}">Continue editing</a>.
        </div>
    @endif

    @if ($application->status === \App\Models\Application::STATUS_APPROVED_FOR_PAYMENT)
        <div class="alert alert-success flex-between">
            <span>Your application is approved. Please settle the fees to proceed.</span>
            <a href="{{ route('applications.pay', $application) }}" class="btn btn-success">Pay Now</a>
        </div>
    @endif

    @if ($application->status === \App\Models\Application::STATUS_PERMIT_RELEASED && $application->permit)
        <div class="alert alert-success flex-between">
            <span>Your Mayor's Permit has been released.</span>
            <a href="{{ route('applications.permit', $application) }}" class="btn btn-success">View Permit</a>
        </div>
    @endif

    <div class="card">
        <h2>Status Timeline</h2>
        @if ($application->statusHistories->isEmpty())
            <p class="muted">No status changes recorded yet.</p>
        @else
            <ul class="timeline">
                @foreach ($application->statusHistories as $history)
                    <li>
                        <strong>{{ $history->to_status }}</strong>
                        @if ($history->from_status)
                            <span class="muted">(from {{ $history->from_status }})</span>
                        @endif
                        @if ($history->note)
                            <div>{{ $history->note }}</div>
                        @endif
                        <div class="meta">{{ $history->created_at?->format('M d, Y H:i') }} @if($history->actor_role) · {{ ucfirst($history->actor_role) }} @endif</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($application->remarks->isNotEmpty())
        <div class="card">
            <h2>Remarks</h2>
            <ul class="timeline">
                @foreach ($application->remarks->sortByDesc('created_at') as $remark)
                    <li>
                        <span class="badge {{ $remark->type === 'REJECTION' ? 'badge-red' : 'badge-yellow' }}">{{ $remark->type }}</span>
                        <div>{{ $remark->body }}</div>
                        <div class="meta">{{ $remark->created_at?->format('M d, Y H:i') }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <h2>Payments</h2>
        @if ($application->payments->isEmpty())
            <p class="muted">No payments recorded.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>Date</th><th>Method</th><th>Amount</th><th>Status</th><th>Verification</th><th>Reference</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($application->payments->sortByDesc('created_at') as $payment)
                            <tr>
                                <td>{{ $payment->created_at?->format('M d, Y H:i') }}</td>
                                <td>{{ strtoupper($payment->method) }}</td>
                                <td>&#8369;{{ number_format((float) $payment->amount, 2) }}</td>
                                <td>{{ $payment->status }}</td>
                                <td>{{ $payment->verification_status }}</td>
                                <td>{{ $payment->reference_number ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p style="margin-top:12px"><a href="{{ route('applications.payments', $application) }}">View payment history</a></p>
        @endif
    </div>
@endsection
