@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <div class="page-header">
        <h1>System Settings</h1>
    </div>

    <div class="alert alert-info">
        These values are read-only. Environment-level settings are changed only by an operator editing
        <code>.env</code> on the server. Requirement types, fee types, and fee rates are edited through their own screens.
    </div>

    <div class="card">
        <h2>Municipality Identity</h2>
        <dl class="kv">
            <dt>Municipality</dt><dd>{{ $permit['municipality'] }}</dd>
            <dt>Province</dt><dd>{{ $permit['province'] }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>Numbering &amp; Validity</h2>
        <dl class="kv">
            <dt>Tracking Prefix</dt><dd>{{ $permit['tracking_prefix'] }}</dd>
            <dt>Permit Prefix</dt><dd>{{ $permit['permit_prefix'] }}</dd>
            <dt>Permit Validity (months)</dt><dd>{{ $permit['valid_months'] }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>SMS</h2>
        <dl class="kv">
            <dt>Driver</dt><dd>{{ $permit['sms']['driver'] }}</dd>
            <dt>Sender ID</dt><dd>{{ $permit['sms']['sender_id'] }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>Payment</h2>
        <dl class="kv">
            <dt>Mode</dt><dd>{{ $permit['payment']['mode'] }}</dd>
            <dt>Enabled Methods</dt>
            <dd>
                @foreach ($permit['payment']['methods'] as $method => $enabled)
                    <span class="badge {{ $enabled ? 'badge-green' : 'badge-gray' }}">{{ strtoupper($method) }}</span>
                @endforeach
            </dd>
            <dt>Webhook Secret</dt><dd>{{ $permit['payment']['webhook_secret'] ? '•••••• (set)' : 'not set' }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>Uploads</h2>
        <dl class="kv">
            <dt>Allowed Mimes</dt><dd>{{ implode(', ', (array) $permit['uploads']['mimes']) }}</dd>
            <dt>Max Size (KB)</dt><dd>{{ $permit['uploads']['max_kb'] }}</dd>
        </dl>
    </div>
@endsection
