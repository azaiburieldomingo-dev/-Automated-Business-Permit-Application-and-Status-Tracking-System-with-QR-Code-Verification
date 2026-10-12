@extends('layouts.app')

@section('title', 'New Requirement Type')

@section('content')
    <div class="page-header">
        <h1>New Requirement Type</h1>
        <a href="{{ route('admin.requirement-types.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card">
        <form method="POST" action="{{ route('admin.requirement-types.store') }}">
            @csrf
            @include('admin.requirement-types._form', ['type' => null])
            <button type="submit" class="btn">Create</button>
        </form>
    </div>
@endsection
