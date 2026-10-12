<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentStartRequest;
use App\Models\Application;
use App\Models\Payment;
use App\Policies\PaymentPolicy;
use App\Services\FeeComputationService;
use App\Services\PaymentService;
use App\Support\PaymentMethods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $lastRejected = $application->payments()
            ->where('status', Payment::STATUS_FAILED)
            ->whereNotNull('rejection_reason')
            ->latest('id')
            ->first();

        return view('applicant.payment', [
            'application' => $application,
            'breakdown' => $fees->computeFor($application),
            'methods' => PaymentMethods::all(),
            'manual' => PaymentMethods::isManual(),
            'lastRejected' => $lastRejected,
        ]);
    }

    public function start(PaymentStartRequest $request, Application $application, PaymentService $payments): RedirectResponse
    {
        abort_unless(
            app(PaymentPolicy::class)->pay($request->user(), $application),
            403,
        );

        $data = $request->validated();
        $details = [];
        $storedProof = null;

        if ($request->hasFile('proof')) {
            $file = $request->file('proof');
            // Private disk: receipts are only served through an authorized route.
            $storedProof = $file->storeAs(
                'payments/'.$application->getKey(),
                'receipt-'.Str::uuid().'.'.$file->extension(),
                'local',
            );
            $details = [
                'reference_number' => $data['reference_number'] ?? null,
                'payer_name' => $data['payer_name'] ?? null,
                'payer_number' => $data['payer_number'] ?? null,
                'proof_path' => $storedProof,
                'proof_original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            ];
        }

        try {
            $payments->startPayment($application, $data['method'], $request->user(), $details);
        } catch (RuntimeException $e) {
            if ($storedProof !== null) {
                Storage::disk('local')->delete($storedProof);
            }

            return back()
                ->withInput($request->except('proof'))
                ->withErrors(['reference_number' => $e->getMessage()]);
        }

        $message = PaymentMethods::isManual()
            ? 'Payment details submitted. The BPLO will check your payment and confirm it. You will be notified once it is verified.'
            : 'Payment initiated. Your payment is being processed and will be confirmed shortly.';

        return redirect()
            ->route('applications.show', $application)
            ->with('success', $message);
    }

    public function history(Application $application): View
    {
        $this->authorize('view', $application);

        $application->load(['payments' => fn ($q) => $q->latest(), 'payments.transactions']);

        return view('applicant.payments', [
            'application' => $application,
        ]);
    }

    /**
     * The applicant's own uploaded receipt.
     */
    public function proof(Payment $payment): StreamedResponse
    {
        $this->authorize('view', $payment);
        abort_if($payment->proof_path === null || ! Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, $payment->proof_original_name);
    }
}
