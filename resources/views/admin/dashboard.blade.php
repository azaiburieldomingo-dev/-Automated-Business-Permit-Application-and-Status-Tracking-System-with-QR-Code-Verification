@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="page-header">
        <h1>Admin Dashboard</h1>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><div class="value">{{ $counts['users'] }}</div><div class="label">Total Users</div></div>
        <div class="stat-card"><div class="value">{{ $counts['applications'] }}</div><div class="label">Total Applications</div></div>
        <div class="stat-card"><div class="value">{{ $counts['permits'] }}</div><div class="label">Total Permits</div></div>
        <div class="stat-card"><div class="value">{{ $counts['payments'] }}</div><div class="label">Total Payments</div></div>
        <div class="stat-card"><div class="value">{{ $counts['active_permits'] }}</div><div class="label">Active Permits</div></div>
        <div class="stat-card"><div class="value">{{ $counts['expired_permits'] }}</div><div class="label">Expired Permits</div></div>
    </div>

    <div class="card">
        <h2>Recent Activities</h2>
        @if ($recentActivities->isEmpty())
            <p class="muted">No activity recorded.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>When</th><th>Actor</th><th>Action</th><th>Subject</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($recentActivities as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('M d, Y H:i') }}</td>
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
@endsection
