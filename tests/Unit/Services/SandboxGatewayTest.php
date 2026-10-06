<?php

namespace Tests\Unit\Services;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\Services\SandboxGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function makePayment(): Payment
    {
        $user = User::create([
            'name' => 'Gateway Tester',
            'email' => 'gw_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);
        $user->role = User::ROLE_APPLICANT;
        $user->save();

        $application = new Application([
            'applicant_name' => 'Gateway Tester',
            'contact_number' => '09171234567',
            'email' => $user->email,
            'business_name' => 'Gateway Biz',
            'business_address' => '1 Street',
            'business_nature' => 'Retail',
            'ownership_type' => 'Sole Proprietorship',
            'barangay' => 'Poblacion',
            'number_of_employees' => 0,
        ]);
        $application->user()->associate($user);
        $application->status = Application::STATUS_PAYMENT_PENDING;
        $application->tracking_number = 'BANSUD-2025-000900';
        $application->save();

        return Payment::create([
            'application_id' => $application->getKey(),
            'tracking_number' => $application->tracking_number,
            'amount' => 1000,
            'breakdown_json' => ['lines' => [], 'total' => 1000],
            'method' => 'gcash',
            'status' => Payment::STATUS_PENDING,
            'verification_status' => Payment::VERIFY_UNVERIFIED,
        ]);
    }

    public function test_initiate_returns_processing_with_sandbox_reference_and_never_paid(): void
    {
        $payment = $this->makePayment();

        $result = app(SandboxGateway::class)->initiate($payment);

        $this->assertSame('processing', $result['status']);
        $this->assertStringStartsWith('SANDBOX-', $result['reference']);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PROCESSING, $payment->status);
        $this->assertNotSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(Payment::VERIFY_UNVERIFIED, $payment->verification_status);

        $this->assertDatabaseHas('payment_transactions', [
            'payment_id' => $payment->getKey(),
            'event' => 'initiated',
            'gateway' => 'sandbox',
        ]);
    }

    public function test_verify_marks_payment_paid_and_verified(): void
    {
        $payment = $this->makePayment();
        app(SandboxGateway::class)->initiate($payment);

        $result = app(SandboxGateway::class)->verify($payment->fresh(), ['event' => 'payment.paid']);

        $this->assertSame('paid', $result['status']);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(Payment::VERIFY_VERIFIED, $payment->verification_status);
        $this->assertNotNull($payment->payment_date);
        $this->assertNotNull($payment->verified_at);

        $this->assertDatabaseHas('payment_transactions', [
            'payment_id' => $payment->getKey(),
            'event' => 'verified',
            'gateway' => 'sandbox',
        ]);
    }
}
