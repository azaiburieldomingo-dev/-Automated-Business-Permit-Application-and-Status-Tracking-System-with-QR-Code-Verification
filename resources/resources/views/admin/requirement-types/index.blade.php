@extends('layouts.app')

@section('title', 'Requirement Types')

@section('content')
    <div class="page-header">
        <h1>Requirement Types</h1>
        <a href="{{ route('admin.requirement-types.create') }}" class="btn">New Requirement Type</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Name</th><th>Code</th><th>Required</th><th>Active</th><th>Order</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($types as $type)
                        <tr>
                            <td>{{ $type->name }}</td>
                            <td>{{ $type->code }}</td>
                            <td>{{ $type->is_required ? 'Yes' : 'No' }}</td>
                            <td>{{ $type->is_active ? 'Yes' : 'No' }}</td>
                            <td>{{ $type->sort_order }}</td>
                            <td><a href="{{ route('admin.requirement-types.edit', $type) }}" class="btn btn-sm btn-secondary">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$types" />
    </div>
@endsection
