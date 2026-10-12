@extends('layouts.app')

@section('title', 'New Fee Type')

@section('content')
    <div class="page-header">
        <h1>New Fee Type</h1>
        <a href="{{ route('admin.fee-types.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card">
        <form method="POST" action="{{ route('admin.fee-types.store') }}">
            @csrf
            @include('admin.fee-types._form', ['feeType' => null])
            <button type="submit" class="btn">Create</button>
        </form>
    </div>
@endsection
