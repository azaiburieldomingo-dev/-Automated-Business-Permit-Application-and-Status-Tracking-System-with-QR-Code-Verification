<?php

namespace Tests\Feature\Payment;

use App\Models\Application;
use App\Models\Payment;
use App\Services\PaymentService;
use Database\Seeders\FeeRateSeeder;
use Database\Seeders\FeeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FeeTypeSeeder::class, FeeRateSeeder::class]);
    }

    public function test_start_payment_only_from_approved_for_payment(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_SUBMITTED);

        $this->expectException(RuntimeException::class);
        app(PaymentService::class)->startPayment($application, 'gcash', $applicant);
    }

    public function test_pay_button_alone_does_not_release_a_permit(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_APPROVED_FOR_PAYMENT);

        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/pay", ['method' => 'gcash'])
            ->assertRedirect();

        $application->refresh();
        // The pay action moves to PAYMENT_PENDING only — never verified, never released.
        $this->assertSame(Application::STATUS_PAYMENT_PENDING, $application->status);
        $this->assertNull($application->permit);

        $payment = Payment::where('application_id', $application->getKey())->firstOrFail();
        $this->assertNotSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(Payment::VERIFY_UNVERIFIED, $payment->verification_status);
    }

    public function test_bplo_confirm_payment_marks_payment_verified(): void
    {
        $applicant = $this->makeApplicant();
        $bplo = $this->makeBplo();
        $application = $this->makeApplication($applicant, Application::STATUS_APPROVED_FOR_PAYMENT);

        $this->actingAs($applicant)->post("/applications/{$application->getKey()}/pay", ['method' => 'gcash']);
        $payment = Payment::where('application_id', $application->getKey())->firstOrFail();

        $this->actingAs($bplo)
            ->post("/bplo/payments/{$payment->getKey()}/confirm")
            ->assertRedirect();

        $this->assertSame(Application::STATUS_PAYMENT_VERIFIED, $application->fresh()->status);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(Payment::VERIFY_VERIFIED, $payment->verification_status);
        $this->assertSame($bplo->getKey(), $payment->verified_by);
    }

    public function test_payment_history_shows_attempts(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_APPROVED_FOR_PAYMENT);

        $this->actingAs($applicant)->post("/applications/{$application->getKey()}/pay", ['method' => 'gcash']);
        $payment = Payment::where('application_id', $application->getKey())->firstOrFail();

        $this->actingAs($applicant)
            ->get("/applications/{$application->getKey()}/payments")
            ->assertOk()
            ->assertSee($payment->reference_number);
    }

    public function test_payment_page_rejected_when_not_approved(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_SUBMITTED);

        $this->actingAs($applicant)
            ->get("/applications/{$application->getKey()}/pay")
            ->assertForbidden();
    }
}
