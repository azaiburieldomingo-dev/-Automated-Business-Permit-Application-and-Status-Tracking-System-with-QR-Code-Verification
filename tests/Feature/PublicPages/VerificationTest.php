<?php

namespace Tests\Feature\PublicPages;

use App\Models\Application;
use App\Models\Permit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    private function releasedApplication(): Application
    {
        return $this->makeApplication($this->makeApplicant(), Application::STATUS_PERMIT_RELEASED, [
            'applicant_name' => 'Jane Dela Cruz',
            'email' => 'jane@example.com',
            'contact_number' => '09171234567',
            'dti_sec_number' => 'DTI-SECRET-999',
        ]);
    }

    public function test_valid_permit_shows_only_permitted_fields(): void
    {
        $permit = $this->makePermit($this->releasedApplication(), Permit::STATUS_ACTIVE);

        $response = $this->get('/verify/'.$permit->verification_token);

        $response->assertOk();
        $response->assertSee('VALID');
        $response->assertSee($permit->permit_number);
        $response->assertSee($permit->business_name);
        $response->assertSee($permit->business_address);

        // Data minimization (RA 10173) — no PII.
        $response->assertDontSee('jane@example.com');
        $response->assertDontSee('09171234567');
        $response->assertDontSee('DTI-SECRET-999');

        $this->assertDatabaseHas('qr_verifications', [
            'permit_id' => $permit->getKey(),
            'token' => $permit->verification_token,
            'result' => 'VALID',
        ]);
    }

    public function test_expired_permit_is_classified_expired(): void
    {
        $permit = $this->makePermit($this->releasedApplication(), Permit::STATUS_ACTIVE, now()->subDay()->toDateString());

        $this->get('/verify/'.$permit->verification_token)
            ->assertOk()
            ->assertSee('EXPIRED');

        $this->assertDatabaseHas('qr_verifications', [
            'token' => $permit->verification_token,
            'result' => 'EXPIRED',
        ]);
    }

    public function test_revoked_permit_is_classified_revoked(): void
    {
        $permit = $this->makePermit($this->releasedApplication(), Permit::STATUS_REVOKED);

        $this->get('/verify/'.$permit->verification_token)
            ->assertOk()
            ->assertSee('REVOKED');
    }

    public function test_unknown_token_is_not_found_and_still_logs(): void
    {
        $token = str_repeat('z', 64);

        $this->get('/verify/'.$token)
            ->assertOk()
            ->assertSee('NOT FOUND');

        $this->assertDatabaseHas('qr_verifications', [
            'permit_id' => null,
            'token' => $token,
            'result' => 'NOT_FOUND',
        ]);
    }

    public function test_verify_route_is_throttled(): void
    {
        $permit = $this->makePermit($this->releasedApplication(), Permit::STATUS_ACTIVE);

        // throttle:30,1 — the 31st request within the window is blocked.
        for ($i = 0; $i < 30; $i++) {
            $this->get('/verify/'.$permit->verification_token)->assertOk();
        }

        $this->get('/verify/'.$permit->verification_token)->assertStatus(429);
    }
}
