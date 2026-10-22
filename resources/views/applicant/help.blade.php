@extends('layouts.app')

@section('title', 'Help & Support')

@php($gcash = $paymentMethods['gcash'] ?? null)

@section('content')
    <div class="page-header">
        <h1>Help &amp; Support</h1>
        <a href="{{ url('/dashboard') }}" class="btn btn-secondary">Back</a>
    </div>

    <div class="card">
        <h2>BPLO Office</h2>
        <p class="muted">Business Permits and Licensing Office — {{ $municipality }}, {{ $province }}.</p>
        <div class="table-wrap">
            <table class="data">
                <tbody>
                    <tr>
                        <th>Office Hours</th>
                        <td>
                            @if (!empty($support['office_hours']))
                                {{ $support['office_hours'] }}
                            @else
                                <span class="muted">Not yet provided</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Office Address</th>
                        <td>
                            @if (!empty($support['office_address']))
                                {{ $support['office_address'] }}
                            @else
                                <span class="muted">Not yet provided</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Contact Phone</th>
                        <td>
                            @if (!empty($support['contact_phone']))
                                {{ $support['contact_phone'] }}
                            @else
                                <span class="muted">Not yet provided</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Contact Email</th>
                        <td>
                            @if (!empty($support['contact_email']))
                                {{ $support['contact_email'] }}
                            @else
                                <span class="muted">Not yet provided</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>How to Pay (GCash, Manual)</h2>
        @if ($gcash && $gcash['available'])
            <p>Send your assessed fees to the municipal GCash account below, then submit the reference
                number and a screenshot on your application's payment page.</p>
            <div class="table-wrap">
                <table class="data">
                    <tbody>
                        <tr>
                            <th>{{ $gcash['label'] }} Number</th>
                            <td>{{ $gcash['number'] }}</td>
                        </tr>
                        <tr>
                            <th>Account Name</th>
                            <td>{{ $gcash['name'] ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="muted" style="margin-top:12px">
                After you submit your payment details, the <strong>Treasurer</strong> reviews and verifies
                the payment. You'll be notified once it is verified and your permit is being prepared.
            </p>
        @else
            <p class="muted">Online payment details are not yet available. Please contact the BPLO office for payment instructions.</p>
        @endif
        <p class="muted">Pay only after your application reaches <em>Approved for Payment</em>. Fees are
            computed by the BPLO assessment — never pay a fixed amount before you are assessed.</p>
    </div>

    <div class="card">
        <h2>Frequently Asked Questions</h2>

        <h3>What are the application statuses?</h3>
        <p>Your application moves through these stages: Draft, Submitted, Under Review, For Correction,
            Approved for Payment, Payment Pending, Payment Verified, Permit Released, and Rejected. The
            status you see on your dashboard is the same one BPLO staff update — there is one source of truth.</p>

        <h3>My application is "For Correction" — what do I do?</h3>
        <p>Open the application, read the BPLO remarks, fix or replace the flagged requirements, and resubmit.</p>

        <h3>When can I pay?</h3>
        <p>Only once your application is <em>Approved for Payment</em>. The assessed amount appears on the
            payment page; the Treasurer verifies your payment after you submit it.</p>

        <h3>When can I download my permit?</h3>
        <p>Your business permit and its QR code become available only after the BPLO releases it
            (<em>Permit Released</em>). The permit is valid for {{ $validMonths }} months.</p>

        <h3>When should I renew?</h3>
        <p>We remind you about {{ $reminderDays }} days before your permit's validity expires. Start a new
            application from the dashboard to renew.</p>
    </div>

    <div class="card">
        <h2>Tracking &amp; Verification</h2>
        <p>Every application has a unique tracking number in the format
            <strong>BANSUD-YYYY-NNNNNN</strong>. You can check an application's progress on the public
            <a href="{{ route('track.show') }}">Track Status</a> page using your tracking number together with
            a second detail for security.</p>
        <p>Released permits carry a QR code. Scanning it opens the public verification page
            (<code>/verify/{token}</code>), which confirms the permit is genuine and shows its validity.</p>
    </div>
@endsection
