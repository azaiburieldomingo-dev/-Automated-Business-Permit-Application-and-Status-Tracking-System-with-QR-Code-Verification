<!DOCTYPE html>
<html lang="en" data-home-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Register — {{ config('permit.municipality') }}</title>
    <meta name="theme-color" content="#0a1230">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
</head>
<body class="home auth-page">

<div class="auth-hero" style="background-image: linear-gradient(rgba(8,16,44,0.84), rgba(5,10,30,0.93)), url('{{ asset('images/bansud-hall.jpg') }}');">

    {{-- Animated sky --}}
    <div class="sky" aria-hidden="true">
        <div class="moon"></div>
        <div class="cloud cloud-1"></div>
        <div class="cloud cloud-2"></div>
        <div class="stars">
            @for ($i = 0; $i < 3; $i++)
                <span class="star" style="left:{{ random_int(8, 70) }}%; top:{{ random_int(6, 30) }}%; animation-delay:{{ $i * 4 + 2 }}s;"></span>
            @endfor
            @for ($i = 0; $i < 16; $i++)
                <span class="twinkle" style="left:{{ random_int(1, 98) }}%; top:{{ random_int(1, 55) }}%; animation-delay:{{ $i * 0.45 }}s; opacity:{{ random_int(2, 7) / 10 }};"></span>
            @endfor
        </div>
    </div>

    <div class="auth-box auth-box-wide">
        <a href="{{ url('/') }}" class="auth-home-link" title="Back to home">&#8592; Home</a>

        <div class="auth-logo-wrap">
            <img src="{{ asset('images/bansud-logo.png') }}" alt="BPLO Logo" class="auth-logo" onerror="this.style.display='none'">
        </div>
        <h1 class="auth-title">Register Account</h1>
        <p class="auth-muted">{{ config('permit.municipality') }}, {{ config('permit.province') }} &middot; Business Permit System</p>

        @if ($errors->any())
            <div class="alert alert-error" style="max-width:720px;margin:0 auto 16px">
                Please check the highlighted fields below.
            </div>
        @endif

        <form method="POST" action="{{ url('/register') }}" id="register-form">
            @csrf
            {{-- Hidden combined name sent to the backend --}}
            <input type="hidden" name="name" id="full_name">

            <div class="reg-grid">
                {{-- Column 1: Registrant Profile --}}
                <div class="auth-card-glass reg-col">
                    <h3 class="reg-col-title">&#128100; Registrant Profile</h3>
                    <div class="form-group">
                        <label for="first_name">First Name <span class="req">*</span></label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name') }}">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="req">*</span></label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="extension_name">Extension Name</label>
                        <input type="text" id="extension_name" name="extension_name" value="{{ old('extension_name') }}" placeholder="e.g. Jr, III">
                    </div>
                </div>

                {{-- Column 2: Account Details --}}
                <div class="auth-card-glass reg-col">
                    <h3 class="reg-col-title">&#128273; Account Details</h3>
                    <div class="form-group">
                        <label for="email">Email Address <span class="req">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                        @error('email') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="text" id="contact_number" name="contact_number" value="{{ old('contact_number') }}" placeholder="09XXXXXXXXX">
                        @error('contact_number') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" value="{{ old('address') }}">
                        @error('address') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span class="req">*</span></label>
                        <input type="password" id="password" name="password" required>
                        @error('password') <div class="field-error">{{ $message }}</div> @enderror
                        <div class="muted" style="font-size:0.78rem;margin-top:4px">At least 8 characters, with upper &amp; lower case and a number.</div>
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Verify Password <span class="req">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required>
                    </div>
                </div>

                {{-- Column 3: Terms --}}
                <div class="auth-card-glass reg-col">
                    <h3 class="reg-col-title">&#128220; Terms and Conditions</h3>
                    <p class="reg-terms-head">Registration</p>
                    <ol class="reg-terms">
                        <li>The information provided is certified as true and correct.</li>
                        <li>Validate your account via the link sent to your email.</li>
                        <li>Do not create multiple or false accounts.</li>
                        <li>Keep your account credentials private and do not share them.</li>
                    </ol>
                    <p class="reg-terms-head">Disclaimer</p>
                    <p class="muted" style="font-size:0.82rem">
                        In accordance with R.A. 10173 (Data Privacy Act), all collected information
                        is treated with confidentiality and will not be publicly disclosed.
                    </p>
                    <label class="reg-accept">
                        <input type="checkbox" id="accept" required>
                        <span>I accept the Terms and Conditions</span>
                    </label>
                </div>
            </div>

            <div class="reg-submit">
                <button type="submit" class="btn">Register</button>
                <span class="auth-muted" style="margin-left:12px">Already registered? <a href="{{ url('/login') }}">Login</a></span>
            </div>
        </form>
    </div>
</div>

<script>
    // Combine the name parts into the single hidden "name" field before submit.
    document.getElementById('register-form').addEventListener('submit', function () {
        var parts = ['first_name', 'middle_name', 'last_name', 'extension_name']
            .map(function (id) { return (document.getElementById(id).value || '').trim(); })
            .filter(function (v) { return v.length; });
        document.getElementById('full_name').value = parts.join(' ');
    });
</script>
</body>
</html>
