<?php

namespace Tests\Feature\Applicant;

use App\Models\Application;
use App\Models\ApplicationRequirement;
use App\Models\RequirementType;
use Database\Seeders\RequirementTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class RequirementUploadTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RequirementTypeSeeder::class);
    }

    private function requiredType(): RequirementType
    {
        return RequirementType::where('is_required', true)->firstOrFail();
    }

    public function test_upload_stores_file_on_local_disk(): void
    {
        Storage::fake('local');
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_DRAFT);
        $type = $this->requiredType();

        $this->actingAs($applicant)->post(
            "/applications/{$application->getKey()}/requirements/{$type->getKey()}",
            [
                'requirement_type_id' => $type->getKey(),
                'file' => UploadedFile::fake()->create('clearance.pdf', 100, 'application/pdf'),
            ],
        )->assertRedirect();

        $requirement = ApplicationRequirement::where('application_id', $application->getKey())
            ->where('requirement_type_id', $type->getKey())
            ->firstOrFail();

        $this->assertSame(ApplicationRequirement::CHECK_COMPLETE, $requirement->checklist_status);
        Storage::disk('local')->assertExists($requirement->stored_path);
    }

    public function test_wrong_mime_is_rejected(): void
    {
        Storage::fake('local');
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_DRAFT);
        $type = $this->requiredType();

        $this->actingAs($applicant)->post(
            "/applications/{$application->getKey()}/requirements/{$type->getKey()}",
            [
                'requirement_type_id' => $type->getKey(),
                'file' => UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream'),
            ],
        )->assertSessionHasErrors('file');

        $this->assertDatabaseCount('application_requirements', 0);
    }

    public function test_file_larger_than_max_is_rejected(): void
    {
        Storage::fake('local');
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_DRAFT);
        $type = $this->requiredType();

        // 5 MB exceeds the 4096 KB cap from config('permit.uploads.max_kb').
        $this->actingAs($applicant)->post(
            "/applications/{$application->getKey()}/requirements/{$type->getKey()}",
            [
                'requirement_type_id' => $type->getKey(),
                'file' => UploadedFile::fake()->create('big.pdf', 5000, 'application/pdf'),
            ],
        )->assertSessionHasErrors('file');

        $this->assertDatabaseCount('application_requirements', 0);
    }

    public function test_non_owner_cannot_download_private_file(): void
    {
        Storage::fake('local');
        $owner = $this->makeApplicant();
        $application = $this->makeApplication($owner, Application::STATUS_DRAFT);
        $type = $this->requiredType();

        $this->actingAs($owner)->post(
            "/applications/{$application->getKey()}/requirements/{$type->getKey()}",
            [
                'requirement_type_id' => $type->getKey(),
                'file' => UploadedFile::fake()->create('clearance.pdf', 100, 'application/pdf'),
            ],
        );

        $requirement = ApplicationRequirement::where('application_id', $application->getKey())->firstOrFail();

        // The owner can download their own file.
        $this->actingAs($owner)
            ->get("/requirements/{$requirement->getKey()}/file")
            ->assertOk();

        // Another applicant cannot.
        $stranger = $this->makeApplicant();
        $this->actingAs($stranger)
            ->get("/requirements/{$requirement->getKey()}/file")
            ->assertForbidden();
    }
}
