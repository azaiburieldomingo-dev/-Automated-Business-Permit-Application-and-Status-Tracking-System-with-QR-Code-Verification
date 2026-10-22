<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('permit.municipality').' — Business Permit System')</title>
    <meta name="theme-color" content="#112E81">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
</head>
<body class="has-sidebar">
    @php
        $user = auth()->user();
        $unread = $user ? \App\Models\Notification::where('user_id', $user->id)->whereNull('read_at')->count() : 0;

        // Role-aware navigation links: [url, label, icon].
        $nav = [];
        if ($user?->isApplicant()) {
            $nav = [
                ['/dashboard', 'Dashboard'],
                ['/applications/create', 'New Application'],
                ['/business-profile', 'Business Profile'],
                ['/settings', 'Settings'],
                ['/help', 'Help'],
            ];
        } elseif ($user?->isBplo()) {
            $nav = [
                ['/bplo/dashboard', 'Dashboard'],
                ['/bplo/applications', 'Applications'],
            ];
        } elseif ($user?->isTreasurer()) {
            $nav = [
                ['/treasurer/dashboard', 'Dashboard'],
                ['/treasurer/payments', 'Payments'],
            ];
        } elseif ($user?->isInspector()) {
            $nav = [
                ['/inspector/dashboard', 'Dashboard'],
                ['/inspector/permits', 'Permits'],
                ['/verify', 'Verify'],
            ];
        } elseif ($user?->isAdmin()) {
            $nav = [
                ['/admin/dashboard', 'Dashboard'],
                ['/admin/users', 'Users'],
                ['/admin/requirement-types', 'Requirements'],
                ['/admin/fee-types', 'Fee Types'],
                ['/admin/fee-rates', 'Fee Rates'],
                ['/admin/audit-logs', 'Audit Logs'],
                ['/admin/settings', 'Settings'],
            ];
        }

        $roleLabel = $user ? ucfirst($user->role) : '';
        $current = '/'.ltrim(request()->path(), '/');
    @endphp

    <input type="checkbox" id="sidebar-toggle" class="sidebar-toggle-cb" hidden>

    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <a href="{{ url('/') }}">
                    <span class="sidebar-brand-mark">BP</span>
                    <span class="sidebar-brand-text">
                        {{ config('permit.municipality') }}
                        <small>Business Permit System</small>
                    </span>
                </a>
            </div>

            <nav class="sidebar-nav">
                @foreach ($nav as [$url, $label])
                    @php $active = $current === $url || str_starts_with($current, rtrim($url, '/').'/'); @endphp
                    <a href="{{ url($url) }}" class="sidebar-link {{ $active ? 'is-active' : '' }}">
                        <span>{{ $label }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <span class="sidebar-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    <span class="sidebar-user-meta">
                        <strong>{{ $user->name }}</strong>
                        <small>{{ $roleLabel }}</small>
                    </span>
                </div>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-secondary btn-block">Logout</button>
                </form>
            </div>
        </aside>

        {{-- Dim backdrop that closes the sidebar on mobile --}}
        <label for="sidebar-toggle" class="sidebar-backdrop"></label>

        <div class="main-area">
            <header class="topbar">
                <label for="sidebar-toggle" class="hamburger" title="Menu">&#9776;</label>
                <span class="topbar-title">@yield('title', 'Dashboard')</span>
                <div class="topbar-right">
                    <x-theme-switcher />
                    <x-notification-bell :count="$unread" />
                </div>
            </header>

            <main class="page">
                <div class="container-fluid">
                    <x-flash-messages />
                    @yield('content')
                </div>
            </main>

            <footer class="footer">
                &copy; {{ date('Y') }} {{ config('permit.municipality') }}, {{ config('permit.province') }}.
                Automated Business Permit Application and Status Tracking System.
            </footer>
        </div>
    </div>

    <script>
        (function () {
            // Close the mobile sidebar after navigating.
            document.querySelectorAll('.sidebar-link').forEach(function (a) {
                a.addEventListener('click', function () {
                    var cb = document.getElementById('sidebar-toggle');
                    if (cb) cb.checked = false;
                });
            });
        })();
    </script>
</body>
</html>
