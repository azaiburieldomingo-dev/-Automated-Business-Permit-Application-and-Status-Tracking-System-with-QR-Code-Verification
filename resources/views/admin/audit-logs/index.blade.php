@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
    <div class="page-header">
        <h1>Audit Logs</h1>
    </div>

    <div class="card">
        <form method="GET" action="{{ url('/admin/audit-logs') }}" class="toolbar">
            <div class="form-group">
                <label for="action">Action</label>
                <select id="action" name="action">
                    <option value="">All</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="actor">Actor ID</label>
                <input type="number" id="actor" name="actor" value="{{ request('actor') }}">
            </div>
            <div class="form-group">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="{{ request('date') }}">
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Filter</button>
                <a href="{{ url('/admin/audit-logs') }}" class="btn btn-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>When</th><th>Actor</th><th>Action</th><th>Subject</th><th>IP</th></tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('M d, Y g:i A') }}</td>
                            <td>{{ $log->actor?->name ?? 'System' }}</td>
                            <td>{{ $log->action }}</td>
                            <td>{{ class_basename($log->subject_type ?? '') }} {{ $log->subject_id }}</td>
                            <td>{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No log entries match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$logs" />
    </div>
@endsection
