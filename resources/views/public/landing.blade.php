<!DOCTYPE html>
<html lang="en" data-home-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('permit.municipality') }} — Business Permit System</title>
    <meta name="theme-color" content="#0a1230">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <script>
        // Apply saved homepage theme (dark/light) before paint.
        (function () {
            try {
                var t = localStorage.getItem('home-theme');
                if (t) document.documentElement.setAttribute('data-home-theme', t);
            } catch (e) {}
        })();
    </script>
</head>
<body class="home">

<div class="home-hero" style="background-image: linear-gradient(var(--home-ov1), var(--home-ov2)), url('{{ asset('images/bansud-hall.jpg') }}');">

    {{-- Animated sky: moon, drifting clouds, falling stars --}}
    <div class="sky" aria-hidden="true">
        <div class="moon"></div>
        <div class="cloud cloud-1"></div>
        <div class="cloud cloud-2"></div>
        <div class="cloud cloud-3"></div>
        <div class="stars">
            {{-- Occasional shooting stars (spread out so they rarely overlap) --}}
            @for ($i = 0; $i < 4; $i++)
                <span class="star" style="left:{{ random_int(8, 70) }}%; top:{{ random_int(6, 34) }}%; animation-delay:{{ $i * 3.5 + 2 }}s;"></span>
            @endfor
            {{-- Faint static twinkling stars for depth --}}
            @for ($i = 0; $i < 22; $i++)
                <span class="twinkle" style="left:{{ random_int(1, 98) }}%; top:{{ random_int(1, 55) }}%; animation-delay:{{ $i * 0.4 }}s; opacity:{{ random_int(2, 7) / 10 }};"></span>
            @endfor
        </div>
    </div>

    {{-- Top bar --}}
    <header class="home-top">
        <div class="home-top-left">
            <img src="{{ asset('images/bansud-logo.png') }}" alt="Logo" class="home-logo" onerror="this.style.display='none'">
            <span>{{ config('permit.municipality') }} &middot; {{ config('permit.province') }}</span>
        </div>
        <nav class="home-top-right">
            <a href="#about">About Bansud</a>
            <a href="{{ url('/track') }}">Track</a>
            <a href="{{ url('/login') }}">Login</a>
            <button type="button" class="home-icon" id="home-search-btn" title="Search" aria-label="Search">&#128269;</button>
            <span class="home-lang" title="Language">EN &#9662;</span>
            <button type="button" class="home-icon" id="home-theme-btn" title="Toggle dark/light" aria-label="Toggle theme">&#9790;</button>
        </nav>
    </header>

    {{-- Hero content --}}
    <div class="home-hero-inner">
        <p class="home-welcome split-letters">WELCOME TO</p>
        <h1 class="home-title split-letters">BUSINESS PERMIT</h1>
        <p class="home-tag">Automated Business Permit Application and Status Tracking System with QR Code Verification</p>
        <p class="home-sub">Malinis, Mapayapa at Maunlad na Bayan ng Bansud</p>

        {{-- Search box --}}
        <form class="home-search" method="GET" action="{{ url('/track') }}" id="home-search-form" hidden>
            <input type="text" name="q" placeholder="Enter your tracking number..." aria-label="Search">
            <button type="submit">&#8594;</button>
        </form>

        <div class="home-actions">
            <a href="{{ url('/register') }}" class="home-btn home-btn-primary">Apply Online &#8594;</a>
            <a href="{{ url('/track') }}" class="home-btn">Track Application &#8594;</a>
            <a href="#about" class="home-btn">About Bansud &#8594;</a>
        </div>
    </div>

    {{-- Auto-scrolling "How It Works" steps along the bottom --}}
    <div class="home-ticker">
        <div class="home-ticker-head">
            <span class="home-ticker-title">&#128276; HOW IT WORKS</span>
            <a href="{{ url('/register') }}" class="home-ticker-all">Get started &#8594;</a>
        </div>
        <div class="home-ticker-track">
            @php
                $steps = [
                    ['1', 'Apply Online', 'Register, fill in your business details, and upload the required documents.'],
                    ['2', 'Track Status', 'Follow your application as the BPLO reviews it. Get notified of updates.'],
                    ['3', 'Pay Fees', 'Once approved, view your fee breakdown and pay online via GCash.'],
                    ['4', 'Download & Verify', 'Download your Mayor\'s Permit with a QR code anyone can scan to verify.'],
                ];
            @endphp
            {{-- Rendered twice for a seamless infinite loop --}}
            @for ($rep = 0; $rep < 2; $rep++)
                @foreach ($steps as $s)
                    <div class="home-ticker-card" @if($rep === 1) aria-hidden="true" @endif>
                        <span class="home-ticker-num">{{ $s[0] }}</span>
                        <div>
                            <div class="home-ticker-name">{{ $s[1] }}</div>
                            <div class="home-ticker-note">{{ $s[2] }}</div>
                        </div>
                    </div>
                @endforeach
            @endfor
        </div>
    </div>
