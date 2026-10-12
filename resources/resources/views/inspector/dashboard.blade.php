@extends('layouts.app')

@section('title', 'Inspector Dashboard')

@section('content')
    <div class="page-header">
        <h1>Inspector Dashboard</h1>
        <a href="{{ url('/inspector/permits') }}" class="btn">Released Permits</a>
    </div>

    <div class="stat-grid">
        <x-stat-card :value="$counts['active']" label="Active Permits" icon="✅" accent="green" />
        <x-stat-card :value="$counts['expired']" label="Expired Permits" icon="⚠️" accent="amber" />
        <x-stat-card :value="$counts['revoked']" label="Revoked Permits" icon="⛔" accent="red" />
        <x-stat-card :value="$counts['scans_today']" label="Scans Today" icon="🔍" accent="mint" />
    </div>

    <div class="card">
        <h2 class="section">Scan / Verify a Permit</h2>
        <p class="muted">Point a phone camera at the QR code on a permit to open its verification page, or paste the code below for manual entry.</p>
        <a href="{{ url('/verify') }}" class="btn btn-mint">Open Manual Verify</a>
    </div>

    <div class="card">
        <h2 class="section">Recent Scans</h2>
        @if ($recentScans->isEmpty())
            <x-empty-state icon="🔍" title="No scans yet"
                message="Each time a permit's QR code is verified, it will be logged here." />
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>Time</th><th>Permit No.</th><th>Result</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($recentScans as $scan)
                            <tr>
                                <td>{{ $scan->created_at?->format('M d, Y g:i A') }}</td>
                                <td>{{ $scan->permit?->permit_number ?? '—' }}</td>
                                <td>
                                    @php
                                        $badge = match ($scan->result) {
                                            'VALID' => 'badge-green',
                                            'EXPIRED' => 'badge-yellow',
                                            'REVOKED', 'NOT_FOUND' => 'badge-red',
                                            default => 'badge-gray',
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ $scan->result }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
