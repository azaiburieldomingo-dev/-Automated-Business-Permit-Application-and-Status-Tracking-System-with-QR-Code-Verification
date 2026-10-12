<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_login_form_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_applicant_login_redirects_to_dashboard(): void
    {
        $user = $this->makeApplicant();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_bplo_login_redirects_to_bplo_dashboard(): void
    {
        $user = $this->makeBplo();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect('/bplo/dashboard');
    }

    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $user = $this->makeAdmin();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect('/admin/dashboard');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->makeApplicant(active: false);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = $this->makeApplicant();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // The sixth attempt is blocked by the throttle middleware.
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_logout_ends_the_session(): void
    {
        $user = $this->makeApplicant();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
