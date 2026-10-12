<?php

namespace Tests\Support;

use App\Models\Application;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;

/**
 * Shared factory helpers for feature tests. Keeps role assignment (never
 * mass-assignable) and application snapshots consistent across the suite.
 */
trait MakesDomainData
{
    private static int $domainSequence = 0;

    private function nextSequence(): int
    {
        return ++self::$domainSequence;
    }

    protected function makeUser(string $role, bool $active = true): User
    {
        $user = User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => $active,
        ]);
        $user->role = $role;
        $user->save();

        return $user;
    }

    protected function makeApplicant(bool $active = true): User
    {
        return $this->makeUser(User::ROLE_APPLICANT, $active);
    }

    protected function makeBplo(): User
    {
        return $this->makeUser(User::ROLE_BPLO);
    }

    protected function makeAdmin(): User
    {
        return $this->makeUser(User::ROLE_ADMIN);
    }

    protected function makeTreasurer(): User
    {
        return $this->makeUser(User::ROLE_TREASURER);
    }

    protected function makeInspector(): User
    {
        return $this->makeUser(User::ROLE_INSPECTOR);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeApplication(User $owner, string $status, array $overrides = []): Application
    {
        $application = new Application(array_merge([
            'applicant_name' => 'Jane Dela Cruz',
            'contact_number' => '09171234567',
            'email' => $owner->email,
            'business_name' => 'Jane Store',
            'business_address' => '1 Rizal St, Bansud',
            'business_nature' => 'Retail',
            'ownership_type' => 'Sole Proprietorship',
            'barangay' => 'Poblacion',
            'number_of_employees' => 2,
        ], $overrides));
        $application->user()->associate($owner);
        $application->status = $status;

        if ($status !== Application::STATUS_DRAFT) {
            $application->tracking_number = $overrides['tracking_number']
                ?? 'BANSUD-'.date('Y').'-'.str_pad((string) $this->nextSequence(), 6, '0', STR_PAD_LEFT);
        }

        $application->save();

        return $application;
    }

    protected function makePendingPayment(Application $application): Payment
    {
        return Payment::create([
            'application_id' => $application->getKey(),
            'tracking_number' => $application->tracking_number,
            'amount' => 1000,
            'breakdown_json' => ['lines' => [], 'total' => 1000],
            'method' => 'gcash',
            'reference_number' => 'SANDBOX-test',
            'status' => Payment::STATUS_PROCESSING,
            'verification_status' => Payment::VERIFY_UNVERIFIED,
        ]);
    }

    protected function makePermit(Application $application, string $status = Permit::STATUS_ACTIVE, ?string $validUntil = null): Permit
    {
        return Permit::create([
            'application_id' => $application->getKey(),
            'permit_number' => 'BP-'.date('Y').'-'.str_pad((string) $this->nextSequence(), 6, '0', STR_PAD_LEFT),
            'verification_token' => bin2hex(random_bytes(32)),
            'business_name' => $application->business_name,
            'owner_name' => $application->applicant_name,
            'business_address' => $application->business_address,
            'business_nature' => $application->business_nature,
            'issued_at' => now()->subMonth(),
            'valid_until' => $validUntil ?? now()->addMonths(11)->toDateString(),
            'status' => $status,
        ]);
    }
}
