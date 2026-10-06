@extends('layouts.app')

@section('title', 'Edit Fee Type')

@section('content')
    <div class="page-header">
        <h1>Edit Fee Type</h1>
        <a href="{{ route('admin.fee-types.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card">
        <form method="POST" action="{{ route('admin.fee-types.update', $feeType) }}">
            @csrf
            @method('PUT')
            @include('admin.fee-types._form', ['feeType' => $feeType])
            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>
@endsection
