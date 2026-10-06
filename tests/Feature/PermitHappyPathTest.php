<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\RequirementType;
use App\Models\User;
use App\Services\PermitService;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\FeeRateSeeder;
use Database\Seeders\FeeTypeSeeder;
use Database\Seeders\RequirementTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PermitHappyPathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RequirementTypeSeeder::class, FeeTypeSeeder::class, FeeRateSeeder::class]);
    }

    private function makeUser(string $role): User
    {
        $user = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => $role.'_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function test_full_lifecycle_from_submit_to_permit_release(): void
    {
        Storage::fake('local');

        $applicant = $this->makeUser(User::ROLE_APPLICANT);
        $bplo = $this->makeUser(User::ROLE_BPLO);

        // Create a draft application.
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
            'dti_sec_number' => 'DTI-123',
        ])->assertRedirect();

        $application = Application::where('user_id', $applicant->getKey())->firstOrFail();
        $this->assertSame(Application::STATUS_DRAFT, $application->status);

        // Upload every required requirement.
        foreach (RequirementType::where('is_required', true)->get() as $type) {
            $this->actingAs($applicant)->post(
                "/applications/{$application->getKey()}/requirements/{$type->getKey()}",
                [
                    'requirement_type_id' => $type->getKey(),
                    'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                ],
            )->assertRedirect();
        }

        // Submit — assigns a tracking number.
        $this->actingAs($applicant)
            ->post("/applications/{$application->getKey()}/submit")
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(Application::STATUS_SUBMITTED, $application->status);
        $this->assertMatchesRegularExpression('/^BANSUD-\d{4}-\d{6}$/', $application->tracking_number);

        // BPLO reviews and approves.
        $this->actingAs($bplo)->post("/bplo/applications/{$application->getKey()}/review")->assertRedirect();
        $this->actingAs($bplo)->post("/bplo/applications/{$application->getKey()}/approve")->assertRedirect();
        $this->assertSame(Application::STATUS_APPROVED_FOR_PAYMENT, $application->fresh()->status);

        // Applicant pays.
        $this->actingAs($applicant)->post("/applications/{$application->getKey()}/pay", [
            'method' => 'gcash',
        ])->assertRedirect();
        $this->assertSame(Application::STATUS_PAYMENT_PENDING, $application->fresh()->status);

        // The pay button alone never verifies.
        $payment = Payment::where('application_id', $application->getKey())->firstOrFail();
        $this->assertNotSame(Payment::STATUS_PAID, $payment->status);

        // BPLO confirms payment.
        $this->actingAs($bplo)->post("/bplo/payments/{$payment->getKey()}/confirm")->assertRedirect();
        $this->assertSame(Application::STATUS_PAYMENT_VERIFIED, $application->fresh()->status);

        // BPLO releases the permit.
        $this->actingAs($bplo)->post("/bplo/applications/{$application->getKey()}/release")->assertRedirect();
        $this->assertSame(Application::STATUS_PERMIT_RELEASED, $application->fresh()->status);

        $permit = Permit::where('application_id', $application->getKey())->firstOrFail();
        $this->assertMatchesRegularExpression('/^BP-\d{4}-\d{6}$/', $permit->permit_number);

        // Applicant downloads the permit PDF.
        $this->actingAs($applicant)
            ->get("/applications/{$application->getKey()}/permit/download")
            ->assertOk();

        // A notification row was created synchronously (no queue worker).
        $this->assertDatabaseHas('notifications', [
            'user_id' => $applicant->getKey(),
            'application_id' => $application->getKey(),
        ]);
    }

    public function test_permit_pdf_contains_embedded_svg_qr(): void
    {
        $permit = Permit::create([
            'application_id' => $this->makeReleasedApplication()->getKey(),
            'permit_number' => 'BP-'.date('Y').'-000055',
            'verification_token' => str_repeat('b', 64),
            'business_name' => 'PDF Biz',
            'owner_name' => 'Owner',
            'business_address' => 'Addr',
            'business_nature' => 'Trade',
            'issued_at' => now(),
            'valid_until' => now()->addYear(),
            'status' => Permit::STATUS_ACTIVE,
        ]);

        $service = app(PermitService::class);
        $uri = $service->qrDataUri('https://example.com/verify/'.$permit->verification_token);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);

        // The PDF renders without error and is non-empty.
        $pdf = Pdf::loadView('permits.pdf', [
            'permit' => $permit,
            'qrDataUri' => $uri,
            'verifyUrl' => 'https://example.com/verify/'.$permit->verification_token,
            'municipality' => config('permit.municipality'),
            'province' => config('permit.province'),
        ]);

        $this->assertNotEmpty($pdf->output());
    }

    private function makeReleasedApplication(): Application
    {
        $applicant = $this->makeUser(User::ROLE_APPLICANT);

        $application = new Application([
            'applicant_name' => 'Owner',
            'contact_number' => '09171234567',
            'email' => $applicant->email,
            'business_name' => 'PDF Biz',
            'business_address' => 'Addr',
            'business_nature' => 'Trade',
            'ownership_type' => 'Sole Proprietorship',
            'barangay' => 'Poblacion',
            'number_of_employees' => 0,
        ]);
        $application->user()->associate($applicant);
        $application->status = Application::STATUS_PERMIT_RELEASED;
        $application->tracking_number = 'BANSUD-'.date('Y').'-000055';
        $application->save();

        return $application;
    }
}
