<?php

namespace Tests\Feature\Payment;

use App\Models\Application;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class SandboxWebhookTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    private function pendingPayment(): Payment
    {
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_PAYMENT_PENDING);

        return $this->makePendingPayment($application);
    }

    private function postWebhook(Payment $payment, string $body, ?string $signature): TestResponse
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($signature !== null) {
            $headers['HTTP_X-Sandbox-Signature'] = $signature;
        }

        return $this->call(
            'POST',
            '/sandbox/payments/'.$payment->getKey().'/webhook',
            [],
            [],
            [],
            $headers,
            $body,
        );
    }

    public function test_webhook_verifies_with_correct_signature(): void
    {
        config(['permit.payment.webhook_secret' => 'test-secret']);
        $payment = $this->pendingPayment();

        $body = json_encode(['event' => 'payment.paid']);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        $this->postWebhook($payment, $body, $signature)->assertOk();

        $this->assertSame(
            Application::STATUS_PAYMENT_VERIFIED,
            $payment->application->fresh()->status,
        );
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
    }

    public function test_webhook_rejects_bad_signature(): void
    {
        config(['permit.payment.webhook_secret' => 'test-secret']);
        $payment = $this->pendingPayment();

        $body = json_encode(['event' => 'payment.paid']);

        $this->postWebhook($payment, $body, 'this-is-wrong')->assertStatus(401);

        $this->assertSame(
            Application::STATUS_PAYMENT_PENDING,
            $payment->application->fresh()->status,
        );
    }

    public function test_webhook_rejects_missing_signature(): void
    {
        config(['permit.payment.webhook_secret' => 'test-secret']);
        $payment = $this->pendingPayment();

        $this->postWebhook($payment, json_encode(['event' => 'payment.paid']), null)
            ->assertStatus(401);

        $this->assertSame(
            Application::STATUS_PAYMENT_PENDING,
            $payment->application->fresh()->status,
        );
    }

    public function test_webhook_returns_503_when_secret_is_blank(): void
    {
        config(['permit.payment.webhook_secret' => '']);
        $payment = $this->pendingPayment();

        $this->postWebhook($payment, json_encode(['event' => 'payment.paid']), null)
            ->assertStatus(503);

        $this->assertSame(
            Application::STATUS_PAYMENT_PENDING,
            $payment->application->fresh()->status,
        );
    }
}
