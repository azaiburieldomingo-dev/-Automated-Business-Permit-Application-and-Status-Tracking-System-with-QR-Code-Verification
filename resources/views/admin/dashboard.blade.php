@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="page-header">
        <h1>Admin Dashboard</h1>
    </div>

    <div class="stat-grid">
        <x-stat-card :value="$counts['users']" label="Total Users" icon="👥" accent="mint" />
        <x-stat-card :value="$counts['applications']" label="Total Applications" icon="📋" accent="default" />
        <x-stat-card :value="$counts['permits']" label="Total Permits" icon="📄" accent="default" />
        <x-stat-card :value="$counts['payments']" label="Total Payments" icon="💰" accent="green" />
        <x-stat-card :value="$counts['active_permits']" label="Active Permits" icon="✅" accent="green" />
        <x-stat-card :value="$counts['expired_permits']" label="Expired Permits" icon="⚠️" accent="amber" />
    </div>

    <div class="card">
        <h2 class="section">Recent Activities</h2>
        @if ($recentActivities->isEmpty())
            <x-empty-state icon="📜" title="No activity yet"
                message="Logins, approvals, payments, and other actions will show up here." />
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>When</th><th>Actor</th><th>Action</th><th>Subject</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($recentActivities as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('M d, Y g:i A') }}</td>
                                <td>{{ $log->actor?->name ?? 'System' }}</td>
                                <td>{{ $log->action }}</td>
                                <td>{{ class_basename($log->subject_type ?? '') }} {{ $log->subject_id }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Workflow overview: shows who acts at each step of the permit process. --}}
    <div class="card">
        <h2 class="section">Permit Process Overview</h2>
        <p class="muted">The standard flow of every application and who is responsible at each step.</p>
        <ol class="progress-steps">
            <li class="progress-step"><span class="progress-dot">1</span>
                <span class="progress-info"><span class="progress-label">Application Submitted</span>
                <span class="progress-who">Applicant</span></span></li>
            <li class="progress-step"><span class="progress-dot">2</span>
                <span class="progress-info"><span class="progress-label">Review (Approve / Return / Reject)</span>
                <span class="progress-who">BPLO</span></span></li>
            <li class="progress-step"><span class="progress-dot">3</span>
                <span class="progress-info"><span class="progress-label">Pay Fees (GCash)</span>
                <span class="progress-who">Applicant</span></span></li>
            <li class="progress-step"><span class="progress-dot">4</span>
                <span class="progress-info"><span class="progress-label">Verify Payment</span>
                <span class="progress-who">Treasurer</span></span></li>
            <li class="progress-step"><span class="progress-dot">5</span>
                <span class="progress-info"><span class="progress-label">Release Permit</span>
                <span class="progress-who">BPLO</span></span></li>
            <li class="progress-step"><span class="progress-dot">6</span>
                <span class="progress-info"><span class="progress-label">Scan / Verify Permit (QR)</span>
                <span class="progress-who">Inspector</span></span></li>
        </ol>
    </div>
@endsection
