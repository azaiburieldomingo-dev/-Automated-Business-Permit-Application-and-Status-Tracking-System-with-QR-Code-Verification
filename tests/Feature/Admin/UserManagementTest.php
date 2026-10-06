<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New BPLO',
            'email' => 'newbplo@bansud.gov.ph',
            'role' => User::ROLE_BPLO,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => true,
        ])->assertRedirect('/admin/users');

        $user = User::where('email', 'newbplo@bansud.gov.ph')->firstOrFail();
        $this->assertSame(User::ROLE_BPLO, $user->role);
    }

    public function test_admin_can_edit_a_users_role(): void
    {
        $admin = $this->makeAdmin();
        $target = $this->makeApplicant();

        $this->actingAs($admin)->put("/admin/users/{$target->getKey()}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => User::ROLE_BPLO,
            'is_active' => true,
        ])->assertRedirect('/admin/users');

        $this->assertSame(User::ROLE_BPLO, $target->fresh()->role);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $bplo = $this->makeBplo();

        $this->actingAs($bplo)->get('/admin/users')->assertForbidden();
        $this->actingAs($bplo)->post('/admin/users', [
            'name' => 'X',
            'email' => 'x@example.com',
            'role' => User::ROLE_ADMIN,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertForbidden();
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put("/admin/users/{$admin->getKey()}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => User::ROLE_APPLICANT,
            'is_active' => false,
        ])->assertRedirect('/admin/users');

        $admin->refresh();
        // Self cannot be demoted or deactivated.
        $this->assertTrue($admin->is_active);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
    }
}
