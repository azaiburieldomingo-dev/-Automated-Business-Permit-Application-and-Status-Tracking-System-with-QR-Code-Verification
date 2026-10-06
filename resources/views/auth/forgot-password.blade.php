@extends('layouts.guest')

@section('title', 'Forgot Password — '.config('permit.municipality'))

@section('content')
    <div class="auth-wrap">
        <div class="auth-card">
            <h1>Forgot Password</h1>
            <p class="muted text-center">Enter your email and we'll send a reset link.</p>

            @if (session('status'))
                <div class="alert alert-info">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ url('/forgot-password') }}">
                @csrf
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
                    @error('email') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-block">Send Reset Link</button>
            </form>

            <p class="text-center" style="margin-top:16px">
                <a href="{{ url('/login') }}">Back to login</a>
            </p>
        </div>
    </div>
@endsection
