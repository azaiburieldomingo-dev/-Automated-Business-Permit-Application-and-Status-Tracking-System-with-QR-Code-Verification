@extends('layouts.app')

@section('title', 'Fee Types')

@section('content')
    <div class="page-header">
        <h1>Fee Types</h1>
        <a href="{{ route('admin.fee-types.create') }}" class="btn">New Fee Type</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Name</th><th>Code</th><th>Active</th><th>Order</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($feeTypes as $feeType)
                        <tr>
                            <td>{{ $feeType->name }}</td>
                            <td>{{ $feeType->code }}</td>
                            <td>{{ $feeType->is_active ? 'Yes' : 'No' }}</td>
                            <td>{{ $feeType->sort_order }}</td>
                            <td><a href="{{ route('admin.fee-types.edit', $feeType) }}" class="btn btn-sm btn-secondary">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$feeTypes" />
    </div>
@endsection
