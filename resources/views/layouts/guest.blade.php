<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('permit.municipality').' — Business Permit System')</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <a href="{{ url('/') }}" class="brand">{{ config('permit.municipality') }}</a>
            <nav>
                <a href="{{ url('/') }}">Home</a>
                <a href="{{ url('/track') }}">Track</a>
                <a href="{{ url('/login') }}">Login</a>
            </nav>
        </div>
    </header>

    <main class="page">
        <div class="container">
            <x-flash-messages />
            @yield('content')
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            &copy; {{ date('Y') }} {{ config('permit.municipality') }}, {{ config('permit.province') }}.
        </div>
    </footer>
</body>
</html>
