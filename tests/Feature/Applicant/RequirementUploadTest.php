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

    public function test_many_requirements_upload_in_one_submit(): void
    {
        Storage::fake('local');
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_DRAFT);
        $types = RequirementType::where('is_active', true)->take(3)->get();

        $files = [];
        foreach ($types as $type) {
            $files[$type->getKey()] = UploadedFile::fake()->create("doc-{$type->getKey()}.pdf", 100, 'application/pdf');
        }

        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/requirements", ['files' => $files])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        foreach ($types as $type) {
            $requirement = ApplicationRequirement::where('application_id', $application->getKey())
                ->where('requirement_type_id', $type->getKey())
                ->firstOrFail();
            $this->assertSame(ApplicationRequirement::CHECK_COMPLETE, $requirement->checklist_status);
            Storage::disk('local')->assertExists($requirement->stored_path);
        }
    }

    public function test_batch_upload_only_touches_chosen_slots(): void
    {
        Storage::fake('local');
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_DRAFT);
        $type = $this->requiredType();

        // Only one slot gets a file; the others are simply not sent.
        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/requirements", [
                'files' => [$type->getKey() => UploadedFile::fake()->create('one.pdf', 50, 'application/pdf')],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('application_requirements', 1);
    }

    public function test_batch_upload_rejects_a_bad_file_without_saving_any(): void
    {
        Storage::fake('local');
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_DRAFT);
        $types = RequirementType::where('is_active', true)->take(2)->get();

        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/requirements", [
                'files' => [
                    $types[0]->getKey() => UploadedFile::fake()->create('ok.pdf', 50, 'application/pdf'),
                    $types[1]->getKey() => UploadedFile::fake()->create('bad.exe', 50, 'application/octet-stream'),
                ],
            ])
            ->assertSessionHasErrors('files.'.$types[1]->getKey());

        // Validation fails before anything is written.
        $this->assertDatabaseCount('application_requirements', 0);
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
