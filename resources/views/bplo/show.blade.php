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

    <x-process-tracker :application="$application" viewer="bplo" />

    <div class="card">
        <h2>Actions</h2>

        @switch($application->status)
            @case(Application::STATUS_SUBMITTED)
                <p class="muted">New application. Click <strong>Begin Review</strong> to start checking the requirements.</p>
                @break
            @case(Application::STATUS_UNDER_REVIEW)
                <p class="muted">Check each requirement above. Then approve it for payment, return it for correction, or reject it.</p>
                @break
            @case(Application::STATUS_FOR_CORRECTION)
                <div class="alert alert-warning mb-0">Waiting for the applicant to upload the corrected requirements and resubmit. No action needed until then.</div>
                @break
            @case(Application::STATUS_APPROVED_FOR_PAYMENT)
                <div class="alert alert-info mb-0">
                    <strong>Next step: the applicant pays.</strong> The applicant sees a <strong>Pay Now</strong> button on their
                    application page and pays the fees below through GCash, Maya, or QR Ph. Once they pay, the payment appears
                    in the <strong>Payments</strong> section, where you can confirm it.
                </div>
                @break
            @case(Application::STATUS_PAYMENT_PENDING)
                <div class="alert alert-info mb-0">
                    <strong>The Treasurer is verifying this payment.</strong> No action is needed from BPLO. Once the Treasurer
                    confirms the GCash payment, this application becomes payment-verified and you can release the permit.
                </div>
                @break
            @case(Application::STATUS_PAYMENT_VERIFIED)
                <p class="muted">Payment verified. Click <strong>Release Permit</strong> to issue the Mayor's Permit and its QR code.</p>
                @break
            @case(Application::STATUS_PERMIT_RELEASED)
                <div class="alert alert-success mb-0">Permit released. The applicant can now view, download, and print it.</div>
                @break
            @case(Application::STATUS_REJECTED)
                <div class="alert alert-error mb-0">This application was rejected. See the remarks below.</div>
                @break
        @endswitch

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
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

    @if ($breakdown)
        <div class="card">
            <h2>Fees to be Paid</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Fee</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @foreach ($breakdown->lines as $line)
                            <tr>
                                <td>{{ $line['name'] }}</td>
                                <td class="text-right">&#8369;{{ number_format($line['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <th>TOTAL</th>
                            <th class="text-right">&#8369;{{ number_format($breakdown->total, 2) }}</th>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <h2>Payments</h2>
        @if ($application->payments->isEmpty())
            <p class="muted">
                No payments yet.
                @if ($application->status === Application::STATUS_APPROVED_FOR_PAYMENT)
                    Waiting for the applicant to pay online.
                @endif
            </p>
        @else
            @foreach ($application->payments->sortByDesc('id') as $payment)
                @php $receiving = config('permit.payment.accounts.'.$payment->method); @endphp
                <div class="payment-entry {{ $payment->isAwaitingVerification() ? 'is-pending' : '' }}">
                    <div class="flex-between">
                        <strong>{{ \App\Support\PaymentMethods::label($payment->method) }} · &#8369;{{ number_format((float) $payment->amount, 2) }}</strong>
                        <span>
                            <span class="badge {{ $payment->status === 'PAID' ? 'badge-green' : ($payment->status === 'FAILED' ? 'badge-red' : 'badge-yellow') }}">{{ $payment->status }}</span>
                            <span class="badge {{ $payment->verification_status === 'VERIFIED' ? 'badge-green' : 'badge-gray' }}">{{ $payment->verification_status }}</span>
                        </span>
                    </div>

                    <dl class="kv" style="margin-top:10px">
                        <dt>Submitted</dt><dd>{{ $payment->created_at?->format('M d, Y g:i A') }}</dd>
                        <dt>Reference No.</dt><dd style="font-family:monospace;font-size:1rem;font-weight:700">{{ $payment->reference_number ?? '—' }}</dd>
                        @if ($payment->payer_name || $payment->payer_number)
                            <dt>Sender</dt><dd>{{ $payment->payer_name ?? '—' }} @if ($payment->payer_number) · {{ $payment->payer_number }} @endif</dd>
                        @endif
                        @if (! empty($receiving['number']))
                            <dt>Paid to</dt><dd>{{ $receiving['number'] }} @if (! empty($receiving['name'])) ({{ $receiving['name'] }}) @endif</dd>
                        @endif
                        @if ($payment->proof_path)
                            <dt>Receipt</dt>
                            <dd><a href="{{ route('bplo.payments.proof', $payment) }}" target="_blank" rel="noopener">View receipt ({{ $payment->proof_original_name }})</a></dd>
                        @endif
                        @if ($payment->verified_at)
                            <dt>Verified</dt><dd>{{ $payment->verified_at->format('M d, Y g:i A') }}</dd>
                        @endif
                        @if ($payment->rejection_reason)
                            <dt>Not accepted</dt><dd style="color:var(--red-dark)">{{ $payment->rejection_reason }}</dd>
                        @endif
                    </dl>

                    @if ($payment->isAwaitingVerification())
                        <div class="alert alert-info" style="margin:14px 0 10px">
                            The Treasurer is verifying Ref No. <strong>{{ $payment->reference_number }}</strong>
                            (&#8369;{{ number_format((float) $payment->amount, 2) }}). You can release the permit once it is confirmed.
                        </div>
                    @endif
                </div>
            @endforeach
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
                    <div class="meta">{{ $history->created_at?->format('M d, Y g:i A') }} @if($history->actor_role) · {{ ucfirst($history->actor_role) }} @endif</div>
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
                        <div class="meta">{{ $remark->author?->name }} · {{ $remark->created_at?->format('M d, Y g:i A') }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
