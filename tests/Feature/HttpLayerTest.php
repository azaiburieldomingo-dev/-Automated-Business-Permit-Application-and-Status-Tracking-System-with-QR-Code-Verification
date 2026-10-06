<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HttpLayerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, bool $active = true): User
    {
        $user = User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => $active,
        ]);
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function makePermit(string $status, ?string $validUntil = null): Permit
    {
        $applicant = $this->makeUser(User::ROLE_APPLICANT);

        $application = new Application([
            'applicant_name' => 'Jane Dela Cruz',
            'contact_number' => '09171234567',
            'email' => 'jane@example.com',
            'business_name' => 'Jane Store',
            'business_address' => '1 Rizal St, Bansud',
            'business_nature' => 'Retail',
            'ownership_type' => 'Sole Proprietorship',
            'barangay' => 'Poblacion',
            'number_of_employees' => 2,
        ]);
        $application->user()->associate($applicant);
        $application->status = Application::STATUS_PERMIT_RELEASED;
        $application->tracking_number = 'BANSUD-'.date('Y').'-000123';
        $application->save();

        return Permit::create([
            'application_id' => $application->getKey(),
            'permit_number' => 'BP-'.date('Y').'-000123',
            'verification_token' => str_repeat('a', 64),
            'business_name' => $application->business_name,
            'owner_name' => $application->applicant_name,
            'business_address' => $application->business_address,
            'business_nature' => $application->business_nature,
            'issued_at' => now()->subMonth(),
            'valid_until' => $validUntil ?? now()->addMonths(11),
            'status' => $status,
        ]);
    }

    public function test_landing_page_renders_with_title_and_actions(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Automated Business Permit Application and Status Tracking System', false);
        $response->assertSee('Apply Online');
        $response->assertSee('Track Application');
        $response->assertSee('Verify Permit');
        $response->assertSee('Login');
        $response->assertDontSee('Bongabong');
    }

    public function test_role_middleware_denies_cross_role_access(): void
    {
        $applicant = $this->makeUser(User::ROLE_APPLICANT);

        $this->actingAs($applicant)->get('/bplo/dashboard')->assertForbidden();
        $this->actingAs($applicant)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_bplo_dashboard_shows_seven_counters(): void
    {
        $bplo = $this->makeUser(User::ROLE_BPLO);

        $response = $this->actingAs($bplo)->get('/bplo/dashboard');

        $response->assertOk();
        foreach (['Total', 'Pending Review', 'For Correction', 'Approved', 'Awaiting Payment', 'Paid', 'Released'] as $label) {
            $response->assertSee($label);
        }
    }

    public function test_admin_dashboard_shows_cards(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        foreach (['Total Users', 'Total Applications', 'Total Permits', 'Total Payments', 'Active Permits', 'Expired Permits', 'Recent Activities'] as $label) {
            $response->assertSee($label);
        }
    }

    public function test_verify_valid_permit_shows_minimal_data_and_logs(): void
    {
        $permit = $this->makePermit(Permit::STATUS_ACTIVE);

        $response = $this->get('/verify/'.$permit->verification_token);

        $response->assertOk();
        $response->assertSee('VALID');
        $response->assertSee($permit->permit_number);
        $response->assertSee($permit->business_name);
        $response->assertSee($permit->business_address);
        // Data-minimized per RA 10173 — no PII.
        $response->assertDontSee('jane@example.com');
        $response->assertDontSee('09171234567');

        $this->assertDatabaseHas('qr_verifications', [
            'token' => $permit->verification_token,
            'result' => 'VALID',
        ]);
    }

    public function test_verify_expired_permit(): void
    {
        $permit = $this->makePermit(Permit::STATUS_ACTIVE, now()->subDay()->toDateString());

        $this->get('/verify/'.$permit->verification_token)
            ->assertOk()
            ->assertSee('EXPIRED');
    }

    public function test_verify_revoked_permit(): void
    {
        $permit = $this->makePermit(Permit::STATUS_REVOKED);

        $this->get('/verify/'.$permit->verification_token)
            ->assertOk()
            ->assertSee('REVOKED');
    }

    public function test_verify_unknown_token_is_not_found(): void
    {
        $this->get('/verify/'.str_repeat('z', 64))
            ->assertOk()
            ->assertSee('NOT FOUND');

        $this->assertDatabaseHas('qr_verifications', [
            'result' => 'NOT_FOUND',
        ]);
    }

    public function test_track_requires_tracking_number_and_verifier(): void
    {
        $this->post('/track', [])
            ->assertSessionHasErrors(['tracking_number', 'verifier']);
    }

    public function test_track_matches_on_last_name_and_hides_pii(): void
    {
        $permit = $this->makePermit(Permit::STATUS_ACTIVE);
        $application = $permit->application;

        $response = $this->post('/track', [
            'tracking_number' => $application->tracking_number,
            'verifier' => 'Dela Cruz',
        ]);

        // "Cruz" is the last name token.
        $response = $this->post('/track', [
            'tracking_number' => $application->tracking_number,
            'verifier' => 'Cruz',
        ]);

        $response->assertOk();
        $response->assertSee($application->tracking_number);
        $response->assertDontSee('jane@example.com');
    }
}
