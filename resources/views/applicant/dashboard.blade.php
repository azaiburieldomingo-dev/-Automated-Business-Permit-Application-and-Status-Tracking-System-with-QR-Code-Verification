@extends('layouts.app')

@section('title', 'My Dashboard')

@section('content')
    <div class="page-header">
        <h1>My Applications</h1>
        <a href="{{ url('/applications/create') }}" class="btn">New Application</a>
    </div>

    <div class="card">
        <h2>Applications</h2>
        @if ($applications->isEmpty())
            <p class="muted">You have no applications yet. Start by creating one.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Tracking No.</th>
                            <th>Business</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($applications as $application)
                            <tr>
                                <td>{{ $application->tracking_number ?? '—' }}</td>
                                <td>{{ $application->business_name }}</td>
                                <td><x-status-badge :status="$application->status" /></td>
                                <td>{{ $application->updated_at?->format('M d, Y H:i') }}</td>
                                <td>
                                    @if (in_array($application->status, [\App\Models\Application::STATUS_DRAFT, \App\Models\Application::STATUS_FOR_CORRECTION]))
                                        <a href="{{ route('applications.edit', $application) }}" class="btn btn-sm btn-secondary">Edit</a>
                                    @endif
                                    <a href="{{ route('applications.show', $application) }}" class="btn btn-sm">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card">
        <h2>Notifications</h2>
        @if ($notifications->isEmpty())
            <p class="muted">No notifications.</p>
        @else
            <ul class="timeline">
                @foreach ($notifications as $notification)
                    <li>
                        <strong>{{ $notification->title }}</strong>
                        <div>{{ $notification->body }}</div>
                        <div class="meta">{{ $notification->created_at?->format('M d, Y H:i') }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
