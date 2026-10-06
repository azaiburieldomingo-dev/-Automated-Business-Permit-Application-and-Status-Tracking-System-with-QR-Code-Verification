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

                    <span class="muted" style="color:#dbeafe">{{ $user->name }}</span>
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
