<?php

namespace App\Contracts;

use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Begin a payment attempt. Must move the payment to PROCESSING and return a
     * reference, but must NEVER mark the payment as PAID.
     *
     * @return array<string, mixed>
     */
    public function initiate(Payment $payment): array;

    /**
     * Confirm a payment attempt. This is the only path that may mark a payment PAID/VERIFIED.
     *
     * @param  array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public function verify(Payment $payment, array $callback): array;
}
