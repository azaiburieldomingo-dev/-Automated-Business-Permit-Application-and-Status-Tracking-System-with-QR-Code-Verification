<?php

namespace Tests\Feature\Roles;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_treasurer_can_reach_treasurer_areas_only(): void
    {
        $treasurer = $this->makeTreasurer();

        $this->actingAs($treasurer)->get('/treasurer/dashboard')->assertOk();
        $this->actingAs($treasurer)->get('/treasurer/payments')->assertOk();

        $this->actingAs($treasurer)->get('/bplo/dashboard')->assertForbidden();
        $this->actingAs($treasurer)->get('/bplo/applications')->assertForbidden();
        $this->actingAs($treasurer)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($treasurer)->get('/inspector/dashboard')->assertForbidden();
    }

    public function test_inspector_can_reach_inspector_areas_only(): void
    {
        $inspector = $this->makeInspector();

        $this->actingAs($inspector)->get('/inspector/dashboard')->assertOk();
        $this->actingAs($inspector)->get('/inspector/permits')->assertOk();

        $this->actingAs($inspector)->get('/treasurer/dashboard')->assertForbidden();
        $this->actingAs($inspector)->get('/treasurer/payments')->assertForbidden();
        $this->actingAs($inspector)->get('/bplo/dashboard')->assertForbidden();
        $this->actingAs($inspector)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_applicant_is_forbidden_from_all_staff_areas(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)->get('/treasurer/dashboard')->assertForbidden();
        $this->actingAs($applicant)->get('/treasurer/payments')->assertForbidden();
        $this->actingAs($applicant)->get('/inspector/dashboard')->assertForbidden();
        $this->actingAs($applicant)->get('/inspector/permits')->assertForbidden();
        $this->actingAs($applicant)->get('/bplo/dashboard')->assertForbidden();
        $this->actingAs($applicant)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_can_reach_treasurer_and_inspector_areas(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/treasurer/dashboard')->assertOk();
        $this->actingAs($admin)->get('/treasurer/payments')->assertOk();
        $this->actingAs($admin)->get('/inspector/dashboard')->assertOk();
        $this->actingAs($admin)->get('/inspector/permits')->assertOk();
    }

    public function test_dashboard_redirects_treasurer_to_its_own_dashboard(): void
    {
        $treasurer = $this->makeTreasurer();

        $this->actingAs($treasurer)->get('/dashboard')->assertRedirect('/treasurer/dashboard');
    }

    public function test_dashboard_redirects_inspector_to_its_own_dashboard(): void
    {
        $inspector = $this->makeInspector();

        $this->actingAs($inspector)->get('/dashboard')->assertRedirect('/inspector/dashboard');
    }

    public function test_login_redirects_each_new_role_to_its_dashboard(): void
    {
        $treasurer = $this->makeTreasurer();
        $this->post('/login', ['email' => $treasurer->email, 'password' => 'Password123!'])
            ->assertRedirect('/treasurer/dashboard');

        $this->post('/logout');

        $inspector = $this->makeInspector();
        $this->post('/login', ['email' => $inspector->email, 'password' => 'Password123!'])
            ->assertRedirect('/inspector/dashboard');
    }
}
