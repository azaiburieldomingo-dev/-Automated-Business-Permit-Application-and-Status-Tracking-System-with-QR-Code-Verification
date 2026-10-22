@extends('layouts.app')

@section('title', 'My Dashboard')

@section('content')
    @php
        use App\Models\Application;
        use App\Models\ApplicationRequirement;
        use App\Models\Payment;

        $actionNeeded = $applications->whereIn('status', [
            Application::STATUS_FOR_CORRECTION,
            Application::STATUS_APPROVED_FOR_PAYMENT,
        ]);
        $paymentPending = $applications->where('status', Application::STATUS_PAYMENT_PENDING);
        $paymentVerified = $applications->where('status', Application::STATUS_PAYMENT_VERIFIED);
        $released = $applications->where('status', Application::STATUS_PERMIT_RELEASED);
    @endphp

    <div class="welcome-banner">
        <h1>Welcome to the BPLO Applicant Portal</h1>
        <p>Manage your business permit applications, monitor application status, and access your permit documents in one place.</p>
    </div>

    {{-- (2) Renewal reminder banner(s) --}}
    @if ($renewalReminders->isNotEmpty())
        @foreach ($renewalReminders as $reminder)
            @php($permit = $reminder['permit'])
            @php($daysLeft = $reminder['daysLeft'])
            <div class="alert alert-warning">
                <strong>Permit renewal reminder — {{ $reminder['application']->business_name }}.</strong>
                Permit {{ $permit->permit_number }} (valid until {{ $permit->valid_until->format('M d, Y') }})
                @if ($reminder['expired'])
                    expired {{ abs($daysLeft) }} day(s) ago.
                @else
                    expires in {{ $daysLeft }} day(s).
                @endif
                Contact the BPLO or <a href="{{ route('applications.create') }}">start a new application</a> to renew.
            </div>
        @endforeach
    @endif

    {{-- (3) Callout blocks --}}
    @if ($actionNeeded->isNotEmpty())
        <div class="card">
            <h2 class="section">Action needed</h2>
            @foreach ($actionNeeded as $application)
                @if ($application->status === Application::STATUS_FOR_CORRECTION)
                    @php($correction = $application->remarks->where('type', 'CORRECTION')->sortByDesc('created_at')->first())
                    <div class="alert alert-warning">
                        <strong>Application {{ $application->tracking_number ?? '—' }} needs corrections.</strong>
                        @if ($correction)
                            {{ $correction->body }}
                        @endif
                        <div><a href="{{ route('applications.edit', $application) }}" class="btn btn-sm">Fix &amp; resubmit</a></div>
                    </div>
                @elseif ($application->status === Application::STATUS_APPROVED_FOR_PAYMENT)
                    <div class="alert alert-info">
                        <strong>Application {{ $application->tracking_number ?? '—' }} is approved for payment.</strong>
                        <div><a href="{{ route('applications.pay', $application) }}" class="btn btn-sm">Pay fees</a></div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    @if ($paymentPending->isNotEmpty())
        <div class="card">
            <h2 class="section">Payment being verified</h2>
            @foreach ($paymentPending as $application)
                <div class="alert alert-info">
                    <strong>Application {{ $application->tracking_number ?? '—' }} — payment submitted.</strong>
                    The Treasurer is verifying your GCash payment.
                    <div><a href="{{ route('applications.payments', $application) }}" class="btn btn-sm btn-secondary">View payment</a></div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($paymentVerified->isNotEmpty())
        <div class="card">
            <h2 class="section">Permit being prepared</h2>
            @foreach ($paymentVerified as $application)
                <div class="alert alert-info">
                    <strong>Application {{ $application->tracking_number ?? '—' }} — payment verified.</strong>
                    The BPLO is preparing your permit for release.
                </div>
            @endforeach
        </div>
    @endif

    @if ($released->isNotEmpty())
        <div class="card">
            <h2 class="section">Good news</h2>
            @foreach ($released as $application)
                <div class="alert alert-success">
                    <strong>Your permit for {{ $application->tracking_number ?? '—' }} is ready to download.</strong>
                    <div><a href="{{ route('applications.permit', $application) }}" class="btn btn-sm">View permit</a></div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- (4) Stat cards --}}
    <div class="stat-grid grid">
        <x-stat-card :value="(int) $stats['total']" label="Total Applications" accent="default" />
        <x-stat-card :value="(int) $stats['pending']" label="Pending Review" accent="amber" />
        <x-stat-card :value="(int) $stats['approved']" label="Approved / Issued" accent="green" />
        <x-stat-card :value="(int) $stats['action']" label="Action Required" accent="red" />
    </div>

    {{-- (5) Per-application detail cards --}}
    @if ($applications->isEmpty())
        <div class="card">
            <x-empty-state icon="📝" title="No applications yet"
                message="Start your first business permit application — it only takes a few minutes.">
                <a href="{{ route('applications.create') }}" class="btn">New Application</a>
            </x-empty-state>
        </div>
    @else
        @foreach ($applications as $application)
            @php
                $order = [
                    Application::STATUS_DRAFT, Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW,
                    Application::STATUS_FOR_CORRECTION, Application::STATUS_APPROVED_FOR_PAYMENT,
                    Application::STATUS_PAYMENT_PENDING, Application::STATUS_PAYMENT_VERIFIED, Application::STATUS_PERMIT_RELEASED,
                ]; // REJECTED intentionally not listed
                $idx = array_search($application->status, $order, true);
                $payIdx = array_search(Application::STATUS_APPROVED_FOR_PAYMENT, $order, true);
                $showFees = $idx !== false && $idx >= $payIdx;
                $current = $application->payments->sortByDesc('id')->first();

                $reqs = $application->requirements;
                $complete = $reqs->where('checklist_status', ApplicationRequirement::CHECK_COMPLETE)->count();
                $missing = $reqs->where('checklist_status', ApplicationRequirement::CHECK_MISSING)->count();
                $needsCorrection = $reqs->where('checklist_status', ApplicationRequirement::CHECK_FOR_CORRECTION);
            @endphp

            <div class="card">
                <div class="page-header">
                    <h2 class="section">{{ $application->business_name }}</h2>
                    <x-status-badge :status="$application->status" />
                </div>
                <p class="muted">
                    Tracking No. <strong>{{ $application->tracking_number ?? '—' }}</strong>
                    · <a href="{{ route('applications.show', $application) }}">View details →</a>
                </p>

                <x-process-tracker :application="$application" viewer="applicant" />

                {{-- Requirements checklist summary --}}
                @if ($reqs->isNotEmpty())
                    <div class="section">
                        <strong>Requirements:</strong>
                        {{ $complete }} complete · {{ $missing }} missing · {{ $needsCorrection->count() }} needs correction
                        @foreach ($needsCorrection as $req)
                            @if ($req->correction_note)
                                <div class="alert alert-warning">
                                    {{ $req->requirementType?->name ?? 'Requirement' }}: {{ $req->correction_note }}
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Fees / payment summary (LOCKED fee-visibility guard) --}}
                @if ($showFees)
                    <div class="section">
                        @if ($application->status === Application::STATUS_APPROVED_FOR_PAYMENT)
                            <strong>Fees &amp; Payment:</strong> Assessment ready — view fees.
                            @if ($current && $current->status === Payment::STATUS_FAILED && $current->rejection_reason)
                                <div class="alert alert-warning">
                                    Your previous payment was not accepted: {{ $current->rejection_reason }}
                                </div>
                            @endif
                            <div><a href="{{ route('applications.pay', $application) }}" class="btn btn-sm">View fees &amp; pay</a></div>
                        @elseif ($current)
                            @php
                                $chip = match ($current->status) {
                                    Payment::STATUS_PAID => 'Paid',
                                    Payment::STATUS_PROCESSING => 'Awaiting verification by the Treasurer',
                                    default => 'Pending',
                                };
                            @endphp
                            <strong>Fees &amp; Payment:</strong> ₱{{ number_format((float) $current->amount, 2) }} · {{ $chip }}
                            <div><a href="{{ route('applications.payments', $application) }}" class="btn btn-sm btn-secondary">View payment history</a></div>
                        @endif
                    </div>
                @endif

                {{-- Permit card (gated on PERMIT_RELEASED + non-null permit relation) --}}
                @if ($application->status === Application::STATUS_PERMIT_RELEASED && $application->permit)
                    @php($permit = $application->permit)
                    <div class="section">
                        <strong>Business Permit</strong>
                        <div class="muted">
                            Permit No. {{ $permit->permit_number }}
                            · Issued {{ $permit->issued_at?->format('M d, Y') }}
                            · Valid until {{ $permit->valid_until?->format('M d, Y') }}
                        </div>
                        <div>
                            <a href="{{ route('applications.permit.download', $application) }}" class="btn btn-sm">Download Permit</a>
                            <a href="{{ route('applications.permit', $application) }}" class="btn btn-sm btn-secondary">View QR</a>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    @endif

    {{-- (6) My Applications history table --}}
    <div class="card">
        <h2 class="section">My Applications</h2>
        <div class="page-header">
            <span></span>
            <a href="{{ route('applications.create') }}" class="btn">New Application</a>
        </div>
        @if ($applications->isEmpty())
            <x-empty-state icon="📝" title="No applications yet"
                message="Start your first business permit application — it only takes a few minutes.">
                <a href="{{ route('applications.create') }}" class="btn">New Application</a>
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Tracking No.</th>
                            <th>Business</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($applications as $application)
                            <tr>
                                <td><strong>{{ $application->tracking_number ?? '—' }}</strong></td>
                                <td>{{ $application->business_name }}</td>
                                <td><x-status-badge :status="$application->status" /></td>
                                <td>{{ $application->updated_at?->format('M d, Y g:i A') }}</td>
                                <td>
                                    @if (in_array($application->status, [Application::STATUS_DRAFT, Application::STATUS_FOR_CORRECTION], true))
                                        <a href="{{ route('applications.edit', $application) }}" class="btn btn-sm btn-secondary">Edit</a>
                                    @endif
                                    <a href="{{ route('applications.show', $application) }}" class="btn btn-sm">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- (7) Notifications list --}}
    <div class="card">
        <h2 class="section">Notifications</h2>
        @if ($notifications->isEmpty())
            <x-empty-state icon="🔔" title="No notifications"
                message="Status updates about your applications will show up here." />
        @else
            <ul class="timeline">
                @foreach ($notifications as $notification)
                    <li>
                        <strong>{{ $notification->title }}</strong>
                        <div>{{ $notification->body }}</div>
                        <div class="meta">{{ $notification->created_at?->format('M d, Y g:i A') }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
