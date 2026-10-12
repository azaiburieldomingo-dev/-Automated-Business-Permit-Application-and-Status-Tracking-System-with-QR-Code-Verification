@extends('layouts.app')

@section('title', 'Edit Fee Rate')

@section('content')
    <div class="page-header">
        <h1>Edit Fee Rate</h1>
        <a href="{{ route('admin.fee-rates.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card">
        <form method="POST" action="{{ route('admin.fee-rates.update', $feeRate) }}">
            @csrf
            @method('PUT')
            @include('admin.fee-rates._form', ['feeRate' => $feeRate])
            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>
@endsection
