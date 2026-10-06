@extends('layouts.app')

@section('title', 'BPLO Dashboard')

@section('content')
    <div class="page-header">
        <h1>BPLO Dashboard</h1>
        <a href="{{ url('/bplo/applications') }}" class="btn">All Applications</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><div class="value">{{ $counts['total'] }}</div><div class="label">Total</div></div>
        <div class="stat-card"><div class="value">{{ $counts['pending_review'] }}</div><div class="label">Pending Review</div></div>
        <div class="stat-card"><div class="value">{{ $counts['for_correction'] }}</div><div class="label">For Correction</div></div>
        <div class="stat-card"><div class="value">{{ $counts['approved'] }}</div><div class="label">Approved</div></div>
        <div class="stat-card"><div class="value">{{ $counts['awaiting_payment'] }}</div><div class="label">Awaiting Payment</div></div>
        <div class="stat-card"><div class="value">{{ $counts['paid'] }}</div><div class="label">Paid</div></div>
        <div class="stat-card"><div class="value">{{ $counts['released'] }}</div><div class="label">Released</div></div>
    </div>

    <div class="card">
        <h2>Recent Applications</h2>
        @if ($recent->isEmpty())
            <p class="muted">No submitted applications yet.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>Tracking No.</th><th>Applicant</th><th>Business</th><th>Status</th><th>Submitted</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($recent as $application)
                            <tr>
                                <td>{{ $application->tracking_number }}</td>
                                <td>{{ $application->applicant_name }}</td>
                                <td>{{ $application->business_name }}</td>
                                <td><x-status-badge :status="$application->status" /></td>
                                <td>{{ $application->submitted_at?->format('M d, Y') }}</td>
                                <td><a href="{{ route('bplo.applications.show', $application) }}" class="btn btn-sm">Review</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
