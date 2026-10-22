<?php

namespace Tests\Feature\Applicant;

use App\Models\Application;
use App\Models\Business;
use App\Models\RequirementType;
use App\Models\User;
use Database\Seeders\RequirementTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class BusinessProfileTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Store',
            'address' => '1 Rizal St, Bansud',
            'barangay' => 'Poblacion',
            'nature' => 'Retail',
            'ownership_type' => 'Sole Proprietorship',
            'dti_sec_type' => 'DTI',
            'dti_sec_number' => 'DTI-123',
            'number_of_employees' => 3,
        ], $overrides);
    }

    public function test_first_put_creates_a_business_for_the_authed_applicant(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)
            ->put('/business-profile', $this->profilePayload())
            ->assertRedirect(route('business-profile.edit'));

        $this->assertSame(1, Business::where('user_id', $applicant->getKey())->count());

        $business = Business::where('user_id', $applicant->getKey())->firstOrFail();
        $this->assertSame('Jane Store', $business->name);
        $this->assertSame($applicant->getKey(), $business->user_id);
    }

    public function test_second_put_updates_the_same_row_confirming_one_per_user(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)->put('/business-profile', $this->profilePayload());
        $first = Business::where('user_id', $applicant->getKey())->firstOrFail();

        $this->actingAs($applicant)
            ->put('/business-profile', $this->profilePayload([
                'name' => 'Jane Updated Store',
                'number_of_employees' => 7,
            ]))
            ->assertRedirect(route('business-profile.edit'));

        // Still exactly one row — the oldest was edited, not a new one created.
        $this->assertSame(1, Business::where('user_id', $applicant->getKey())->count());

        $first->refresh();
        $this->assertSame($first->getKey(), Business::where('user_id', $applicant->getKey())->firstOrFail()->getKey());
        $this->assertSame('Jane Updated Store', $first->name);
        $this->assertSame(7, (int) $first->number_of_employees);
    }

    public function test_role_is_not_mass_assignable_via_the_business_profile_endpoint(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)
            ->put('/business-profile', $this->profilePayload(['role' => User::ROLE_ADMIN]))
            ->assertRedirect(route('business-profile.edit'));

        $this->assertSame(User::ROLE_APPLICANT, $applicant->fresh()->role);
    }

    public function test_creating_an_application_links_and_upserts_a_business_when_none_exists(): void
    {
        Storage::fake('local');
        $this->seed(RequirementTypeSeeder::class);
        $applicant = $this->makeApplicant();

        $this->assertSame(0, Business::where('user_id', $applicant->getKey())->count());

        $this->actingAs($applicant)->post('/applications', [
            'applicant_name' => 'Juan Dela Cruz',
            'contact_number' => '09171234567',
            'email' => $applicant->email,
            'business_name' => 'Juan Sari-Sari Store',
            'business_address' => '3 Rizal St, Bansud',
            'business_nature' => 'Retail',
            'ownership_type' => 'Sole Proprietorship',
            'barangay' => 'Poblacion',
            'number_of_employees' => 1,
            'dti_sec_type' => 'DTI',
            'dti_sec_number' => 'DTI-999',
        ])->assertRedirect();

        $business = Business::where('user_id', $applicant->getKey())->firstOrFail();
        $this->assertSame('Juan Sari-Sari Store', $business->name);

        $application = Application::where('user_id', $applicant->getKey())->firstOrFail();
        $this->assertSame($business->getKey(), $application->business_id);
    }

    public function test_business_id_null_flow_still_submits_for_existing_applications(): void
    {
        // An application created directly (no prior business, business_id null)
        // via the shared helper still works end-to-end as before.
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_SUBMITTED);

        $this->assertNull($application->business_id);

        $this->actingAs($applicant)
            ->get("/applications/{$application->getKey()}")
            ->assertOk()
            ->assertSee($application->tracking_number);
    }
}
