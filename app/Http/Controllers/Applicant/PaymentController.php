<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentStartRequest;
use App\Models\Application;
use App\Policies\PaymentPolicy;
use App\Services\FeeComputationService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    public function show(Application $application, FeeComputationService $fees): View
    {
        $this->authorize('view', $application);

        abort_unless(
            $application->status === Application::STATUS_APPROVED_FOR_PAYMENT,
            403,
            'This application is not awaiting payment.',
        );

        $enabledMethods = collect((array) config('permit.payment.methods'))
            ->filter(fn ($enabled): bool => (bool) $enabled)
            ->keys()
            ->all();

        return view('applicant.payment', [
            'application' => $application,
            'breakdown' => $fees->computeFor($application),
            'methods' => $enabledMethods,
        ]);
    }

    public function start(PaymentStartRequest $request, Application $application, PaymentService $payments): RedirectResponse
    {
        abort_unless(
            app(PaymentPolicy::class)->pay($request->user(), $application),
            403,
        );

        try {
            $payments->startPayment($application, $request->validated()['method'], $request->user());
        } catch (RuntimeException $e) {
            return redirect()
                ->route('applications.show', $application)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Payment initiated. Your payment is being processed and will be confirmed shortly.');
    }

    public function history(Application $application): View
    {
        $this->authorize('view', $application);

        $application->load(['payments' => fn ($q) => $q->latest(), 'payments.transactions']);

        return view('applicant.payments', [
            'application' => $application,
        ]);
    }
}
