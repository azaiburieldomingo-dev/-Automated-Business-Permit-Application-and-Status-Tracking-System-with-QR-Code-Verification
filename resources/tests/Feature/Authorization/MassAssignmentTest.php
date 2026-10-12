<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_is_not_mass_assignable_via_create(): void
    {
        $user = User::create([
            'name' => 'Mass Assign',
            'email' => 'mass@example.com',
            'password' => 'Password123!',
            'is_active' => true,
            'role' => User::ROLE_ADMIN,
        ]);

        // role was silently ignored by mass assignment; the DB default applies.
        $this->assertNotSame(User::ROLE_ADMIN, $user->fresh()->role);
        $this->assertSame(User::ROLE_APPLICANT, $user->fresh()->role);
    }

    public function test_role_is_not_mass_assignable_via_fill(): void
    {
        $user = new User;
        $user->fill([
            'name' => 'Fill Test',
            'email' => 'fill@example.com',
            'password' => 'Password123!',
            'is_active' => true,
            'role' => User::ROLE_ADMIN,
        ]);
        $user->save();

        $this->assertSame(User::ROLE_APPLICANT, $user->fresh()->role);
    }

    public function test_register_endpoint_ignores_role_input(): void
    {
        $this->post('/register', [
            'name' => 'Register Mass',
            'email' => 'registermass@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]);

        $user = User::where('email', 'registermass@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_APPLICANT, $user->role);
    }
}
