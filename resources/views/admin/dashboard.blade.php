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
@endsection
