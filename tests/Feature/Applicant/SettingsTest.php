<?php

namespace Tests\Feature\Applicant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_profile_update_changes_name_contact_address_and_email_without_touching_role(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)
            ->put('/settings/profile', [
                'name' => 'Updated Name',
                'contact_number' => '09181234567',
                'address' => '42 New St, Bansud',
                'email' => 'updated@example.com',
            ])
            ->assertRedirect(route('settings'));

        $applicant->refresh();
        $this->assertSame('Updated Name', $applicant->name);
        $this->assertSame('09181234567', $applicant->contact_number);
        $this->assertSame('42 New St, Bansud', $applicant->address);
        $this->assertSame('updated@example.com', $applicant->email);
        $this->assertSame(User::ROLE_APPLICANT, $applicant->role);
    }

    public function test_role_is_not_mass_assignable_via_the_profile_endpoint(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)->put('/settings/profile', [
            'name' => 'Updated Name',
            'email' => 'noescalation@example.com',
            'role' => User::ROLE_ADMIN,
        ])->assertRedirect(route('settings'));

        $this->assertSame(User::ROLE_APPLICANT, $applicant->fresh()->role);
    }

    public function test_email_uniqueness_rejects_another_users_email(): void
    {
        $applicant = $this->makeApplicant();
        $other = $this->makeApplicant();

        $this->actingAs($applicant)
            ->from('/settings')
            ->put('/settings/profile', [
                'name' => $applicant->name,
                'email' => $other->email,
            ])
            ->assertRedirect('/settings')
            ->assertSessionHasErrors('email');

        $this->assertNotSame($other->email, $applicant->fresh()->email);
    }

    public function test_profile_save_preserves_a_max_32_style_contact_number(): void
    {
        // A landline / formatted number the strict ApplicationRequest regex would
        // reject must still pass here (rule pinned to RegisterRequest: max:32).
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)
            ->put('/settings/profile', [
                'name' => $applicant->name,
                'contact_number' => '(02) 8123 4567',
                'email' => $applicant->email,
            ])
            ->assertRedirect(route('settings'))
            ->assertSessionHasNoErrors();

        $this->assertSame('(02) 8123 4567', $applicant->fresh()->contact_number);
    }

    public function test_password_change_with_correct_current_password_succeeds_and_authenticates(): void
    {
        $applicant = $this->makeApplicant(); // created with password Password123!

        $this->actingAs($applicant)
            ->put('/settings/password', [
                'current_password' => 'Password123!',
                'password' => 'NewSecret123',
                'password_confirmation' => 'NewSecret123',
            ])
            ->assertRedirect(route('settings'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewSecret123', $applicant->fresh()->password));
    }

    public function test_password_change_with_wrong_current_password_is_rejected_and_unchanged(): void
    {
        $applicant = $this->makeApplicant();
        $originalHash = $applicant->password;

        $this->actingAs($applicant)
            ->from('/settings')
            ->put('/settings/password', [
                'current_password' => 'WrongPassword123',
                'password' => 'NewSecret123',
                'password_confirmation' => 'NewSecret123',
            ])
            ->assertRedirect('/settings')
            ->assertSessionHasErrors('current_password');

        $this->assertSame($originalHash, $applicant->fresh()->password);
        $this->assertTrue(Hash::check('Password123!', $applicant->fresh()->password));
    }

    public function test_change_password_policy_matches_registration(): void
    {
        $applicant = $this->makeApplicant();

        // A value that registration REJECTS (no numbers / not mixed case) is rejected here too.
        $this->actingAs($applicant)
            ->from('/settings')
            ->put('/settings/password', [
                'current_password' => 'Password123!',
                'password' => 'alllowercase',
                'password_confirmation' => 'alllowercase',
            ])
            ->assertRedirect('/settings')
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Password123!', $applicant->fresh()->password));

        // A value that registration ACCEPTS (8+, mixed case, numbers) is accepted here.
        $this->actingAs($applicant)
            ->put('/settings/password', [
                'current_password' => 'Password123!',
                'password' => 'Accepted123',
                'password_confirmation' => 'Accepted123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Accepted123', $applicant->fresh()->password));
    }
}
