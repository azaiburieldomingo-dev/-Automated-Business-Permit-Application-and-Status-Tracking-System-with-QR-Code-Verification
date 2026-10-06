@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <div class="page-header">
        <h1>Edit User</h1>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Back</a>
    </div>

    @if (auth()->id() === $user->id)
        <div class="alert alert-info">This is your own account. You cannot change your own role or deactivate yourself.</div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                @error('email') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" @disabled(auth()->id() === $user->id) required>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="is_active">Active</label>
                    <select id="is_active" name="is_active" @disabled(auth()->id() === $user->id)>
                        <option value="1" @selected(old('is_active', $user->is_active ? '1' : '0') === '1')>Yes</option>
                        <option value="0" @selected(old('is_active', $user->is_active ? '1' : '0') === '0')>No</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}">
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="{{ old('address', $user->address) }}">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">New Password (leave blank to keep)</label>
                    <input type="password" id="password" name="password">
                    @error('password') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation">
                </div>
            </div>
            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>
@endsection
