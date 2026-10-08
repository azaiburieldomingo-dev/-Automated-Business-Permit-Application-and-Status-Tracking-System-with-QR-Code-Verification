<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('permit.municipality').' — Business Permit System')</title>
    <meta name="theme-color" content="#36454F">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
</head>
<body>
    @php
        $user = auth()->user();
        $unread = $user ? \App\Models\Notification::where('user_id', $user->id)->whereNull('read_at')->count() : 0;
    @endphp

    <header class="navbar">
        <div class="container">
            <a href="{{ url('/') }}" class="brand">{{ config('permit.municipality') }}</a>
            <nav>
                @auth
                    @if ($user->isApplicant())
                        <a href="{{ url('/dashboard') }}">Dashboard</a>
                        <a href="{{ url('/applications/create') }}">New Application</a>
                    @elseif ($user->isBplo())
                        <a href="{{ url('/bplo/dashboard') }}">Dashboard</a>
                        <a href="{{ url('/bplo/applications') }}">Applications</a>
                    @elseif ($user->isTreasurer())
                        <a href="{{ url('/treasurer/dashboard') }}">Dashboard</a>
                        <a href="{{ url('/treasurer/payments') }}">Payments</a>
                    @elseif ($user->isInspector())
                        <a href="{{ url('/inspector/dashboard') }}">Dashboard</a>
                        <a href="{{ url('/inspector/permits') }}">Permits</a>
                        <a href="{{ url('/verify') }}">Verify</a>
                    @elseif ($user->isAdmin())
                        <a href="{{ url('/admin/dashboard') }}">Dashboard</a>
                        <a href="{{ url('/admin/users') }}">Users</a>
                        <a href="{{ url('/admin/requirement-types') }}">Requirements</a>
                        <a href="{{ url('/admin/fee-types') }}">Fee Types</a>
                        <a href="{{ url('/admin/fee-rates') }}">Fee Rates</a>
                        <a href="{{ url('/admin/audit-logs') }}">Audit Logs</a>
                        <a href="{{ url('/admin/settings') }}">Settings</a>
                    @endif

                    <x-notification-bell :count="$unread" />

                    <span class="user-name">{{ $user->name }}</span>
                    <form method="POST" action="{{ url('/logout') }}" class="inline-form">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-secondary">Logout</button>
                    </form>
                @else
                    <a href="{{ url('/track') }}">Track</a>
                    <a href="{{ url('/login') }}">Login</a>
                @endauth
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
            Automated Business Permit Application and Status Tracking System.
        </div>
    </footer>
</body>
</html>
