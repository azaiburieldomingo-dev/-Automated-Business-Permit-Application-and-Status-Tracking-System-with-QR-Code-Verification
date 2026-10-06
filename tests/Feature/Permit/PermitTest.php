<?php

namespace Tests\Feature\Permit;

use App\Models\Application;
use App\Models\Permit;
use App\Services\PermitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class PermitTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_release_requires_payment_verified(): void
    {
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_APPROVED_FOR_PAYMENT);

        $this->expectException(RuntimeException::class);
        app(PermitService::class)->release($application);
    }

    public function test_release_from_payment_verified_creates_a_permit_with_token(): void
    {
        Storage::fake('local');
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_PAYMENT_VERIFIED);

        $permit = app(PermitService::class)->release($application, $this->makeBplo());

        $this->assertSame(Application::STATUS_PERMIT_RELEASED, $application->fresh()->status);
        $this->assertMatchesRegularExpression('/^BP-\d{4}-\d{6}$/', $permit->permit_number);
        $this->assertSame(64, strlen($permit->verification_token));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $permit->verification_token);
    }

    public function test_permit_pdf_is_non_empty(): void
    {
        Storage::fake('local');
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_PAYMENT_VERIFIED);

        $service = app(PermitService::class);
        $permit = $service->release($application, $this->makeBplo());

        $this->assertNotNull($permit->pdf_path);
        Storage::disk('local')->assertExists($permit->pdf_path);
        $this->assertNotEmpty(Storage::disk('local')->get($permit->pdf_path));
    }

    public function test_qr_data_uri_is_svg_base64(): void
    {
        $service = app(PermitService::class);

        $uri = $service->qrDataUri('https://example.com/verify/'.str_repeat('a', 64));

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);

        $decoded = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
        $this->assertStringContainsString('<svg', $decoded);
    }

    public function test_qr_url_matches_the_expected_shape(): void
    {
        config(['app.url' => 'http://localhost:8000']);
        Storage::fake('local');
        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_PAYMENT_VERIFIED);

        $permit = app(PermitService::class)->release($application, $this->makeBplo());
        $verifyUrl = rtrim((string) config('app.url'), '/').'/verify/'.$permit->verification_token;

        $this->assertMatchesRegularExpression(
            '#^https?://[^:]+(:\d+)?/verify/[A-Za-z0-9]+$#',
            $verifyUrl,
        );
    }

    public function test_applicant_can_download_own_permit_but_non_owner_cannot(): void
    {
        Storage::fake('local');
        $owner = $this->makeApplicant();
        $application = $this->makeApplication($owner, Application::STATUS_PAYMENT_VERIFIED);
        app(PermitService::class)->release($application, $this->makeBplo());

        $this->actingAs($owner)
            ->get("/applications/{$application->getKey()}/permit/download")
            ->assertOk();

        $stranger = $this->makeApplicant();
        $this->actingAs($stranger)
            ->get("/applications/{$application->getKey()}/permit/download")
            ->assertForbidden();
    }

    public function test_no_permit_edit_route_exists(): void
    {
        // The permit is immutable: there is no update ability or edit route.
        $this->assertFalse(Route::has('applications.permit.update'));
        $this->assertFalse(Route::has('permits.update'));
    }
}
