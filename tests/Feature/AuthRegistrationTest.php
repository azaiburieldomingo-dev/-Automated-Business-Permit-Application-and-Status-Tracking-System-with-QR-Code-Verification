<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_forces_applicant_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'admin',
        ]);

        $response->assertRedirect('/dashboard');

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_APPLICANT, $user->role);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::create([
            'name' => 'Inactive',
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
            'is_active' => false,
        ]);
        $user->role = User::ROLE_APPLICANT;
        $user->save();

        $response = $this->from('/login')->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
