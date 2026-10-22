<?php

namespace Tests\Feature\Applicant;

use App\Models\Application;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    private function correctionRemark(Application $application, User $author, string $body): void
    {
        $remark = $application->remarks()->make(['type' => 'CORRECTION', 'body' => $body]);
        $remark->author()->associate($author);
        $remark->save();
    }

    private function failedPayment(Application $application, string $reason, int $amount = 1500): Payment
    {
        $payment = Payment::create([
            'application_id' => $application->getKey(),
            'tracking_number' => $application->tracking_number,
            'amount' => $amount,
            'breakdown_json' => ['lines' => [], 'total' => $amount],
            'method' => 'gcash',
            'reference_number' => 'FAILED-'.uniqid(),
            'status' => Payment::STATUS_FAILED,
            'verification_status' => Payment::VERIFY_UNVERIFIED,
        ]);

        // rejection_reason is a real column but not mass-assignable; set it
        // directly, the same way the Treasurer reject flow does.
        $payment->rejection_reason = $reason;
        $payment->save();

        return $payment;
    }

    private function activePayment(Application $application, int $amount, string $status = Payment::STATUS_PROCESSING): Payment
    {
        return Payment::create([
            'application_id' => $application->getKey(),
            'tracking_number' => $application->tracking_number,
            'amount' => $amount,
            'breakdown_json' => ['lines' => [], 'total' => $amount],
            'method' => 'gcash',
            'reference_number' => 'ACTIVE-'.uniqid(),
            'status' => $status,
            'verification_status' => Payment::VERIFY_UNVERIFIED,
        ]);
    }

    public function test_the_four_stat_counts_reflect_application_statuses(): void
    {
        $applicant = $this->makeApplicant();

        $this->makeApplication($applicant, Application::STATUS_SUBMITTED);
        $this->makeApplication($applicant, Application::STATUS_UNDER_REVIEW);
        $this->makeApplication($applicant, Application::STATUS_FOR_CORRECTION);
        $this->makeApplication($applicant, Application::STATUS_APPROVED_FOR_PAYMENT);
        $released = $this->makeApplication($applicant, Application::STATUS_PERMIT_RELEASED);
        $this->makePermit($released);

        $response = $this->actingAs($applicant)->get('/dashboard')->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame(2, $stats['pending']);   // SUBMITTED + UNDER_REVIEW
        $this->assertSame(1, $stats['approved']);   // PERMIT_RELEASED
        $this->assertSame(2, $stats['action']);     // FOR_CORRECTION + APPROVED_FOR_PAYMENT
    }

    public function test_action_needed_callout_shows_the_correction_remark_body(): void
    {
        $applicant = $this->makeApplicant();
        $bplo = $this->makeBplo();
        $application = $this->makeApplication($applicant, Application::STATUS_FOR_CORRECTION);
        $this->correctionRemark($application, $bplo, 'Please fix your business address.');

        $this->actingAs($applicant)->get('/dashboard')
            ->assertOk()
            ->assertSee('Please fix your business address.');
    }

    public function test_fee_amount_is_not_shown_for_under_review(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_UNDER_REVIEW);
        $this->activePayment($application, 1234); // should be ignored — below APPROVED_FOR_PAYMENT

        $this->actingAs($applicant)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Assessment ready')
            ->assertDontSee('₱1,234.00');
    }

    public function test_assessment_ready_is_reachable_at_approved_for_payment(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_APPROVED_FOR_PAYMENT);

        $this->actingAs($applicant)->get('/dashboard')
            ->assertOk()
            ->assertSee('Assessment ready')
            ->assertSee(route('applications.pay', $application), false);
    }

    public function test_rejected_application_shows_no_fee_and_no_permit_block(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_REJECTED);
        // Even if a stray payment row exists, nothing fee-related may render.
        $this->activePayment($application, 2222);

        $this->actingAs($applicant)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Assessment ready')
            ->assertDontSee('₱2,222.00')
            ->assertDontSee('Download Permit');
    }

    public function test_for_correction_application_shows_no_fee_block(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_FOR_CORRECTION);
        $this->activePayment($application, 3333);

        $this->actingAs($applicant)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Assessment ready')
            ->assertDontSee('₱3,333.00');
    }

    public function test_repayment_routing_links_to_pay_and_surfaces_the_rejection_reason(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_APPROVED_FOR_PAYMENT);
        $this->failedPayment($application, 'Ref No. not found in GCash history');

        $response = $this->actingAs($applicant)->get('/dashboard')->assertOk();

        // Links to the guarded pay page (re-compute), NOT the read-only history.
        $response->assertSee(route('applications.pay', $application), false);
        $response->assertDontSee(route('applications.payments', $application), false);
        $response->assertSee('Ref No. not found in GCash history');
    }

    public function test_current_payment_selection_uses_the_latest_row(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_PAYMENT_PENDING);

        // Older FAILED row, then a newer active row (higher id).
        $this->failedPayment($application, 'Rejected earlier', 999);
        $this->activePayment($application, 2500);

        $response = $this->actingAs($applicant)->get('/dashboard')->assertOk();

        // The newer active amount is shown; the failed amount is not surfaced here.
        $response->assertSee('₱2,500.00');
        $response->assertDontSee('₱999.00');
        $response->assertDontSee('Rejected earlier');
    }

    public function test_payment_pending_shows_treasurer_callout_and_is_not_in_stat_cards(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_PAYMENT_PENDING);
        $this->activePayment($application, 1800);

        $response = $this->actingAs($applicant)->get('/dashboard')->assertOk();

        $response->assertSee('The Treasurer is verifying your GCash payment.');

        $stats = $response->viewData('stats');
        $this->assertSame(1, $stats['paymentPending']);
        $this->assertSame(0, $stats['pending']);
        $this->assertSame(0, $stats['approved']);
        $this->assertSame(0, $stats['action']);
    }

    public function test_payment_verified_shows_permit_being_prepared_callout(): void
    {
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_PAYMENT_VERIFIED);
        $this->activePayment($application, 1800, Payment::STATUS_PAID);

        $this->actingAs($applicant)->get('/dashboard')
            ->assertOk()
            ->assertSee('The BPLO is preparing your permit for release.');
    }

    public function test_stat_partition_holds_across_a_nine_status_fixture(): void
    {
        $applicant = $this->makeApplicant();

        $this->makeApplication($applicant, Application::STATUS_DRAFT);
        $this->makeApplication($applicant, Application::STATUS_SUBMITTED);
        $this->makeApplication($applicant, Application::STATUS_UNDER_REVIEW);
        $this->makeApplication($applicant, Application::STATUS_FOR_CORRECTION);
        $this->makeApplication($applicant, Application::STATUS_APPROVED_FOR_PAYMENT);
        $this->makeApplication($applicant, Application::STATUS_PAYMENT_PENDING);
        $this->makeApplication($applicant, Application::STATUS_PAYMENT_VERIFIED);
        $released = $this->makeApplication($applicant, Application::STATUS_PERMIT_RELEASED);
        $this->makePermit($released);
        $this->makeApplication($applicant, Application::STATUS_REJECTED);

        $stats = $this->actingAs($applicant)->get('/dashboard')->assertOk()->viewData('stats');

        $this->assertSame(9, $stats['total']);
        $this->assertSame(
            $stats['pending'] + $stats['action'] + $stats['approved']
                + $stats['paymentPending'] + $stats['paymentVerified']
                + $stats['draft'] + $stats['rejected'],
            $stats['total'],
        );
    }

    public function test_renewal_reminder_shows_within_window_and_not_far_in_the_future(): void
    {
        // Permit expiring soon (within the 30-day window) → banner shown.
        $near = $this->makeApplicant();
        $nearApp = $this->makeApplication($near, Application::STATUS_PERMIT_RELEASED);
        $this->makePermit($nearApp, Permit::STATUS_ACTIVE, now()->addDays(10)->toDateString());

        $this->actingAs($near)->get('/dashboard')
            ->assertOk()
            ->assertSee('Permit renewal reminder');

        // Permit valid far in the future → NO banner.
        $far = $this->makeApplicant();
        $farApp = $this->makeApplication($far, Application::STATUS_PERMIT_RELEASED);
        $this->makePermit($farApp, Permit::STATUS_ACTIVE, now()->addMonths(10)->toDateString());

        $this->actingAs($far)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Permit renewal reminder');
    }
}
