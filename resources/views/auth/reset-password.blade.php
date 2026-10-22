@extends('layouts.auth')

@section('title', 'Reset Password')

@section('form')
    <h2 class="auth-h2">Reset Password</h2>

    <form method="POST" action="{{ url('/reset-password') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required autofocus>
            @error('email') <div class="field-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" id="password" name="password" required>
            @error('password') <div class="field-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm New Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>
        <button type="submit" class="btn btn-block">Reset Password</button>
    </form>
@endsection
