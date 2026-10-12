@extends('layouts.app')

@section('title', 'New Application')

@section('content')
    <div class="page-header">
        <h1>New Business Permit Application</h1>
        <a href="{{ url('/dashboard') }}" class="btn btn-secondary">Back</a>
    </div>

    <div class="card">
        <p class="muted">Fill in your business details to create a draft. You'll upload requirements on the next step.</p>
        <form method="POST" action="{{ url('/applications') }}">
            @csrf
            @include('applicant._form-fields', ['application' => null])
            <button type="submit" class="btn">Save Draft &amp; Continue</button>
        </form>
    </div>
@endsection
