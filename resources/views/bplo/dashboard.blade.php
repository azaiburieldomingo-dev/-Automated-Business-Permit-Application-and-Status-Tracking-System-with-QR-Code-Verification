@extends('layouts.app')

@section('title', 'BPLO Dashboard')

@section('content')
    <div class="page-header">
        <h1>BPLO Dashboard</h1>
        <a href="{{ url('/bplo/applications') }}" class="btn">All Applications</a>
    </div>

    <div class="stat-grid">
        <x-stat-card :value="$counts['total']" label="Total" icon="📋" accent="mint" />
        <x-stat-card :value="$counts['pending_review']" label="Pending Review" icon="🕑" accent="amber" />
        <x-stat-card :value="$counts['for_correction']" label="For Correction" icon="✏️" accent="amber" />
        <x-stat-card :value="$counts['approved']" label="Approved" icon="👍" accent="green" />
        <x-stat-card :value="$counts['awaiting_payment']" label="Awaiting Payment" icon="⏳" accent="amber" />
        <x-stat-card :value="$counts['paid']" label="Paid" icon="💰" accent="green" />
        <x-stat-card :value="$counts['released']" label="Released" icon="✅" accent="green" />
    </div>

    <div class="card">
        <h2 class="section">Recent Applications</h2>
        @if ($recent->isEmpty())
            <x-empty-state icon="📋" title="No applications yet"
                message="Submitted applications will appear here for review." />
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
