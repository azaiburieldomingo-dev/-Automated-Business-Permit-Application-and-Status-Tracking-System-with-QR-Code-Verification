@extends('layouts.app')

@section('title', 'Edit Requirement Type')

@section('content')
    <div class="page-header">
        <h1>Edit Requirement Type</h1>
        <a href="{{ route('admin.requirement-types.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card">
        <form method="POST" action="{{ route('admin.requirement-types.update', $type) }}">
            @csrf
            @method('PUT')
            @include('admin.requirement-types._form', ['type' => $type])
            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>
@endsection
