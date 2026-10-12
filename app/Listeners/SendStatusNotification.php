<?php

namespace App\Listeners;

use App\Events\ApplicationApprovedForPayment;
use App\Events\ApplicationRejected;
use App\Events\ApplicationReturnedForCorrection;
use App\Events\ApplicationSubmitted;
use App\Events\ApplicationUnderReview;
use App\Events\PaymentConfirmed;
use App\Events\PermitReleased;
use App\Models\Payment;
use App\Services\NotificationService;

/**
 * Handles all seven domain events and routes a notification to the applicant.
 *
 * Deliberately NOT ShouldQueue — runs synchronously inline with the request so
 * notifications fire with no queue worker (QUEUE_CONNECTION=database).
 */
class SendStatusNotification
{
    /**
     * Maps event class to [type, title, body-builder].
     *
     * @var array<class-string, array{0: string, 1: string}>
     */
    private const TEMPLATES = [
        ApplicationSubmitted::class => ['application.submitted', 'Application Submitted'],
        ApplicationUnderReview::class => ['application.under_review', 'Application Under Review'],
        ApplicationReturnedForCorrection::class => ['application.for_correction', 'Application Returned for Correction'],
        ApplicationApprovedForPayment::class => ['application.approved_for_payment', 'Application Approved for Payment'],
        PaymentConfirmed::class => ['payment.confirmed', 'Payment Confirmed'],
        PermitReleased::class => ['permit.released', 'Permit Released'],
        ApplicationRejected::class => ['application.rejected', 'Application Rejected'],
    ];

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function handle(object $event): void
    {
        $template = self::TEMPLATES[$event::class] ?? null;

        if ($template === null) {
            return;
        }

        [$type, $title] = $template;

        $application = $event->application;
        $applicant = $application->user;

        // Back to "approved for payment" because the BPLO rejected a payment.
        if ($event instanceof ApplicationApprovedForPayment && $this->rejectedPayment($application)) {
            $type = 'payment.rejected';
            $title = 'Payment Not Accepted';
        }

        if ($applicant === null) {
            return;
        }

        $this->notifications->notify(
            $applicant,
            $type,
            $title,
            $this->body($event, $application),
            $application,
        );
    }

    private function body(object $event, $application): string
    {
        $tracking = $application->tracking_number ?? 'your application';

        return match ($event::class) {
            ApplicationSubmitted::class => "Your application {$tracking} has been submitted and is awaiting review.",
            ApplicationUnderReview::class => "Your application {$tracking} is now under review by the BPLO.",
            ApplicationReturnedForCorrection::class => $this->remarkBody($application, 'CORRECTION')
                ?? "Your application {$tracking} needs corrections. Please review the remarks.",
            ApplicationApprovedForPayment::class => ($rejected = $this->rejectedPayment($application))
                ? "Your payment for {$tracking} (Ref No. {$rejected->reference_number}) was not accepted: {$rejected->rejection_reason}. Please pay again."
                : "Your application {$tracking} has been approved. You may now pay the applicable fees.",
            PaymentConfirmed::class => "Payment for application {$tracking} has been confirmed.",
            PermitReleased::class => "Your Mayor's Permit for application {$tracking} has been released and is ready to download.",
            ApplicationRejected::class => $this->remarkBody($application, 'REJECTION')
                ?? "Your application {$tracking} has been rejected.",
            default => "Status update for application {$tracking}.",
        };
    }

    private function rejectedPayment($application): ?Payment
    {
        $latest = $application->payments()->latest('id')->first();

        return $latest && $latest->status === Payment::STATUS_FAILED && $latest->rejection_reason
            ? $latest
            : null;
    }

    private function remarkBody($application, string $type): ?string
    {
        $remark = $application->remarks()
            ->where('type', $type)
            ->latest('id')
            ->first();

        return $remark?->body;
    }
}
