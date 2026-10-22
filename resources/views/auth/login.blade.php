@extends('layouts.auth')

@section('title', 'Login')

@section('form')
    <h2 class="auth-h2">Login</h2>

    <form method="POST" action="{{ url('/login') }}">
        @csrf
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            @error('password') <div class="field-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
            <label class="auth-remember"><input type="checkbox" name="remember" value="1"> Remember me</label>
        </div>
        <button type="submit" class="btn btn-block">Login</button>
    </form>

    <p class="text-center" style="margin-top:16px">
        <a href="{{ url('/forgot-password') }}">Forgot your password?</a>
    </p>
    <p class="text-center mb-0">
        No account? <a href="{{ url('/register') }}">Register as an applicant</a>
    </p>
@endsection