</div>

{{-- About / services section (light) --}}
<section id="about" class="home-about">
    <div class="container">
        <h2 class="text-center">Business Permits Licensing System</h2>
        <p class="text-center muted">Engage with us</p>
        <div class="gov-services">
            <a href="{{ url('/register') }}" class="gov-service">
                <div class="gov-service-mark">BP</div>
                <div class="gov-service-name">Business Permit</div>
                <div class="gov-service-note">Apply online</div>
            </a>
            <div class="gov-service is-soon">
                <div class="gov-service-mark">BLD</div>
                <div class="gov-service-name">Building Permit</div>
                <div class="gov-service-note">Soon</div>
            </div>
            <div class="gov-service is-soon">
                <div class="gov-service-mark">WRK</div>
                <div class="gov-service-name">Working Permit</div>
                <div class="gov-service-note">Soon</div>
            </div>
            <div class="gov-service is-soon">
                <div class="gov-service-mark">OCC</div>
                <div class="gov-service-name">Occupancy Permit</div>
                <div class="gov-service-note">Soon</div>
            </div>
        </div>

        <div class="card" style="margin-top:28px;text-align:center">
            <h2 class="mb-0">Ready to get started?</h2>
            <p class="muted">Apply for your {{ config('permit.municipality') }} business permit online, or verify an existing one.</p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                <a href="{{ url('/register') }}" class="btn">Apply Online</a>
                <a href="{{ route('verify.lookup') }}" class="btn btn-secondary">Verify a Permit</a>
            </div>
        </div>
    </div>
</section>

<footer class="footer">
    <div class="container">
        &copy; {{ date('Y') }} {{ config('permit.municipality') }}, {{ config('permit.province') }}.
        Automated Business Permit Application and Status Tracking System.
    </div>
</footer>

<script>
    // Split text into per-letter spans so each letter hovers on its own.
    (function () {
        document.querySelectorAll('.split-letters').forEach(function (el) {
            var text = el.textContent;
            el.textContent = '';
            el.setAttribute('aria-label', text);
            for (var i = 0; i < text.length; i++) {
                var ch = text[i];
                if (ch === ' ') {
                    el.appendChild(document.createTextNode('\u00A0'));
                } else {
                    var span = document.createElement('span');
                    span.className = 'ltr';
                    span.textContent = ch;
                    span.setAttribute('aria-hidden', 'true');
                    el.appendChild(span);
                }
            }
        });
    })();

    // Dark / light toggle (homepage only)
    (function () {
        var root = document.documentElement;
        var btn = document.getElementById('home-theme-btn');
        if (btn) btn.addEventListener('click', function () {
            var cur = root.getAttribute('data-home-theme') === 'light' ? 'light' : 'dark';
            var next = cur === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-home-theme', next);
            try { localStorage.setItem('home-theme', next); } catch (e) {}
            btn.innerHTML = next === 'dark' ? '&#9790;' : '&#9728;';
        });
        // Search toggle
        var sBtn = document.getElementById('home-search-btn');
        var sForm = document.getElementById('home-search-form');
        if (sBtn && sForm) sBtn.addEventListener('click', function () {
            sForm.hidden = !sForm.hidden;
            if (!sForm.hidden) sForm.querySelector('input').focus();
        });
    })();
</script>
</body>
</html>
