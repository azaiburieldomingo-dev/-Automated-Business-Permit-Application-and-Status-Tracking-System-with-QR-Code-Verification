<?php

namespace Tests\Feature\Applicant;

use App\Models\Application;
use App\Models\RequirementType;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use Database\Seeders\RequirementTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class ApplicationFlowTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RequirementTypeSeeder::class);
    }

    private function validPayload(User $applicant): array
    {
        return [
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
            'dti_sec_number' => 'DTI-123',
        ];
    }

    private function uploadRequiredDocuments(User $applicant, Application $application): void
    {
        foreach (RequirementType::where('is_required', true)->get() as $type) {
            $this->actingAs($applicant)->post(
                "/applications/{$application->getKey()}/requirements/{$type->getKey()}",
                [
                    'requirement_type_id' => $type->getKey(),
                    'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                ],
            )->assertRedirect();
        }
    }

    public function test_happy_path_from_draft_to_submitted(): void
    {
        Storage::fake('local');
        $applicant = $this->makeApplicant();

        // Create a draft.
        $this->actingAs($applicant)->post('/applications', $this->validPayload($applicant))->assertRedirect();
        $application = Application::where('user_id', $applicant->getKey())->firstOrFail();
        $this->assertSame(Application::STATUS_DRAFT, $application->status);
        $this->assertNull($application->tracking_number);

        // Review the summary before submitting.
        $this->actingAs($applicant)
            ->get("/applications/{$application->getKey()}/summary")
            ->assertOk();

        $this->uploadRequiredDocuments($applicant, $application);

        // Submit — a tracking number is assigned.
        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/submit")
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(Application::STATUS_SUBMITTED, $application->status);
        $this->assertMatchesRegularExpression('/^BANSUD-\d{4}-\d{6}$/', $application->tracking_number);

        // Status page renders for the owner.
        $this->actingAs($applicant)
            ->get("/applications/{$application->getKey()}")
            ->assertOk()
            ->assertSee($application->tracking_number);
    }

    public function test_cannot_submit_without_required_requirements(): void
    {
        Storage::fake('local');
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)->post('/applications', $this->validPayload($applicant));
        $application = Application::where('user_id', $applicant->getKey())->firstOrFail();

        // No requirements uploaded — submit should be blocked and the app stays DRAFT.
        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/submit")
            ->assertRedirect(route('applications.summary', $application))
            ->assertSessionHas('error');

        $this->assertSame(Application::STATUS_DRAFT, $application->fresh()->status);
        $this->assertNull($application->fresh()->tracking_number);
    }

    public function test_resubmit_from_for_correction_keeps_the_same_tracking_number(): void
    {
        Storage::fake('local');
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)->post('/applications', $this->validPayload($applicant));
        $application = Application::where('user_id', $applicant->getKey())->firstOrFail();
        $this->uploadRequiredDocuments($applicant, $application);

        $this->actingAs($applicant)->post("/applications/{$application->getKey()}/submit");
        $application->refresh();
        $originalTracking = $application->tracking_number;
        $this->assertNotNull($originalTracking);

        // BPLO returns it for correction.
        $workflow = app(ApplicationWorkflowService::class);
        $workflow->beginReview($application, $this->makeBplo());
        $workflow->returnForCorrection($application->fresh(), $this->makeBplo(), 'Fix the address.');
        $this->assertSame(Application::STATUS_FOR_CORRECTION, $application->fresh()->status);

        // Applicant resubmits — tracking number is unchanged.
        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/submit")
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(Application::STATUS_SUBMITTED, $application->status);
        $this->assertSame($originalTracking, $application->tracking_number);
    }
}
