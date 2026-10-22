<?php

namespace Tests\Feature\Payment;

use App\Models\Application;
use App\Models\Notification;
use App\Models\Payment;
use App\Services\ManualGateway;
use Database\Seeders\FeeRateSeeder;
use Database\Seeders\FeeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FeeTypeSeeder::class, FeeRateSeeder::class]);
        Storage::fake('local');

        config([
            'permit.payment.mode' => 'manual',
            'permit.payment.accounts.gcash.number' => '09670829993',
            'permit.payment.accounts.gcash.name' => 'Aiza Domingo',
            'permit.payment.accounts.maya.number' => null,
            'permit.payment.accounts.qrph.number' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'method' => 'gcash',
            'reference_number' => '1012 345 678901',
            'payer_name' => 'Jane Dela Cruz',
            'payer_number' => '09171234567',
            'proof' => UploadedFile::fake()->image('receipt.png'),
            'confirm_amount' => '1',
        ], $overrides);
    }

    private function approvedApplication(): array
    {
        $applicant = $this->makeApplicant();

        return [$applicant, $this->makeApplication($applicant, Application::STATUS_APPROVED_FOR_PAYMENT)];
    }

    public function test_pay_page_shows_the_gcash_number_and_account_name(): void
    {
        [$applicant, $application] = $this->approvedApplication();

        $this->actingAs($applicant)->get("/applications/{$application->getKey()}/pay")
            ->assertOk()
            ->assertSee('09670829993')
            ->assertSee('Aiza Domingo')
            ->assertSee('Reference Number')
            ->assertSee('Not yet available'); // Maya / QR Ph have no account yet
    }

    public function test_submitting_reference_and_receipt_sets_payment_pending_only(): void
    {
        [$applicant, $application] = $this->approvedApplication();

        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/pay", $this->payload())
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHasNoErrors();

        $payment = Payment::where('application_id', $application->getKey())->firstOrFail();

        $this->assertSame('1012345678901', $payment->reference_number); // spaces stripped
        $this->assertSame(Payment::STATUS_PROCESSING, $payment->status);
        $this->assertSame(Payment::VERIFY_UNVERIFIED, $payment->verification_status);
        $this->assertSame('09171234567', $payment->payer_number);
        Storage::disk('local')->assertExists($payment->proof_path);

        $application->refresh();
        $this->assertSame(Application::STATUS_PAYMENT_PENDING, $application->status);
        $this->assertNull($application->permit);
    }

    public function test_manual_start_flash_names_the_treasurer_not_the_bplo(): void
    {
        [$applicant, $application] = $this->approvedApplication();

        $response = $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/pay", $this->payload())
            ->assertRedirect(route('applications.show', $application))
            ->assertSessionHas('success');

        $flash = session('success');
        $this->assertStringContainsString('Treasurer', $flash);
        $this->assertStringNotContainsString('BPLO', $flash);
    }

    public function test_reference_and_receipt_are_required(): void
    {
        [$applicant, $application] = $this->approvedApplication();

        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/pay", ['method' => 'gcash'])
            ->assertSessionHasErrors(['reference_number', 'proof', 'confirm_amount']);

        $this->assertSame(Application::STATUS_APPROVED_FOR_PAYMENT, $application->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_method_without_receiving_account_cannot_be_used(): void
    {
        [$applicant, $application] = $this->approvedApplication();

        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/pay", $this->payload(['method' => 'maya']))
            ->assertSessionHasErrors('method');
    }

    public function test_the_same_reference_number_cannot_be_reused(): void
    {
        [$a1, $app1] = $this->approvedApplication();
        [$a2, $app2] = $this->approvedApplication();

        $this->actingAs($a1)->post("/applications/{$app1->getKey()}/pay", $this->payload());

        $this->actingAs($a2)
            ->post("/applications/{$app2->getKey()}/pay", $this->payload(['reference_number' => '1012345678901']))
            ->assertSessionHasErrors('reference_number');

        $this->assertSame(Application::STATUS_APPROVED_FOR_PAYMENT, $app2->fresh()->status);
    }

    public function test_treasurer_confirms_after_checking_the_receipt(): void
    {
        [$applicant, $application] = $this->approvedApplication();
        $treasurer = $this->makeTreasurer();
        $this->actingAs($applicant)->post("/applications/{$application->getKey()}/pay", $this->payload());
        $payment = Payment::firstOrFail();

        $this->actingAs($treasurer)->get("/treasurer/payments/{$payment->getKey()}")
            ->assertOk()
            ->assertSee('1012345678901')
            ->assertSee('View receipt')
            ->assertSee('Reject Payment');

        $this->actingAs($treasurer)->get("/treasurer/payments/{$payment->getKey()}/receipt")->assertOk();

        $this->actingAs($treasurer)->post("/treasurer/payments/{$payment->getKey()}/confirm")->assertRedirect();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(Payment::VERIFY_VERIFIED, $payment->verification_status);
        $this->assertSame(Application::STATUS_PAYMENT_VERIFIED, $application->fresh()->status);
    }

    public function test_treasurer_rejects_and_applicant_can_pay_again(): void
    {
        [$applicant, $application] = $this->approvedApplication();
        $treasurer = $this->makeTreasurer();
        $this->actingAs($applicant)->post("/applications/{$application->getKey()}/pay", $this->payload());
        $payment = Payment::firstOrFail();

        $this->actingAs($treasurer)
            ->post("/treasurer/payments/{$payment->getKey()}/reject", ['reason' => 'Ref No. not found in GCash history'])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertSame('Ref No. not found in GCash history', $payment->rejection_reason);
        $this->assertSame(Application::STATUS_APPROVED_FOR_PAYMENT, $application->fresh()->status);

        $notice = Notification::where('user_id', $applicant->getKey())->where('type', 'payment.rejected')->first();
        $this->assertNotNull($notice);
        $this->assertStringContainsString('Ref No. not found in GCash history', $notice->body);

        $this->actingAs($applicant)->get("/applications/{$application->getKey()}/pay")
            ->assertOk()
            ->assertSee('Your previous payment was not accepted.');

        // Corrected resubmission (the rejected reference no longer blocks).
        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/pay", $this->payload(['reference_number' => '1012345678901']))
            ->assertSessionHasNoErrors();
        $this->assertSame(Application::STATUS_PAYMENT_PENDING, $application->fresh()->status);
    }

    public function test_reject_requires_a_reason(): void
    {
        [$applicant, $application] = $this->approvedApplication();
        $this->actingAs($applicant)->post("/applications/{$application->getKey()}/pay", $this->payload());
        $payment = Payment::firstOrFail();

        $this->actingAs($this->makeTreasurer())
            ->post("/treasurer/payments/{$payment->getKey()}/reject", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(Payment::STATUS_PROCESSING, $payment->fresh()->status);
    }

    public function test_receipts_are_private(): void
    {
        [$applicant, $application] = $this->approvedApplication();
        $this->actingAs($applicant)->post("/applications/{$application->getKey()}/pay", $this->payload());
        $payment = Payment::firstOrFail();

        $this->actingAs($applicant)->get("/payments/{$payment->getKey()}/receipt")->assertOk();
        $this->actingAs($this->makeApplicant())->get("/payments/{$payment->getKey()}/receipt")->assertForbidden();
        $this->actingAs($applicant)->get("/bplo/payments/{$payment->getKey()}/receipt")->assertForbidden();
    }

    public function test_manual_gateway_never_self_verifies(): void
    {
        [, $application] = $this->approvedApplication();
        $payment = $this->makePendingPayment($application);

        $result = app(ManualGateway::class)->verify($payment, ['source' => 'webhook']);

        $this->assertSame('unverified', $result['status']);
        $this->assertSame(Payment::VERIFY_UNVERIFIED, $payment->fresh()->verification_status);
    }
}
