<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $statuses = [
            Payment::STATUS_PENDING,
            Payment::STATUS_PROCESSING,
            Payment::STATUS_PAID,
            Payment::STATUS_FAILED,
            Payment::STATUS_CANCELLED,
        ];

        $payments = Payment::query()
            ->with('application')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->query('q').'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('tracking_number', 'like', $term)
                        ->orWhere('reference_number', 'like', $term);
                });
            })
            ->when(
                $request->filled('status') && in_array($request->query('status'), $statuses, true),
                fn ($query) => $query->where('status', $request->query('status')),
            )
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('treasurer.index', [
            'payments' => $payments,
            'statuses' => $statuses,
        ]);
    }

    public function show(Payment $payment): View
    {
        $payment->load(['application', 'transactions']);

        return view('treasurer.show', [
            'payment' => $payment,
            'application' => $payment->application,
        ]);
    }

    public function confirm(Payment $payment, PaymentService $payments): RedirectResponse
    {
        if (! $payment->isAwaitingVerification()) {
            return back()->with('error', 'This payment is not awaiting verification.');
        }

        try {
            // 'bplo_confirm' is the service's staff-confirmed source string; it
            // is kept unchanged so ManualGateway still accepts the verification.
            $payments->verify($payment, ['source' => 'bplo_confirm'], request()->user());
        } catch (\Throwable $e) {
            return back()->with('error', 'Payment confirmation failed: '.$e->getMessage());
        }

        return redirect()
            ->route('treasurer.payments.show', $payment)
            ->with('success', 'Payment confirmed. The application is now payment-verified and ready for permit release.');
    }

    public function reject(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Please state why the payment was not accepted.',
        ]);

        try {
            $payments->reject($payment, $validated['reason'], $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('treasurer.payments.show', $payment)
            ->with('success', 'Payment rejected. The applicant has been asked to pay again.');
    }

    public function proof(Payment $payment): StreamedResponse
    {
        abort_if($payment->proof_path === null || ! Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, $payment->proof_original_name);
    }
}
