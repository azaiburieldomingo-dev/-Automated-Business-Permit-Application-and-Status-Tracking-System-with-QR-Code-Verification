@extends('layouts.app')

@section('title', 'Applications')

@section('content')
    <div class="page-header">
        <h1>Applications</h1>
        <a href="{{ url('/bplo/dashboard') }}" class="btn btn-secondary">Dashboard</a>
    </div>

    <div class="card">
        <form method="GET" action="{{ url('/bplo/applications') }}" class="toolbar">
            <div class="form-group">
                <label for="q">Search</label>
                <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="Tracking / applicant / business">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="date">Submitted Date</label>
                <input type="date" id="date" name="date" value="{{ request('date') }}">
            </div>
            <div class="form-group">
                <label for="sort">Sort By</label>
                <select id="sort" name="sort">
                    @foreach (['created_at' => 'Created', 'submitted_at' => 'Submitted', 'status' => 'Status', 'business_name' => 'Business', 'applicant_name' => 'Applicant', 'tracking_number' => 'Tracking No.'] as $key => $label)
                        <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="direction">Direction</label>
                <select id="direction" name="direction">
                    <option value="desc" @selected($direction === 'desc')>Descending</option>
                    <option value="asc" @selected($direction === 'asc')>Ascending</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Apply</button>
                <a href="{{ url('/bplo/applications') }}" class="btn btn-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Tracking No.</th><th>Applicant</th><th>Business</th><th>Status</th><th>Submitted</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr>
                            <td>{{ $application->tracking_number }}</td>
                            <td>{{ $application->applicant_name }}</td>
                            <td>{{ $application->business_name }}</td>
                            <td><x-status-badge :status="$application->status" /></td>
                            <td>{{ $application->submitted_at?->format('M d, Y') }}</td>
                            <td><a href="{{ route('bplo.applications.show', $application) }}" class="btn btn-sm">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No applications match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$applications" />
    </div>
@endsection
