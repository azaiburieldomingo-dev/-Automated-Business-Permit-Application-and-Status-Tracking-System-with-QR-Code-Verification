<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * The owning applicant may pay only while the application is APPROVED_FOR_PAYMENT.
     */
    public function pay(User $user, Application $application): bool
    {
        return $user->getKey() === $application->user_id
            && $application->status === Application::STATUS_APPROVED_FOR_PAYMENT;
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->isBplo() || $user->isAdmin()) {
            return true;
        }

        return $user->getKey() === $payment->application?->user_id;
    }
}
