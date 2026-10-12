@extends('layouts.app')

@section('title', 'Pay Fees')

@php
    $available = array_filter($methods, fn ($m) => $m['available']);
    $selected = old('method', array_key_first($available));
    $amount = number_format($breakdown->total, 2);
@endphp

@section('content')
    <div class="page-header">
        <div>
            <h1>Pay Permit Fees</h1>
            <p class="muted mb-0">Tracking Number: <strong>{{ $application->tracking_number }}</strong> · {{ $application->business_name }}</p>
        </div>
        <a href="{{ route('applications.show', $application) }}" class="btn btn-secondary">Back</a>
    </div>

    @if ($lastRejected)
        <div class="alert alert-error">
            <strong>Your previous payment was not accepted.</strong>
            Reason: {{ $lastRejected->rejection_reason }}<br>
            Please pay again or submit the correct reference number and receipt below.
        </div>
    @endif

    <div class="card">
        <h2>Fee Breakdown</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Fee</th><th class="text-right">Amount</th></tr>
                </thead>
                <tbody>
                    @foreach ($breakdown->lines as $line)
                        <tr>
                            <td>{{ $line['name'] }}</td>
                            <td class="text-right">&#8369;{{ number_format($line['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th>TOTAL AMOUNT</th>
                        <th class="text-right">&#8369;{{ $amount }}</th>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if (empty($available))
        <div class="card">
            <h2>Payment</h2>
            <p class="muted mb-0">Online payment is not available yet. Please pay at the Municipal Treasurer's Office.</p>
        </div>
    @elseif ($manual)
        <form method="POST" action="{{ route('applications.pay.start', $application) }}" enctype="multipart/form-data">
            @csrf

            <div class="card">
                <h2>Step 1: Choose how to pay</h2>
                <div class="method-grid">
                    @foreach ($methods as $m)
                        <label class="method-option {{ $m['available'] ? '' : 'is-disabled' }}">
                            <input type="radio" name="method" value="{{ $m['key'] }}"
                                   @checked($selected === $m['key']) @disabled(! $m['available'])>
                            <span class="method-name">{{ $m['label'] }}</span>
                            @unless ($m['available'])
                                <span class="muted" style="font-size:0.8rem">Not yet available</span>
                            @endunless
                        </label>
                    @endforeach
                </div>
                @error('method') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="card">
                <h2>Step 2: Send the payment</h2>

                @foreach ($available as $m)
                    <div class="pay-account" data-method="{{ $m['key'] }}" @if ($selected !== $m['key']) hidden @endif>
                        <div class="pay-account-box">
                            <div>
                                <div class="pay-label">Send to this {{ $m['label'] }} account</div>
                                <div class="pay-number" id="number-{{ $m['key'] }}">{{ $m['number'] }}</div>
                                @if ($m['name'])
                                    <div class="pay-name">Account name: <strong>{{ $m['name'] }}</strong></div>
                                @endif
                            </div>
                            <div class="pay-amount">
                                <div class="pay-label">Exact amount</div>
                                <div class="pay-total">&#8369;{{ $amount }}</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm copy-btn" data-copy="{{ $m['number'] }}">Copy number</button>
                        <button type="button" class="btn btn-secondary btn-sm copy-btn" data-copy="{{ number_format($breakdown->total, 2, '.', '') }}">Copy amount</button>

                        <ol class="pay-steps">
                            <li>Open your <strong>{{ $m['label'] }}</strong> app and choose <strong>Send Money</strong>.</li>
                            <li>Enter the number <strong>{{ $m['number'] }}</strong>@if ($m['name']) and check that the name shows <strong>{{ $m['name'] }}</strong>@endif.</li>
                            <li>Enter the exact amount: <strong>&#8369;{{ $amount }}</strong>.</li>
                            <li>In the message/note, type your tracking number: <strong>{{ $application->tracking_number }}</strong>.</li>
                            <li>Send it, then take a screenshot of the receipt that shows the <strong>Ref No.</strong></li>
                        </ol>
                    </div>
                @endforeach
            </div>

            <div class="card">
                <h2>Step 3: Submit your payment details</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label for="reference_number">Reference Number <span class="muted">(required)</span></label>
                        <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}"
                               placeholder="e.g. 1234 567 890123" inputmode="numeric" autocomplete="off" required>
                        <div class="muted" style="font-size:0.82rem">For GCash, this is the 13-digit <strong>Ref No.</strong> on the receipt.</div>
                        @error('reference_number') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label for="proof">Receipt Screenshot <span class="muted">(required)</span></label>
                        <input type="file" id="proof" name="proof" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="muted" style="font-size:0.82rem">JPG, PNG, or PDF, up to {{ round(config('permit.uploads.max_kb') / 1024) }} MB.</div>
                        @error('proof') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="payer_name">Sender Name <span class="muted">(optional)</span></label>
                        <input type="text" id="payer_name" name="payer_name" value="{{ old('payer_name', $application->applicant_name) }}">
                        @error('payer_name') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label for="payer_number">Mobile Number You Paid From <span class="muted">(optional)</span></label>
                        <input type="tel" id="payer_number" name="payer_number" value="{{ old('payer_number') }}" placeholder="09XXXXXXXXX">
                        @error('payer_number') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>

                <label style="display:flex;gap:8px;align-items:flex-start;font-weight:500">
                    <input type="checkbox" name="confirm_amount" value="1" @checked(old('confirm_amount')) required style="margin-top:4px">
                    <span>I sent exactly <strong>&#8369;{{ $amount }}</strong> and the reference number above is correct.</span>
                </label>
                @error('confirm_amount') <div class="field-error">{{ $message }}</div> @enderror

                <div style="margin-top:16px">
                    <button type="submit" class="btn btn-success"
                            onclick="return confirm('Submit your payment details for verification?')">Submit Payment Details</button>
                </div>

                <p class="muted" style="margin-top:12px;margin-bottom:0">
                    Your application will show <strong>Payment Pending</strong> while the BPLO checks your payment.
                    Your permit is released only after the payment is verified.
                </p>
            </div>
        </form>

        <script>
            (function () {
                var radios = document.querySelectorAll('input[name="method"]');
                var panels = document.querySelectorAll('.pay-account');
                radios.forEach(function (r) {
                    r.addEventListener('change', function () {
                        panels.forEach(function (p) { p.hidden = p.dataset.method !== r.value; });
                    });
                });
                document.querySelectorAll('.copy-btn').forEach(function (b) {
                    b.addEventListener('click', function () {
                        var text = b.dataset.copy, original = b.textContent;
                        var done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = original; }, 1500); };
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(text).then(done);
                        } else {
                            var t = document.createElement('textarea');
                            t.value = text; document.body.appendChild(t); t.select();
                            document.execCommand('copy'); document.body.removeChild(t); done();
                        }
                    });
                });
            })();
        </script>
    @else
        <div class="card">
            <h2>Choose Payment Method</h2>
            <form method="POST" action="{{ route('applications.pay.start', $application) }}">
                @csrf
                <div class="form-group">
                    <label for="method">Payment Method</label>
                    <select id="method" name="method" required>
                        @foreach ($available as $m)
                            <option value="{{ $m['key'] }}">{{ $m['label'] }}</option>
                        @endforeach
                    </select>
                    @error('method') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-success">Pay &#8369;{{ $amount }}</button>
            </form>
            <div class="alert alert-warning" style="margin-top:12px">
                <strong>SANDBOX / TEST MODE.</strong> No real money is charged. Clicking Pay creates a test payment
                with a reference number. The BPLO then confirms it before the permit can be released.
            </div>
        </div>
    @endif
@endsection
