<?php

namespace Tests\Feature\Permit;

use App\Models\Application;
use App\Models\Permit;
use App\Services\PermitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class QrLinkTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_qr_link_uses_the_public_address_not_localhost(): void
    {
        config(['app.url' => 'http://localhost:8000', 'permit.public_url' => 'http://10.0.0.5:8000/']);
        $permit = $this->makePermit($this->makeApplication($this->makeApplicant(), Application::STATUS_PERMIT_RELEASED));

        $this->assertSame('http://10.0.0.5:8000/verify/'.$permit->verification_token, $permit->verifyUrl());
    }

    public function test_the_encoded_link_opens_the_verification_page(): void
    {
        config(['permit.public_url' => 'http://10.0.0.5:8000']);
        $permit = $this->makePermit($this->makeApplication($this->makeApplicant(), Application::STATUS_PERMIT_RELEASED));

        // What a phone requests after scanning: only the path matters to the app.
        $path = parse_url($permit->verifyUrl(), PHP_URL_PATH);

        $this->get($path)->assertOk()->assertSee('VALID PERMIT');
    }

    public function test_permit_page_qr_and_link_are_clickable(): void
    {
        config(['permit.public_url' => 'http://10.0.0.5:8000']);
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_PERMIT_RELEASED);
        $permit = $this->makePermit($application, Permit::STATUS_ACTIVE);
        $url = $permit->verifyUrl();

        $response = $this->actingAs($applicant)->get("/applications/{$application->getKey()}/permit");

        $response->assertOk()
            ->assertSee('<a href="'.$url.'" target="_blank" rel="noopener" title="Open the verification page"', false)
            ->assertSee('Open Verification Page')
            ->assertSee('value="'.$url.'"', false)
            ->assertDontSee('only opens on this computer');
    }

    public function test_permit_page_warns_when_qr_points_to_localhost(): void
    {
        config(['permit.public_url' => 'http://localhost:8000']);
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_PERMIT_RELEASED);
        $this->makePermit($application);

        $this->actingAs($applicant)->get("/applications/{$application->getKey()}/permit")
            ->assertOk()
            ->assertSee('only opens on this computer');
    }

    public function test_downloaded_pdf_always_uses_the_current_public_address(): void
    {
        Storage::fake('local');
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_PAYMENT_VERIFIED);

        config(['permit.public_url' => 'http://localhost:8000']);
        $permit = app(PermitService::class)->release($application, $this->makeBplo());
        $old = Storage::disk('local')->get($permit->pdf_path);

        // Address changes (e.g. moved from localhost to the Wi-Fi IP).
        config(['permit.public_url' => 'http://10.0.0.5:8000']);
        $this->actingAs($applicant)
            ->get("/applications/{$application->getKey()}/permit/download")
            ->assertOk();

        $new = Storage::disk('local')->get($permit->fresh()->pdf_path);
        $this->assertNotSame($old, $new, 'PDF should be re-rendered with the new QR address.');
    }
}
