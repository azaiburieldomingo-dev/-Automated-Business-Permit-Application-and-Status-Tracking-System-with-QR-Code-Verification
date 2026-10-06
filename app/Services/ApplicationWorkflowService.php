<?php

namespace App\Services;

use App\Events\ApplicationApprovedForPayment;
use App\Events\ApplicationRejected;
use App\Events\ApplicationReturnedForCorrection;
use App\Events\ApplicationSubmitted;
use App\Events\ApplicationUnderReview;
use App\Events\PaymentConfirmed;
use App\Events\PermitReleased;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Application;
use App\Models\ApplicationRemark;
use App\Models\ApplicationStatusHistory;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplicationWorkflowService
{
    /**
     * Legal status transitions: from => [allowed to statuses].
     * Mirrors the design doc's legal-transition table exactly.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        Application::STATUS_DRAFT => [
            Application::STATUS_SUBMITTED,
        ],
        Application::STATUS_SUBMITTED => [
            Application::STATUS_UNDER_REVIEW,
            Application::STATUS_REJECTED,
        ],
        Application::STATUS_UNDER_REVIEW => [
            Application::STATUS_FOR_CORRECTION,
            Application::STATUS_APPROVED_FOR_PAYMENT,
            Application::STATUS_REJECTED,
        ],
        Application::STATUS_FOR_CORRECTION => [
            Application::STATUS_SUBMITTED,
        ],
        Application::STATUS_APPROVED_FOR_PAYMENT => [
            Application::STATUS_PAYMENT_PENDING,
        ],
        Application::STATUS_PAYMENT_PENDING => [
            Application::STATUS_APPROVED_FOR_PAYMENT,
            Application::STATUS_PAYMENT_VERIFIED,
        ],
        Application::STATUS_PAYMENT_VERIFIED => [
            Application::STATUS_PERMIT_RELEASED,
        ],
    ];

    /**
     * Maps a destination status to its domain event class.
     *
     * @var array<string, class-string>
     */
    private const EVENTS = [
        Application::STATUS_SUBMITTED => ApplicationSubmitted::class,
        Application::STATUS_UNDER_REVIEW => ApplicationUnderReview::class,
        Application::STATUS_FOR_CORRECTION => ApplicationReturnedForCorrection::class,
        Application::STATUS_APPROVED_FOR_PAYMENT => ApplicationApprovedForPayment::class,
        Application::STATUS_PAYMENT_VERIFIED => PaymentConfirmed::class,
        Application::STATUS_PERMIT_RELEASED => PermitReleased::class,
        Application::STATUS_REJECTED => ApplicationRejected::class,
    ];

    public function __construct(
        private readonly TrackingNumberService $trackingNumbers,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Validate and perform a status transition. Writes a status-history row and
     * an audit-log row, and fires the matching domain event, all in one transaction.
     *
     * @throws InvalidStatusTransitionException
     */
    public function transition(Application $application, string $to, ?User $actor = null, ?string $note = null): Application
    {
        $from = $application->status;

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            Log::warning('Illegal application status transition attempted.', [
                'application_id' => $application->getKey(),
                'from' => $from,
                'to' => $to,
            ]);

            throw new InvalidStatusTransitionException($from, $to);
        }

        return DB::transaction(function () use ($application, $from, $to, $actor, $note): Application {
            $application->status = $to;

            if ($to === Application::STATUS_SUBMITTED && $application->submitted_at === null) {
                $application->submitted_at = now();
            }

            if ($this->isBploAction($to)) {
                $application->reviewed_at = now();
            }

            $application->save();

            ApplicationStatusHistory::create([
                'application_id' => $application->getKey(),
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $actor?->getKey(),
                'actor_role' => $actor?->role,
                'note' => $note,
                'created_at' => now(),
            ]);

            $this->auditLogger->log(
                'application.status_changed',
                $application,
                ['from' => $from, 'to' => $to],
                $actor,
            );

            if ($eventClass = self::EVENTS[$to] ?? null) {
                event(new $eventClass($application, $actor));
            }

            return $application;
        });
    }

    /**
     * Submit (DRAFT -> SUBMITTED or FOR_CORRECTION -> SUBMITTED), generating a
     * tracking number on first submit with a bounded retry loop against collisions.
     */
    public function submit(Application $application, ?User $actor = null): Application
    {
        if ($application->tracking_number === null) {
            $this->assignTrackingNumber($application);
        }

        return $this->transition($application, Application::STATUS_SUBMITTED, $actor);
    }

    public function beginReview(Application $application, ?User $actor = null): Application
    {
        return $this->transition($application, Application::STATUS_UNDER_REVIEW, $actor);
    }

    public function approveForPayment(Application $application, ?User $actor = null): Application
    {
        return $this->transition($application, Application::STATUS_APPROVED_FOR_PAYMENT, $actor);
    }

    public function returnForCorrection(Application $application, ?User $actor, string $remark): Application
    {
        $this->addRemark($application, $actor, ApplicationRemark::class, 'CORRECTION', $remark);

        return $this->transition($application, Application::STATUS_FOR_CORRECTION, $actor, $remark);
    }

    public function reject(Application $application, ?User $actor, string $remark): Application
    {
        $this->addRemark($application, $actor, ApplicationRemark::class, 'REJECTION', $remark);

        return $this->transition($application, Application::STATUS_REJECTED, $actor, $remark);
    }

    public function markPaymentPending(Application $application, ?User $actor = null): Application
    {
        return $this->transition($application, Application::STATUS_PAYMENT_PENDING, $actor);
    }

    public function markPaymentVerified(Application $application, ?User $actor = null): Application
    {
        return $this->transition($application, Application::STATUS_PAYMENT_VERIFIED, $actor);
    }

    /**
     * Release the permit by delegating to PermitService (which guards on
     * PAYMENT_VERIFIED and performs the PERMIT_RELEASED transition itself).
     */
    public function releasePermit(Application $application, ?User $actor = null): Permit
    {
        return app(PermitService::class)->release($application, $actor);
    }

    private function assignTrackingNumber(Application $application): void
    {
        $year = (int) now()->year;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $tracking = $this->trackingNumbers->generate($year);

            try {
                $application->tracking_number = $tracking;
                $application->save();

                return;
            } catch (QueryException $e) {
                Log::warning('Tracking number collision; retrying.', [
                    'attempt' => $attempt,
                    'tracking_number' => $tracking,
                ]);

                if ($attempt === 5) {
                    Log::error('Tracking number generation failed after 5 attempts.', [
                        'application_id' => $application->getKey(),
                    ]);

                    throw $e;
                }
            }
        }
    }

    private function isBploAction(string $to): bool
    {
        return in_array($to, [
            Application::STATUS_UNDER_REVIEW,
            Application::STATUS_FOR_CORRECTION,
            Application::STATUS_APPROVED_FOR_PAYMENT,
            Application::STATUS_REJECTED,
        ], true);
    }

    /**
     * @param  class-string<ApplicationRemark>  $remarkClass
     */
    private function addRemark(Application $application, ?User $actor, string $remarkClass, string $type, string $body): void
    {
        $remark = new $remarkClass([
            'author_id' => $actor?->getKey(),
            'type' => $type,
            'body' => $body,
        ]);
        $remark->application()->associate($application);
        $remark->save();
    }
}
