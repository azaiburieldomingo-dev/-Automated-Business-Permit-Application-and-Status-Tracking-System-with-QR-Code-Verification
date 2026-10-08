<?php

namespace Tests\Feature\Bplo;

use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class ReviewFlowTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_begin_review_moves_submitted_to_under_review(): void
    {
        $bplo = $this->makeBplo();
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_SUBMITTED);

        $this->actingAs($bplo)
            ->post("/bplo/applications/{$application->getKey()}/review")
            ->assertRedirect();

        $this->assertSame(Application::STATUS_UNDER_REVIEW, $application->fresh()->status);
    }

    public function test_approve_moves_under_review_to_approved_for_payment(): void
    {
        $bplo = $this->makeBplo();
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_UNDER_REVIEW);

        $this->actingAs($bplo)
            ->post("/bplo/applications/{$application->getKey()}/approve")
            ->assertRedirect();

        $this->assertSame(Application::STATUS_APPROVED_FOR_PAYMENT, $application->fresh()->status);
    }

    public function test_approved_application_tells_bplo_the_applicant_pays_next(): void
    {
        $bplo = $this->makeBplo();
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_APPROVED_FOR_PAYMENT);

        $this->actingAs($bplo)
            ->get("/bplo/applications/{$application->getKey()}")
            ->assertOk()
            ->assertSee('Next step: the applicant pays.')
            ->assertSee('Fees to be Paid')
            ->assertSee('Waiting for the applicant to pay online.')
            ->assertDontSee('Confirm Payment</button>', false);
    }

    public function test_pending_payment_shows_reference_but_no_confirm_button_for_bplo(): void
    {
        $bplo = $this->makeBplo();
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_PAYMENT_PENDING);
        $payment = $this->makePendingPayment($application);

        $this->actingAs($bplo)
            ->get("/bplo/applications/{$application->getKey()}")
            ->assertOk()
            ->assertSee('The Treasurer is verifying this payment.')
            ->assertSee($payment->reference_number)
            ->assertDontSee('Confirm Payment</button>', false)
            ->assertDontSee('Reject Payment</button>', false);
    }

    public function test_return_for_correction_requires_a_remark(): void
    {
        $bplo = $this->makeBplo();
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_UNDER_REVIEW);

        // Empty remark is rejected by the Form Request.
        $this->actingAs($bplo)
            ->post("/bplo/applications/{$application->getKey()}/return", ['remark' => ''])
            ->assertSessionHasErrors('remark');
        $this->assertSame(Application::STATUS_UNDER_REVIEW, $application->fresh()->status);

        // A valid remark transitions and records the remark.
        $this->actingAs($bplo)
            ->post("/bplo/applications/{$application->getKey()}/return", ['remark' => 'Please re-upload a clearer ID.'])
            ->assertRedirect();

        $this->assertSame(Application::STATUS_FOR_CORRECTION, $application->fresh()->status);
        $this->assertDatabaseHas('application_remarks', [
            'application_id' => $application->getKey(),
            'type' => 'CORRECTION',
            'body' => 'Please re-upload a clearer ID.',
        ]);
    }

    public function test_reject_requires_a_remark(): void
    {
        $bplo = $this->makeBplo();
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_UNDER_REVIEW);

        $this->actingAs($bplo)
            ->post("/bplo/applications/{$application->getKey()}/reject", ['remark' => ''])
            ->assertSessionHasErrors('remark');
        $this->assertSame(Application::STATUS_UNDER_REVIEW, $application->fresh()->status);

        $this->actingAs($bplo)
            ->post("/bplo/applications/{$application->getKey()}/reject", ['remark' => 'Fraudulent documents.'])
            ->assertRedirect();

        $this->assertSame(Application::STATUS_REJECTED, $application->fresh()->status);
        $this->assertDatabaseHas('application_remarks', [
            'application_id' => $application->getKey(),
            'type' => 'REJECTION',
            'body' => 'Fraudulent documents.',
        ]);
    }

    public function test_applicant_cannot_access_bplo_routes(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_SUBMITTED);

        $this->actingAs($applicant)->get('/bplo/dashboard')->assertForbidden();
        $this->actingAs($applicant)->get('/bplo/applications')->assertForbidden();
        $this->actingAs($applicant)
            ->post("/bplo/applications/{$application->getKey()}/review")
            ->assertForbidden();
    }
}
