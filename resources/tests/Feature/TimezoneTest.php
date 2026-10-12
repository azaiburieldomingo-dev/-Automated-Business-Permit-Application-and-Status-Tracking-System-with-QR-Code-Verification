<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Services\ApplicationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_app_runs_on_philippine_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', date_default_timezone_get());
        $this->assertSame(8 * 3600, now()->getOffset());
    }

    public function test_recorded_times_are_philippine_time(): void
    {
        // 19:15 UTC is 03:15 the next morning in Bansud.
        $this->travelTo(Carbon::parse('2026-10-06 19:15:00', 'UTC'));

        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_DRAFT);
        app(ApplicationWorkflowService::class)->submit($application, $application->user);

        $this->assertSame('2026-10-07 03:15', $application->fresh()->submitted_at->format('Y-m-d H:i'));
    }

    public function test_new_year_uses_the_local_year_for_tracking_numbers(): void
    {
        Event::fake();

        // Still Dec 31 in UTC, but already Jan 1 2027 in the Philippines.
        $this->travelTo(Carbon::parse('2026-12-31 16:30:00', 'UTC'));

        $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_DRAFT);
        app(ApplicationWorkflowService::class)->submit($application, $application->user);

        $this->assertStringStartsWith('BANSUD-2027-', $application->fresh()->tracking_number);
    }
}
