@extends('layouts.guest')

@section('title', 'Access Not Allowed — '.config('permit.municipality'))

@section('content')
    <div class="card" style="max-width:560px;margin:40px auto;text-align:center">
        <h1>Access Not Allowed</h1>
        <p class="muted">
            Your account doesn't have access to this page.
            @auth
                You are signed in as <strong>{{ auth()->user()->email }}</strong>
                ({{ strtoupper(auth()->user()->role) }}).
            @endauth
        </p>

        @auth
            <a href="{{ url(auth()->user()->homePath()) }}" class="btn">Go to My Dashboard</a>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-secondary">Log out and switch account</button>
            </form>
        @else
            <a href="{{ url('/login') }}" class="btn">Login</a>
        @endauth
    </div>
@endsection
