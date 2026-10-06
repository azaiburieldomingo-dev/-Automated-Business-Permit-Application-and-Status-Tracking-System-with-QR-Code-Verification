<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingPayment(): Payment
    {
        $user = User::create([
            'name' => 'Webhook Applicant',
            'email' => 'webhook_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);
        $user->role = User::ROLE_APPLICANT;
        $user->save();

        $application = new Application([
            'applicant_name' => 'Webhook Applicant',
            'contact_number' => '09171234567',
            'email' => $user->email,
            'business_name' => 'Webhook Biz',
            'business_address' => '2 Mabini St',
            'business_nature' => 'Services',
            'ownership_type' => 'Sole Proprietorship',
            'barangay' => 'Proper',
            'number_of_employees' => 1,
        ]);
        $application->user()->associate($user);
        $application->status = Application::STATUS_PAYMENT_PENDING;
        $application->tracking_number = 'BANSUD-'.date('Y').'-000777';
        $application->save();

        return Payment::create([
            'application_id' => $application->getKey(),
            'tracking_number' => $application->tracking_number,
            'amount' => 1000,
            'breakdown_json' => ['lines' => [], 'total' => 1000],
            'method' => 'gcash',
            'reference_number' => 'SANDBOX-test',
            'status' => Payment::STATUS_PROCESSING,
            'verification_status' => Payment::VERIFY_UNVERIFIED,
        ]);
    }

    public function test_webhook_verifies_with_correct_signature(): void
    {
        config(['permit.payment.webhook_secret' => 'test-secret']);
        $payment = $this->makePendingPayment();

        $body = json_encode(['event' => 'payment.paid']);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        $response = $this->call(
            'POST',
            '/sandbox/payments/'.$payment->getKey().'/webhook',
            [],
            [],
            [],
            ['HTTP_X-Sandbox-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body,
        );

        $response->assertOk();
        $this->assertSame(
            Application::STATUS_PAYMENT_VERIFIED,
            $payment->application->fresh()->status,
        );
    }

    public function test_webhook_rejects_bad_signature(): void
    {
        config(['permit.payment.webhook_secret' => 'test-secret']);
        $payment = $this->makePendingPayment();

        $body = json_encode(['event' => 'payment.paid']);

        $response = $this->call(
            'POST',
            '/sandbox/payments/'.$payment->getKey().'/webhook',
            [],
            [],
            [],
            ['HTTP_X-Sandbox-Signature' => 'wrong', 'CONTENT_TYPE' => 'application/json'],
            $body,
        );

        $response->assertStatus(401);
        $this->assertSame(
            Application::STATUS_PAYMENT_PENDING,
            $payment->application->fresh()->status,
        );
    }

    public function test_webhook_returns_503_when_secret_blank(): void
    {
        config(['permit.payment.webhook_secret' => '']);
        $payment = $this->makePendingPayment();

        $body = json_encode(['event' => 'payment.paid']);

        $response = $this->call(
            'POST',
            '/sandbox/payments/'.$payment->getKey().'/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $body,
        );

        $response->assertStatus(503);
    }
}
