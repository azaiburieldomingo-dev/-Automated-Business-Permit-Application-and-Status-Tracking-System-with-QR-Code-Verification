<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_form_renders(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_successful_registration_creates_an_applicant(): void
    {
        $response = $this->post('/register', [
            'name' => 'New Applicant',
            'email' => 'newapplicant@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'newapplicant@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_APPLICANT, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_role_input_is_ignored_and_user_stays_applicant(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky User',
            'email' => 'sneaky@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => User::ROLE_ADMIN,
        ]);

        $user = User::where('email', 'sneaky@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_APPLICANT, $user->role);
    }

    public function test_validation_errors_block_registration(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::create([
            'name' => 'Existing',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $this->post('/register', [
            'name' => 'Another',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors('email');
    }
}
