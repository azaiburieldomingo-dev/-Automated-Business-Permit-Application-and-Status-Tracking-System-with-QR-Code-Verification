@extends('layouts.guest')

@section('title', config('permit.municipality').' — Automated Business Permit Application and Status Tracking System')

@section('content')
    <div class="hero" style="margin:-28px -16px 0; border-radius:0 0 16px 16px">
        <div class="container">
            <h1>{{ config('permit.municipality') }} — Automated Business Permit Application and Status Tracking System</h1>
            <p>Apply for your business permit online, track your application, pay fees, and verify your permit with a QR code — all in one place for {{ config('permit.municipality') }}, {{ config('permit.province') }}.</p>
            <div class="hero-actions">
                <a href="{{ url('/register') }}" class="btn btn-mint">Apply Online</a>
                <a href="{{ url('/track') }}" class="btn btn-secondary">Track Application</a>
                <a href="{{ route('verify.lookup') }}" class="btn btn-secondary">Verify Permit</a>
                <a href="{{ url('/login') }}" class="btn btn-secondary">Login</a>
            </div>
        </div>
    </div>

    <div style="margin-top:40px">
        <h2 class="text-center">How It Works</h2>
        <p class="text-center muted" style="max-width:620px;margin:0 auto 8px">Four simple steps from application to a verified permit.</p>
        <div class="steps">
            <div class="step">
                <div class="num">1</div>
                <h3>Apply Online</h3>
                <p class="muted">Register, fill in your business details, and upload the required documents from any device.</p>
            </div>
            <div class="step">
                <div class="num">2</div>
                <h3>Track Status</h3>
                <p class="muted">Follow your application as the BPLO reviews it. Get notified of approvals or corrections.</p>
            </div>
            <div class="step">
                <div class="num">3</div>
                <h3>Pay Fees</h3>
                <p class="muted">Once approved, view your fee breakdown and settle the amount through the online payment flow.</p>
            </div>
            <div class="step">
                <div class="num">4</div>
                <h3>Download &amp; Verify</h3>
                <p class="muted">Download your official Mayor's Permit with a QR code anyone can scan to verify its authenticity.</p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:28px;text-align:center">
        <h2 class="mb-0">Ready to get started?</h2>
        <p class="muted">Apply for your Bansud business permit online, or verify an existing one.</p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <a href="{{ url('/register') }}" class="btn">Apply Online</a>
            <a href="{{ route('verify.lookup') }}" class="btn btn-secondary">Verify a Permit</a>
        </div>
    </div>
@endsection
