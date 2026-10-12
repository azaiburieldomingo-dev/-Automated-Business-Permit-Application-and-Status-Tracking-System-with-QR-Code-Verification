<?php

namespace Tests\Feature\Roles;

use App\Models\Application;
use App\Models\Permit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class SeparationOfDutiesTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_bplo_payment_confirm_reject_routes_are_removed(): void
    {
        $this->assertFalse(Route::has('bplo.payments.confirm'));
        $this->assertFalse(Route::has('bplo.payments.reject'));

        // The treasurer owns verification now.
        $this->assertTrue(Route::has('treasurer.payments.confirm'));
        $this->assertTrue(Route::has('treasurer.payments.reject'));
    }

    public function test_bplo_show_page_has_no_confirm_button_but_keeps_payment_info(): void
    {
        $bplo = $this->makeBplo();
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_PAYMENT_PENDING);
        $payment = $this->makePendingPayment($application);

        $this->actingAs($bplo)->get("/bplo/applications/{$application->getKey()}")
            ->assertOk()
            ->assertDontSee('Confirm Payment</button>', false)
            ->assertDontSee('Reject Payment</button>', false)
            ->assertSee($payment->reference_number)
            ->assertSee($payment->status);
    }

    public function test_bplo_still_releases_the_permit_after_payment_is_verified(): void
    {
        Storage::fake('local');
        $bplo = $this->makeBplo();
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_PAYMENT_VERIFIED);

        $this->actingAs($bplo)
            ->post("/bplo/applications/{$application->getKey()}/release")
            ->assertRedirect();

        $this->assertSame(Application::STATUS_PERMIT_RELEASED, $application->fresh()->status);
        $this->assertNotNull($application->fresh()->permit);
    }

    public function test_inspector_sees_released_permits_and_show_links_to_public_verify(): void
    {
        $inspector = $this->makeInspector();
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_PERMIT_RELEASED);
        $permit = $this->makePermit($application, Permit::STATUS_ACTIVE);

        $this->actingAs($inspector)->get('/inspector/permits')
            ->assertOk()
            ->assertSee($permit->permit_number)
            ->assertSee($permit->business_name);

        $this->actingAs($inspector)->get("/inspector/permits/{$permit->getKey()}")
            ->assertOk()
            ->assertSee('/verify/'.$permit->verification_token, false)
            ->assertSee('Scan History');
    }

    public function test_inspector_cannot_hit_payment_or_bplo_routes(): void
    {
        $inspector = $this->makeInspector();
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_PAYMENT_PENDING);
        $payment = $this->makePendingPayment($application);

        $this->actingAs($inspector)
            ->post("/treasurer/payments/{$payment->getKey()}/confirm")
            ->assertForbidden();
        $this->actingAs($inspector)->get('/treasurer/payments')->assertForbidden();
        $this->actingAs($inspector)->get('/bplo/applications')->assertForbidden();

        // Inspector has no revoke/edit powers: no such route exists for inspector.
        $this->assertFalse(Route::has('inspector.permits.revoke'));
        $this->assertFalse(Route::has('inspector.permits.update'));
    }
}
