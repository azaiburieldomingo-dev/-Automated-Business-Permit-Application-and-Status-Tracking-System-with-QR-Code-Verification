@extends('layouts.app')

@section('title', 'Fee Rates')

@section('content')
    <div class="page-header">
        <h1>Fee Rates</h1>
        <a href="{{ route('admin.fee-rates.create') }}" class="btn">New Fee Rate</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Fee Type</th><th>Label</th><th>Amount</th><th>Active</th><th>Effective From</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($feeRates as $rate)
                        <tr>
                            <td>{{ $rate->feeType?->name }}</td>
                            <td>{{ $rate->label }}</td>
                            <td>&#8369;{{ number_format((float) $rate->amount, 2) }}</td>
                            <td>{{ $rate->is_active ? 'Yes' : 'No' }}</td>
                            <td>{{ $rate->effective_from?->format('M d, Y') ?? '—' }}</td>
                            <td><a href="{{ route('admin.fee-rates.edit', $rate) }}" class="btn btn-sm btn-secondary">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$feeRates" />
    </div>
@endsection
