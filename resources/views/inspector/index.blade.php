@extends('layouts.app')

@section('title', 'Released Permits')

@section('content')
    <div class="page-header">
        <h1>Released Permits</h1>
        <a href="{{ url('/inspector/dashboard') }}" class="btn btn-secondary">Dashboard</a>
    </div>

    <div class="card">
        <form method="GET" action="{{ url('/inspector/permits') }}" class="toolbar">
            <div class="form-group">
                <label for="q">Search</label>
                <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="Permit no. / business / address">
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Apply</button>
                <a href="{{ url('/inspector/permits') }}" class="btn btn-secondary">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Permit No.</th><th>Business</th><th>Address</th><th>Status</th><th>Valid Until</th><th>Compliance</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($permits as $permit)
                        @php
                            $isExpired = $permit->valid_until !== null && $permit->valid_until->isPast();
                            $isRevoked = $permit->status === \App\Models\Permit::STATUS_REVOKED;
                            if ($isRevoked) {
                                $indicator = ['REVOKED', 'badge-red'];
                            } elseif ($isExpired) {
                                $indicator = ['EXPIRED', 'badge-yellow'];
                            } else {
                                $indicator = ['ACTIVE', 'badge-green'];
                            }
                        @endphp
                        <tr>
                            <td>{{ $permit->permit_number }}</td>
                            <td>{{ $permit->business_name }}</td>
                            <td>{{ $permit->business_address }}</td>
                            <td>{{ $permit->status }}</td>
                            <td>{{ $permit->valid_until?->format('M d, Y') ?? '—' }}</td>
                            <td><span class="badge {{ $indicator[1] }}">{{ $indicator[0] }}</span></td>
                            <td><a href="{{ route('inspector.permits.show', $permit) }}" class="btn btn-sm">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">No released permits match your search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$permits" />
    </div>
@endsection
