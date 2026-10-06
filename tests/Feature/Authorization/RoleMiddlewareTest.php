<?php

namespace Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_applicant_cannot_access_bplo_routes(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)->get('/bplo/dashboard')->assertForbidden();
        $this->actingAs($applicant)->get('/bplo/applications')->assertForbidden();
    }

    public function test_applicant_cannot_access_admin_routes(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_bplo_cannot_access_admin_routes(): void
    {
        $bplo = $this->makeBplo();

        $this->actingAs($bplo)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($bplo)->get('/admin/users')->assertForbidden();
    }

    public function test_bplo_can_access_bplo_routes(): void
    {
        $bplo = $this->makeBplo();

        $this->actingAs($bplo)->get('/bplo/dashboard')->assertOk();
    }

    public function test_admin_can_access_both_bplo_and_admin_routes(): void
    {
        $admin = $this->makeAdmin();

        // Admin is included in role:bplo,admin.
        $this->actingAs($admin)->get('/bplo/dashboard')->assertOk();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_unauthenticated_requests_redirect_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/bplo/dashboard')->assertRedirect('/login');
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }
}
