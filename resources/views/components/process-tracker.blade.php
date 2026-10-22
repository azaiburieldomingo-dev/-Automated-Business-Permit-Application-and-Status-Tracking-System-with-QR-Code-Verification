@props([
    'application',
    'viewer' => 'applicant', // applicant | bplo | treasurer | inspector | admin
])

@php
    use App\Models\Application as App_;

    // Ordered main steps: [status, label, who acts]
    $steps = [
        [App_::STATUS_SUBMITTED,            'Application Submitted', 'Applicant'],
        [App_::STATUS_UNDER_REVIEW,         'BPLO Review',           'BPLO'],
        [App_::STATUS_APPROVED_FOR_PAYMENT, 'Approved for Payment',  'Applicant'],
        [App_::STATUS_PAYMENT_PENDING,      'Payment Verification',  'Treasurer'],
        [App_::STATUS_PAYMENT_VERIFIED,     'Permit Release',        'BPLO'],
        [App_::STATUS_PERMIT_RELEASED,      'Permit Released',       'Applicant'],
    ];

    $order = [
        App_::STATUS_DRAFT, App_::STATUS_SUBMITTED, App_::STATUS_UNDER_REVIEW,
        App_::STATUS_FOR_CORRECTION, App_::STATUS_APPROVED_FOR_PAYMENT,
        App_::STATUS_PAYMENT_PENDING, App_::STATUS_PAYMENT_VERIFIED, App_::STATUS_PERMIT_RELEASED,
    ];
    $currentIndex = array_search($application->status, $order, true);

    // Who acts at the current status, and what each viewer should do.
    $actorNow = match ($application->status) {
        App_::STATUS_DRAFT, App_::STATUS_FOR_CORRECTION, App_::STATUS_APPROVED_FOR_PAYMENT, App_::STATUS_PERMIT_RELEASED => 'Applicant',
        App_::STATUS_SUBMITTED, App_::STATUS_UNDER_REVIEW, App_::STATUS_PAYMENT_VERIFIED => 'BPLO',
        App_::STATUS_PAYMENT_PENDING => 'Treasurer',
        default => null,
    };

    // Message tailored to the person viewing the page.
    $isMyTurn = strtolower($actorNow ?? '') === strtolower($viewer);

    $viewerAction = match (true) {
        $viewer === 'applicant' && $application->status === App_::STATUS_FOR_CORRECTION
            => 'Open the edit page, fix the flagged requirements, and resubmit.',
        $viewer === 'applicant' && $application->status === App_::STATUS_APPROVED_FOR_PAYMENT
            => 'Click “Pay Now” and pay the fees via GCash.',
        $viewer === 'applicant' && $application->status === App_::STATUS_PERMIT_RELEASED
            => 'Your permit is ready — view, download, and print it.',
        $viewer === 'applicant' && $application->status === App_::STATUS_DRAFT
            => 'Finish filling out the form and submit it.',

        $viewer === 'bplo' && in_array($application->status, [App_::STATUS_SUBMITTED, App_::STATUS_UNDER_REVIEW], true)
            => 'Review the requirements, then Approve for Payment, Return for Correction, or Reject.',
        $viewer === 'bplo' && $application->status === App_::STATUS_PAYMENT_VERIFIED
            => 'Payment is verified — click Release Permit to issue the Mayor’s Permit.',

        $viewer === 'treasurer' && $application->status === App_::STATUS_PAYMENT_PENDING
            => 'Check the reference number and receipt, then Confirm or Reject the payment.',

        default => null,
    };
@endphp

<div class="card">
    <h2 class="section">What Happens Next</h2>

    @if ($application->status === App_::STATUS_REJECTED)
        <div class="alert alert-error mb-0">This application was <strong>rejected</strong>. See the remarks for details.</div>
    @else
        <div class="alert {{ $isMyTurn ? 'alert-success' : 'alert-info' }}">
            <strong>
                @if ($isMyTurn)
                    @if ($viewer === 'applicant') It's your turn. @else Action needed from you ({{ ucfirst($viewer) }}). @endif
                @else
                    Waiting for the {{ $actorNow }}.
                @endif
            </strong>
            @if ($viewerAction)
                {{ $viewerAction }}
            @elseif (! $isMyTurn)
                No action needed from you right now.
            @endif
        </div>

        <ol class="progress-steps">
            @foreach ($steps as $i => $step)
                @php
                    $stepIndex = array_search($step[0], $order, true);
                    $state = $stepIndex < $currentIndex ? 'done'
                        : ($stepIndex === $currentIndex ? 'current' : 'upcoming');
                @endphp
                <li class="progress-step is-{{ $state }}">
                    <span class="progress-dot">{{ $i + 1 }}</span>
                    <span class="progress-info">
                        <span class="progress-label">{{ $step[1] }}</span>
                        <span class="progress-who">
                            @if ($state === 'done') Done
                            @elseif ($state === 'current') In progress · {{ $step[2] }}
                            @else {{ $step[2] }} @endif
                        </span>
                    </span>
                </li>
            @endforeach
        </ol>
    @endif
</div>
