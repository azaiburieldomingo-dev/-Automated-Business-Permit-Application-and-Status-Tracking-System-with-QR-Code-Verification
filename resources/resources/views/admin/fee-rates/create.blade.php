@extends('layouts.app')

@section('title', 'New Fee Rate')

@section('content')
    <div class="page-header">
        <h1>New Fee Rate</h1>
        <a href="{{ route('admin.fee-rates.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card">
        <form method="POST" action="{{ route('admin.fee-rates.store') }}">
            @csrf
            @include('admin.fee-rates._form', ['feeRate' => null])
            <button type="submit" class="btn">Create</button>
        </form>
    </div>
@endsection
