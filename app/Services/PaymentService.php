<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Events\PaymentConfirmed;
use App\Models\Application;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\PaymentMethods;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentService
{
    public function __construct(
        private readonly FeeComputationService $fees,
        private readonly ApplicationWorkflowService $workflow,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Begin a payment attempt. Guards APPROVED_FOR_PAYMENT. Never marks PAID.
     *
     * @param  array{reference_number?: ?string, payer_name?: ?string, payer_number?: ?string, proof_path?: ?string, proof_original_name?: ?string}  $details
     *                                                                                                                                                         Applicant-reported transfer details (manual mode).
     */
    public function startPayment(Application $application, string $method, ?User $actor = null, array $details = []): Payment
    {
        if ($application->status !== Application::STATUS_APPROVED_FOR_PAYMENT) {
            Log::warning('startPayment blocked: application not APPROVED_FOR_PAYMENT.', [
                'application_id' => $application->getKey(),
                'status' => $application->status,
            ]);

            throw new RuntimeException('Application is not awaiting payment.');
        }

        $reference = isset($details['reference_number'])
            ? self::normalizeReference($details['reference_number'])
            : null;

        if (PaymentMethods::isManual() && ($reference === null || $reference === '')) {
            throw new RuntimeException('Please enter the reference number from your payment receipt.');
        }

        if ($reference !== null && $this->referenceAlreadyUsed($reference)) {
            throw new RuntimeException('This reference number has already been submitted for another payment.');
        }

        $breakdown = $this->fees->computeFor($application);

        return DB::transaction(function () use ($application, $method, $breakdown, $actor, $details, $reference): Payment {
            $payment = Payment::create([
                'application_id' => $application->getKey(),
                'tracking_number' => $application->tracking_number,
                'amount' => $breakdown->total,
                'breakdown_json' => $breakdown->toArray(),
                'method' => $method,
                'reference_number' => $reference,
                'payer_name' => $details['payer_name'] ?? null,
                'payer_number' => $details['payer_number'] ?? null,
                'proof_path' => $details['proof_path'] ?? null,
                'proof_original_name' => $details['proof_original_name'] ?? null,
                'payment_date' => $reference !== null ? now() : null,
                'status' => Payment::STATUS_PENDING,
                'verification_status' => Payment::VERIFY_UNVERIFIED,
            ]);

            $this->gateway()->initiate($payment);

            $this->workflow->markPaymentPending($application, $actor);

            $this->auditLogger->log(
                'payment.started',
                $payment,
                ['method' => $method, 'reference' => $payment->reference_number],
                $actor,
            );

            return $payment->refresh();
        });
    }

    /**
     * Confirm a payment. The only path to PAYMENT_VERIFIED. On failure, returns
     * the application to APPROVED_FOR_PAYMENT.
     *
     * @param  array<string, mixed>  $callback
     */
    public function verify(Payment $payment, array $callback, ?User $actor = null): void
    {
        $application = $payment->application;

        DB::transaction(function () use ($payment, $callback, $actor, $application): void {
            try {
                $result = $this->gateway()->verify($payment, $callback);
            } catch (\Throwable $e) {
                $this->fail($payment, $application, $actor, $e->getMessage());

                throw $e;
            }

            if (($result['status'] ?? null) !== 'paid') {
                $this->fail($payment, $application, $actor, 'gateway did not confirm payment');

                return;
            }

            if ($actor !== null) {
                $payment->verified_by = $actor->getKey();
                $payment->save();
            }

            $this->workflow->markPaymentVerified($application, $actor);

            $this->auditLogger->log(
                'payment.verified',
                $payment,
                ['reference' => $payment->reference_number, 'status' => 'paid'],
                $actor,
            );

            event(new PaymentConfirmed($application, $actor));
        });
    }

    /**
     * BPLO decision: the reported payment could not be matched (wrong amount,
     * unknown reference, unreadable receipt...). The application goes back to
     * APPROVED_FOR_PAYMENT so the applicant can pay or resubmit.
     */
    public function reject(Payment $payment, string $reason, User $actor): void
    {
        if (! $payment->isAwaitingVerification()) {
            throw new RuntimeException('Only a payment awaiting verification can be rejected.');
        }

        DB::transaction(function () use ($payment, $reason, $actor): void {
            $payment->rejection_reason = $reason;
            $payment->verified_by = $actor->getKey();
            $payment->save();

            $this->fail($payment, $payment->application, $actor, $reason);
        });
    }

    /**
     * GCash/Maya references are printed with spaces; store them without.
     */
    public static function normalizeReference(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        return strtoupper((string) preg_replace('/[\s-]+/', '', $reference));
    }

    private function referenceAlreadyUsed(string $reference): bool
    {
        return Payment::query()
            ->where('reference_number', $reference)
            ->whereNotIn('status', [Payment::STATUS_FAILED, Payment::STATUS_CANCELLED])
            ->exists();
    }

    private function fail(Payment $payment, Application $application, ?User $actor, string $reason): void
    {
        $payment->status = Payment::STATUS_FAILED;
        $payment->save();

        PaymentTransaction::create([
            'payment_id' => $payment->getKey(),
            'event' => 'failed',
            'gateway' => config('permit.payment.mode'),
            'payload_json' => ['reason' => $reason],
            'created_at' => now(),
        ]);

        if ($application->status === Application::STATUS_PAYMENT_PENDING) {
            $this->workflow->transition($application, Application::STATUS_APPROVED_FOR_PAYMENT, $actor);
        }

        $this->auditLogger->log(
            'payment.failed',
            $payment,
            ['reason' => $reason],
            $actor,
        );
    }

    /**
     * Resolve the payment gateway keyed on config('permit.payment.mode').
     */
    private function gateway(): PaymentGateway
    {
        $mode = config('permit.payment.mode');

        return match ($mode) {
            'sandbox' => app(SandboxGateway::class),
            'manual' => app(ManualGateway::class),
            default => app(PaymentGateway::class),
        };
    }
}
