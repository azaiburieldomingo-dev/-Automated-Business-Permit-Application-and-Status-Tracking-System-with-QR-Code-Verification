<?php

namespace Tests\Feature\PublicPages;

use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    private function trackedApplication(): Application
    {
        return $this->makeApplication($this->makeApplicant(), Application::STATUS_UNDER_REVIEW, [
            'applicant_name' => 'Jane Dela Cruz',
            'email' => 'jane@example.com',
            'contact_number' => '09171234567',
            'dti_sec_number' => 'DTI-SECRET-999',
            'tracking_number' => 'BANSUD-'.date('Y').'-000321',
        ]);
    }

    public function test_track_form_renders(): void
    {
        $this->get('/track')->assertOk();
    }

    public function test_lookup_with_correct_tracking_and_verifier_shows_status(): void
    {
        $application = $this->trackedApplication();

        $response = $this->post('/track', [
            'tracking_number' => $application->tracking_number,
            'verifier' => 'jane@example.com',
        ]);

        $response->assertOk();
        $response->assertSee($application->tracking_number);
        // The status is shown via a humanized badge.
        $response->assertSee('Under Review');

        // No PII leaks.
        $response->assertDontSee('09171234567');
        $response->assertDontSee('DTI-SECRET-999');
    }

    public function test_lookup_matches_on_last_name(): void
    {
        $application = $this->trackedApplication();

        $this->post('/track', [
            'tracking_number' => $application->tracking_number,
            'verifier' => 'Cruz',
        ])->assertOk()->assertSee($application->tracking_number);
    }

    public function test_wrong_verifier_does_not_reveal_the_application(): void
    {
        $application = $this->trackedApplication();

        $response = $this->post('/track', [
            'tracking_number' => $application->tracking_number,
            'verifier' => 'wrong-person@example.com',
        ]);

        $response->assertOk();
        $response->assertDontSee('09171234567');
    }

    public function test_lookup_validates_input(): void
    {
        $this->post('/track', [])
            ->assertSessionHasErrors(['tracking_number', 'verifier']);

        // Malformed tracking number fails the regex rule.
        $this->post('/track', [
            'tracking_number' => 'NOT-A-TRACKING-NUMBER',
            'verifier' => 'jane@example.com',
        ])->assertSessionHasErrors('tracking_number');
    }

    public function test_track_lookup_is_throttled(): void
    {
        $application = $this->trackedApplication();

        // throttle:6,1 — the 7th POST within the window is blocked.
        for ($i = 0; $i < 6; $i++) {
            $this->post('/track', [
                'tracking_number' => $application->tracking_number,
                'verifier' => 'jane@example.com',
            ])->assertOk();
        }

        $this->post('/track', [
            'tracking_number' => $application->tracking_number,
            'verifier' => 'jane@example.com',
        ])->assertStatus(429);
    }
}
