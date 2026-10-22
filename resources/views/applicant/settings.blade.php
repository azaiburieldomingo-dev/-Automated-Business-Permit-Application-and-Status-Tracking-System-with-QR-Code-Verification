@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <div class="page-header">
        <h1>Account &amp; Security Settings</h1>
        <a href="{{ url('/dashboard') }}" class="btn btn-secondary">Back</a>
    </div>

    <div class="card">
        <h2>Profile Information</h2>
        <p class="muted">Update your name, contact details, and email address.</p>

        <form method="POST" action="{{ route('settings.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                    @error('email') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number"
                           value="{{ old('contact_number', $user->contact_number) }}" placeholder="09171234567">
                    @error('contact_number') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="{{ old('address', $user->address) }}">
                @error('address') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn">Save Profile</button>
        </form>
    </div>

    <div class="card">
        <h2>Change Password</h2>
        <p class="muted">Use at least 8 characters with upper and lower case letters and a number.</p>

        <form method="POST" action="{{ route('settings.password.update') }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                @error('current_password') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    @error('password') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="btn">Change Password</button>
        </form>
    </div>

    <p class="muted">To sign out, use the <strong>Logout</strong> button in the sidebar.</p>
@endsection
