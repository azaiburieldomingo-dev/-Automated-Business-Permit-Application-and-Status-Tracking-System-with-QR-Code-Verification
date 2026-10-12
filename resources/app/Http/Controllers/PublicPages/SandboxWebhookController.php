<?php

namespace App\Http\Controllers\PublicPages;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Simulated gateway callback. Sandbox-only, HMAC-signed. Models the real
 * gateway's asynchronous payment confirmation.
 */
class SandboxWebhookController extends Controller
{
    public function handle(Request $request, Payment $payment, PaymentService $payments): Response
    {
        // Clearly labeled sandbox-only.
        if (config('permit.payment.mode') !== 'sandbox') {
            return response('sandbox webhook not available', Response::HTTP_NOT_FOUND);
        }

        $secret = config('permit.payment.webhook_secret');

        if ($secret === null || $secret === '') {
            Log::warning('Sandbox webhook called while PAYMENT_WEBHOOK_SECRET is empty; disabled.');

            return response(
                'sandbox webhook disabled; set PAYMENT_WEBHOOK_SECRET',
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        $provided = (string) $request->header('X-Sandbox-Signature', '');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            Log::warning('Sandbox webhook signature mismatch.', [
                'payment_id' => $payment->getKey(),
            ]);

            return response('invalid signature', Response::HTTP_UNAUTHORIZED);
        }

        $payments->verify($payment, ['source' => 'sandbox_webhook'] + $request->all());

        return response('ok', Response::HTTP_OK);
    }
}
