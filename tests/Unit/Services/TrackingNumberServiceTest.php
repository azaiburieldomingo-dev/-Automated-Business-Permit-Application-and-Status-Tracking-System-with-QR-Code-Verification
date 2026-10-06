<?php

namespace Tests\Unit\Services;

use App\Services\TrackingNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_number_is_formatted_and_padded(): void
    {
        $service = app(TrackingNumberService::class);

        $this->assertSame('BANSUD-2025-000001', $service->generate(2025));
    }

    public function test_numbers_are_sequential_and_gap_free_in_same_year(): void
    {
        $service = app(TrackingNumberService::class);

        $this->assertSame('BANSUD-2025-000001', $service->generate(2025));
        $this->assertSame('BANSUD-2025-000002', $service->generate(2025));
        $this->assertSame('BANSUD-2025-000003', $service->generate(2025));
    }

    public function test_new_year_resets_the_sequence(): void
    {
        $service = app(TrackingNumberService::class);

        $service->generate(2025);
        $service->generate(2025);

        $this->assertSame('BANSUD-2026-000001', $service->generate(2026));
        $this->assertSame('BANSUD-2025-000003', $service->generate(2025));
    }

    public function test_prefix_comes_from_config(): void
    {
        config(['permit.tracking_prefix' => 'TESTPFX']);
        $service = app(TrackingNumberService::class);

        $this->assertSame('TESTPFX-2025-000001', $service->generate(2025));
    }
}
