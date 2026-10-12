<?php

namespace Tests\Feature\Treasurer;

use App\Models\Application;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\FeeRateSeeder;
use Database\Seeders\FeeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class TreasurerPaymentTest extends TestCase
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
     * @return array{0: User, 1: Application, 2: Payment}
     */
    private function submitManualPayment(): array
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_APPROVED_FOR_PAYMENT);

        $this->actingAs($applicant)->post("/applications/{$application->getKey()}/pay", [
            'method' => 'gcash',
            'reference_number' => '1012 345 678901',
            'payer_name' => 'Jane Dela Cruz',
            'payer_number' => '09171234567',
            'proof' => UploadedFile::fake()->image('receipt.png'),
            'confirm_amount' => '1',
        ]);

        return [$applicant, $application, Payment::firstOrFail()];
    }

    public function test_treasurer_confirms_a_manual_payment(): void
    {
        [, $application, $payment] = $this->submitManualPayment();
        $treasurer = $this->makeTreasurer();

        $this->actingAs($treasurer)
            ->post("/treasurer/payments/{$payment->getKey()}/confirm")
            ->assertRedirect(route('treasurer.payments.show', $payment));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(Payment::VERIFY_VERIFIED, $payment->verification_status);
        $this->assertSame($treasurer->getKey(), $payment->verified_by);
        $this->assertSame(Application::STATUS_PAYMENT_VERIFIED, $application->fresh()->status);
    }

    public function test_treasurer_rejects_a_manual_payment(): void
    {
        [$applicant, $application, $payment] = $this->submitManualPayment();
        $treasurer = $this->makeTreasurer();

        $this->actingAs($treasurer)
            ->post("/treasurer/payments/{$payment->getKey()}/reject", ['reason' => 'Amount does not match'])
            ->assertRedirect(route('treasurer.payments.show', $payment));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertSame('Amount does not match', $payment->rejection_reason);
        $this->assertSame(Application::STATUS_APPROVED_FOR_PAYMENT, $application->fresh()->status);

        $notice = Notification::where('user_id', $applicant->getKey())
            ->where('type', 'payment.rejected')
            ->first();
        $this->assertNotNull($notice);
    }

    public function test_treasurer_dashboard_lists_payment_awaiting_verification(): void
    {
        [, , $payment] = $this->submitManualPayment();
        $treasurer = $this->makeTreasurer();

        $this->actingAs($treasurer)->get('/treasurer/dashboard')
            ->assertOk()
            ->assertSee('Awaiting Verification')
            ->assertSee($payment->reference_number);
    }

    public function test_treasurer_show_links_to_the_private_receipt(): void
    {
        [, , $payment] = $this->submitManualPayment();
        $treasurer = $this->makeTreasurer();

        $this->actingAs($treasurer)->get("/treasurer/payments/{$payment->getKey()}")
            ->assertOk()
            ->assertSee('View receipt')
            ->assertSee('Confirm Payment');

        $this->actingAs($treasurer)->get("/treasurer/payments/{$payment->getKey()}/receipt")->assertOk();
    }
}
