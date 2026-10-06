<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Policies\ApplicationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.'_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function makeApplicationFor(User $owner, string $status): Application
    {
        $application = new Application([
            'applicant_name' => $owner->name,
            'contact_number' => '09171234567',
            'email' => $owner->email,
            'business_name' => 'Biz',
            'business_address' => 'Addr',
            'business_nature' => 'Retail',
            'ownership_type' => 'sole proprietorship',
            'barangay' => 'Poblacion',
            'number_of_employees' => 0,
        ]);
        $application->user()->associate($owner);
        $application->status = $status;
        $application->save();

        return $application;
    }

    public function test_owner_can_update_draft(): void
    {
        $owner = $this->makeUser(User::ROLE_APPLICANT);
        $application = $this->makeApplicationFor($owner, Application::STATUS_DRAFT);

        $this->assertTrue((new ApplicationPolicy)->update($owner, $application));
    }

    public function test_owner_cannot_update_submitted(): void
    {
        $owner = $this->makeUser(User::ROLE_APPLICANT);
        $application = $this->makeApplicationFor($owner, Application::STATUS_SUBMITTED);

        $this->assertFalse((new ApplicationPolicy)->update($owner, $application));
    }

    public function test_non_owner_cannot_update(): void
    {
        $owner = $this->makeUser(User::ROLE_APPLICANT);
        $other = $this->makeUser(User::ROLE_APPLICANT);
        $application = $this->makeApplicationFor($owner, Application::STATUS_DRAFT);

        $this->assertFalse((new ApplicationPolicy)->update($other, $application));
    }

    public function test_only_staff_can_review(): void
    {
        $applicant = $this->makeUser(User::ROLE_APPLICANT);
        $bplo = $this->makeUser(User::ROLE_BPLO);

        $policy = new ApplicationPolicy;
        $this->assertFalse($policy->review($applicant));
        $this->assertTrue($policy->review($bplo));
    }
}
