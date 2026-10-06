<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Support\Str;

/**
 * Clearly-labeled sandbox gateway. initiate() only ever reaches PROCESSING;
 * verify() is the sole path to PAID + VERIFIED.
 */
class SandboxGateway implements PaymentGateway
{
    private const GATEWAY = 'sandbox';

    public function initiate(Payment $payment): array
    {
        $reference = 'SANDBOX-'.Str::uuid()->toString();

        $payment->status = Payment::STATUS_PROCESSING;
        $payment->reference_number = $reference;
        $payment->save();

        PaymentTransaction::create([
            'payment_id' => $payment->getKey(),
            'event' => 'initiated',
            'gateway' => self::GATEWAY,
            'payload_json' => ['status' => 'processing', 'reference' => $reference],
            'created_at' => now(),
        ]);

        return ['status' => 'processing', 'reference' => $reference];
    }

    public function verify(Payment $payment, array $callback): array
    {
        $now = now();

        $payment->status = Payment::STATUS_PAID;
        $payment->verification_status = Payment::VERIFY_VERIFIED;
        $payment->payment_date = $now;
        $payment->verified_at = $now;
        $payment->save();

        PaymentTransaction::create([
            'payment_id' => $payment->getKey(),
            'event' => 'verified',
            'gateway' => self::GATEWAY,
            'payload_json' => [
                'status' => 'paid',
                'reference' => $payment->reference_number,
            ],
            'created_at' => $now,
        ]);

        return ['status' => 'paid'];
    }
}
