<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Models\PaymentTransaction;

/**
 * Manual "send money" payments (GCash / Maya / QR Ph to the municipality's
 * receiving account). The applicant reports the reference number and receipt;
 * nothing is treated as paid until a BPLO officer checks it against the
 * account's transaction history and confirms it.
 */
class ManualGateway implements PaymentGateway
{
    private const GATEWAY = 'manual';

    public function initiate(Payment $payment): array
    {
        $payment->status = Payment::STATUS_PROCESSING;
        $payment->save();

        PaymentTransaction::create([
            'payment_id' => $payment->getKey(),
            'event' => 'submitted',
            'gateway' => self::GATEWAY,
            'payload_json' => [
                'status' => 'processing',
                'reference' => $payment->reference_number,
                'method' => $payment->method,
                'has_proof' => $payment->proof_path !== null,
            ],
            'created_at' => now(),
        ]);

        return ['status' => 'processing', 'reference' => $payment->reference_number];
    }

    /**
     * Only reached through the BPLO "Confirm Payment" action, i.e. after a
     * person has matched the reference number to a received transfer.
     */
    public function verify(Payment $payment, array $callback): array
    {
        if (($callback['source'] ?? null) !== 'bplo_confirm') {
            return ['status' => 'unverified'];
        }

        $now = now();

        $payment->status = Payment::STATUS_PAID;
        $payment->verification_status = Payment::VERIFY_VERIFIED;
        $payment->payment_date = $payment->payment_date ?? $now;
        $payment->verified_at = $now;
        $payment->save();

        PaymentTransaction::create([
            'payment_id' => $payment->getKey(),
            'event' => 'verified',
            'gateway' => self::GATEWAY,
            'payload_json' => ['status' => 'paid', 'reference' => $payment->reference_number],
            'created_at' => $now,
        ]);

        return ['status' => 'paid'];
    }
}
