<!DOCTYPE html>
<html lang="en" data-home-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Business Permit System') — {{ config('permit.municipality') }}</title>
    <meta name="theme-color" content="#0a1230">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
</head>
<body class="home auth-page">

<div class="auth-hero" style="background-image: linear-gradient(rgba(8,16,44,0.82), rgba(5,10,30,0.92)), url('{{ asset('images/bansud-hall.jpg') }}');">

    {{-- Animated sky (same as homepage) --}}
    <div class="sky" aria-hidden="true">
        <div class="moon"></div>
        <div class="cloud cloud-1"></div>
        <div class="cloud cloud-2"></div>
        <div class="stars">
            @for ($i = 0; $i < 3; $i++)
                <span class="star" style="left:{{ random_int(8, 70) }}%; top:{{ random_int(6, 34) }}%; animation-delay:{{ $i * 4 + 2 }}s;"></span>
            @endfor
            @for ($i = 0; $i < 16; $i++)
                <span class="twinkle" style="left:{{ random_int(1, 98) }}%; top:{{ random_int(1, 60) }}%; animation-delay:{{ $i * 0.45 }}s; opacity:{{ random_int(2, 7) / 10 }};"></span>
            @endfor
        </div>
    </div>

    <div class="auth-box">
        <a href="{{ url('/') }}" class="auth-home-link" title="Back to home">&#8592; Home</a>

        {{-- BPLO logo centered on top --}}
        <div class="auth-logo-wrap">
            <img src="{{ asset('images/bansud-logo.png') }}" alt="BPLO Logo" class="auth-logo" onerror="this.style.display='none'">
        </div>
        <h1 class="auth-title">Business Permit System</h1>
        <p class="auth-muted">{{ config('permit.municipality') }}, {{ config('permit.province') }}</p>

        <div class="auth-card-glass">
            @yield('form')
        </div>
    </div>
</div>
</body>
</html>
