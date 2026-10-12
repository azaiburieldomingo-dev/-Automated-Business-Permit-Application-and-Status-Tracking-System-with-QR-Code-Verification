@extends('layouts.guest')

@section('title', 'Verify Permit — '.config('permit.municipality'))

@section('content')
    <div style="max-width:560px;margin:0 auto">
        <div class="page-header">
            <h1>Verify a Permit</h1>
        </div>

        <div class="card">
            <p class="muted">
                Scan the QR code on the Mayor's Permit with your phone camera. If you can't scan it,
                type or paste the verification link or code printed at the bottom of the permit.
            </p>

            @isset($invalid)
                <div class="alert alert-warning">Please enter a valid verification code or link.</div>
            @endisset

            <form method="GET" action="{{ route('verify.lookup') }}">
                <div class="form-group">
                    <label for="code">Verification Code or Link</label>
                    <input type="text" id="code" name="code" value="{{ old('code', request('code')) }}"
                           placeholder="{{ url('/verify') }}/..." autocomplete="off" required>
                    @error('code') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn">Verify Permit</button>
            </form>
        </div>

        <p class="text-center"><a href="{{ url('/') }}">{{ config('permit.municipality') }}</a></p>
    </div>
@endsection
