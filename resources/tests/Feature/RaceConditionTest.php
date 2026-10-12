<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Services\ApplicationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class RaceConditionTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_five_submissions_in_same_year_yield_gap_free_increasing_tracking_numbers(): void
    {
        Event::fake();
        $workflow = app(ApplicationWorkflowService::class);
        $year = (int) now()->year;

        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $application = $this->makeApplication($this->makeApplicant(), Application::STATUS_DRAFT);
            $workflow->submit($application, $application->user);
            $numbers[] = $application->fresh()->tracking_number;
        }

        // All formatted and unique.
        foreach ($numbers as $number) {
            $this->assertMatchesRegularExpression('/^BANSUD-'.$year.'-\d{6}$/', $number);
        }
        $this->assertCount(5, array_unique($numbers));

        // Strictly increasing and gap-free sequence component.
        $sequences = array_map(
            fn (string $n): int => (int) substr($n, -6),
            $numbers,
        );

        $this->assertSame([1, 2, 3, 4, 5], $sequences);
    }
}
