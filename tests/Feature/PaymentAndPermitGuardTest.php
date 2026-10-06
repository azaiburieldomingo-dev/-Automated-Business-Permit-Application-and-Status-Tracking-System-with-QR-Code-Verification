<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PermitService;
use App\Services\SandboxGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class PaymentAndPermitGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplication(string $status): Application
    {
        $user = User::create([
            'name' => 'Guard Applicant',
            'email' => 'guard_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);
        $user->role = User::ROLE_APPLICANT;
        $user->save();

        $application = new Application([
            'applicant_name' => 'Guard Applicant',
            'contact_number' => '09171234567',
            'email' => $user->email,
            'business_name' => 'Guard Business',
            'business_address' => '1 Street',
            'business_nature' => 'Services',
            'ownership_type' => 'sole proprietorship',
            'barangay' => 'Poblacion',
            'number_of_employees' => 0,
        ]);
        $application->user()->associate($user);
        $application->status = $status;
        $application->tracking_number = 'BANSUD-2025-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $application->save();

        return $application;
    }

    public function test_sandbox_initiate_never_returns_paid(): void
    {
        Event::fake();
        $application = $this->makeApplication(Application::STATUS_APPROVED_FOR_PAYMENT);

        $payment = Payment::create([
            'application_id' => $application->getKey(),
            'tracking_number' => $application->tracking_number,
            'amount' => 100,
            'breakdown_json' => ['lines' => [], 'total' => 100],
            'method' => 'gcash',
            'status' => Payment::STATUS_PENDING,
            'verification_status' => Payment::VERIFY_UNVERIFIED,
        ]);

        $result = app(SandboxGateway::class)->initiate($payment);

        $this->assertSame('processing', $result['status']);
        $this->assertNotSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertStringStartsWith('SANDBOX-', $result['reference']);
    }

    public function test_start_payment_requires_approved_status(): void
    {
        $application = $this->makeApplication(Application::STATUS_SUBMITTED);

        $this->expectException(RuntimeException::class);

        app(PaymentService::class)->startPayment($application, 'gcash');
    }

    public function test_permit_release_requires_payment_verified(): void
    {
        $application = $this->makeApplication(Application::STATUS_APPROVED_FOR_PAYMENT);

        $this->expectException(RuntimeException::class);

        app(PermitService::class)->release($application);
    }
}
