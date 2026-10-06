<?php

namespace App\Http\Controllers\Bplo;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function confirm(Payment $payment, PaymentService $payments): RedirectResponse
    {
        $this->authorize('review', Application::class);

        try {
            $payments->verify($payment, ['source' => 'bplo_confirm'], request()->user());
        } catch (\Throwable $e) {
            return back()->with('error', 'Payment confirmation failed: '.$e->getMessage());
        }

        $payment->refresh();
        $application = $payment->application;

        return redirect()
            ->route('bplo.applications.show', $application)
            ->with('success', 'Payment confirmed. The application is now payment-verified and ready for permit release.');
    }
}
