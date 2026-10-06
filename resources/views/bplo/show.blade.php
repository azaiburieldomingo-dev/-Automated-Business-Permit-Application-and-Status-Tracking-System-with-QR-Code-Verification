@extends('layouts.app')

@section('title', 'Review Application')

@php use App\Models\Application; @endphp

@section('content')
    <div class="page-header">
        <div>
            <h1>Review Application</h1>
            <p class="muted mb-0">Tracking Number: <strong>{{ $application->tracking_number }}</strong></p>
        </div>
        <x-status-badge :status="$application->status" />
    </div>

    <div class="card">
        <h2>Applicant Information</h2>
        <dl class="kv">
            <dt>Applicant Name</dt><dd>{{ $application->applicant_name }}</dd>
            <dt>Contact Number</dt><dd>{{ $application->contact_number }}</dd>
            <dt>Email</dt><dd>{{ $application->email }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>Business Information</h2>
        <dl class="kv">
            <dt>Business Name</dt><dd>{{ $application->business_name }}</dd>
            <dt>Business Address</dt><dd>{{ $application->business_address }}</dd>
            <dt>Barangay</dt><dd>{{ $application->barangay }}</dd>
            <dt>Nature</dt><dd>{{ $application->business_nature }}</dd>
            <dt>Ownership Type</dt><dd>{{ $application->ownership_type }}</dd>
            <dt>Employees</dt><dd>{{ $application->number_of_employees }}</dd>
            <dt>DTI / SEC</dt><dd>{{ $application->dti_sec_type ? $application->dti_sec_type.' — '.$application->dti_sec_number : '—' }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>Requirements</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Document</th><th>File</th><th>Checklist</th><th>Flag</th></tr>
                </thead>
                <tbody>
                    @foreach ($application->requirements as $req)
                        <tr>
                            <td>{{ $req->requirementType?->name ?? '—' }}</td>
                            <td>
                                @if ($req->stored_path)
                                    <a href="{{ route('bplo.requirements.file', $req) }}">{{ $req->original_filename }}</a>
                                @else
                                    <span class="muted">No file</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $req->checklist_status === 'COMPLETE' ? 'badge-green' : ($req->checklist_status === 'FOR_CORRECTION' ? 'badge-yellow' : 'badge-gray') }}">{{ $req->checklist_status }}</span>
                                @if ($req->correction_note)<div class="muted" style="font-size:0.8rem">{{ $req->correction_note }}</div>@endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('bplo.applications.requirements.flag', [$application, $req]) }}" style="display:flex;gap:6px;flex-wrap:wrap">
                                    @csrf
                                    <select name="checklist_status">
                                        @foreach (['COMPLETE', 'MISSING', 'FOR_CORRECTION'] as $cs)
                                            <option value="{{ $cs }}" @selected($req->checklist_status === $cs)>{{ $cs }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="correction_note" placeholder="Note" value="{{ $req->correction_note }}">
                                    <button type="submit" class="btn btn-sm">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Actions</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            @if ($application->status === Application::STATUS_SUBMITTED)
                <form method="POST" action="{{ route('bplo.applications.review', $application) }}" class="inline-form">
                    @csrf <button type="submit" class="btn">Begin Review</button>
                </form>
            @endif

            @if ($application->status === Application::STATUS_UNDER_REVIEW)
                <form method="POST" action="{{ route('bplo.applications.approve', $application) }}" class="inline-form">
                    @csrf <button type="submit" class="btn btn-success">Approve for Payment</button>
                </form>
            @endif

            @if (in_array($application->status, [Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW]))
                <form method="POST" action="{{ route('bplo.applications.reject', $application) }}" class="inline-form" onsubmit="return confirm('Reject this application?')">
                    @csrf
                    <input type="text" name="remark" placeholder="Rejection remark (required)" required>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </form>
            @endif

            @if ($application->status === Application::STATUS_UNDER_REVIEW)
                <form method="POST" action="{{ route('bplo.applications.return', $application) }}" class="inline-form">
                    @csrf
                    <input type="text" name="remark" placeholder="Correction remark (required)" required>
                    <button type="submit" class="btn btn-secondary">Return for Correction</button>
                </form>
            @endif

            @if ($application->status === Application::STATUS_PAYMENT_VERIFIED)
                <form method="POST" action="{{ route('bplo.applications.release', $application) }}" class="inline-form">
                    @csrf <button type="submit" class="btn btn-success">Release Permit</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <h2>Payments</h2>
        @if ($application->payments->isEmpty())
            <p class="muted">No payments.</p>
        @else
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Status</th><th>Verification</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($application->payments->sortByDesc('created_at') as $payment)
                            <tr>
                                <td>{{ $payment->created_at?->format('M d, Y H:i') }}</td>
                                <td>{{ strtoupper($payment->method) }}</td>
                                <td>&#8369;{{ number_format((float) $payment->amount, 2) }}</td>
                                <td>{{ $payment->status }}</td>
                                <td>{{ $payment->verification_status }}</td>
                                <td>
                                    @if ($payment->verification_status !== 'VERIFIED' && $payment->status !== 'PAID')
                                        <form method="POST" action="{{ route('bplo.payments.confirm', $payment) }}" class="inline-form">
                                            @csrf <button type="submit" class="btn btn-sm btn-success">Confirm Payment</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($application->permit)
        <div class="card">
            <h2>Permit</h2>
            <dl class="kv">
                <dt>Permit Number</dt><dd>{{ $application->permit->permit_number }}</dd>
                <dt>Status</dt><dd>{{ $application->permit->status }}</dd>
                <dt>Valid Until</dt><dd>{{ $application->permit->valid_until?->format('M d, Y') }}</dd>
            </dl>
            <form method="POST" action="{{ route('bplo.permits.regenerate', $application->permit) }}" class="inline-form">
                @csrf <button type="submit" class="btn btn-secondary">Regenerate Token &amp; PDF</button>
            </form>
        </div>
    @endif

    <div class="card">
        <h2>Status History</h2>
        <ul class="timeline">
            @foreach ($application->statusHistories as $history)
                <li>
                    <strong>{{ $history->to_status }}</strong>
                    @if ($history->from_status)<span class="muted">(from {{ $history->from_status }})</span>@endif
                    @if ($history->note)<div>{{ $history->note }}</div>@endif
                    <div class="meta">{{ $history->created_at?->format('M d, Y H:i') }} @if($history->actor_role) · {{ ucfirst($history->actor_role) }} @endif</div>
                </li>
            @endforeach
        </ul>
    </div>

    @if ($application->remarks->isNotEmpty())
        <div class="card">
            <h2>Remarks</h2>
            <ul class="timeline">
                @foreach ($application->remarks->sortByDesc('created_at') as $remark)
                    <li>
                        <span class="badge {{ $remark->type === 'REJECTION' ? 'badge-red' : 'badge-yellow' }}">{{ $remark->type }}</span>
                        <div>{{ $remark->body }}</div>
                        <div class="meta">{{ $remark->author?->name }} · {{ $remark->created_at?->format('M d, Y H:i') }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
