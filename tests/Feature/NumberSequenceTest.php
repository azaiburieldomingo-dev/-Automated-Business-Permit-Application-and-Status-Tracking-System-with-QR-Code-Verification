<?php

namespace Tests\Feature;

use App\Services\PermitNumberService;
use App\Services\TrackingNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_number_is_formatted_and_sequential(): void
    {
        $service = app(TrackingNumberService::class);

        $this->assertSame('BANSUD-2025-000001', $service->generate(2025));
        $this->assertSame('BANSUD-2025-000002', $service->generate(2025));
        $this->assertSame('BANSUD-2026-000001', $service->generate(2026));
    }

    public function test_permit_number_is_formatted_and_sequential(): void
    {
        $service = app(PermitNumberService::class);

        $this->assertSame('BP-2025-000001', $service->generate(2025));
        $this->assertSame('BP-2025-000002', $service->generate(2025));
    }

    public function test_tracking_numbers_are_gap_free_across_many_calls(): void
    {
        $service = app(TrackingNumberService::class);
        $numbers = [];

        for ($i = 0; $i < 10; $i++) {
            $numbers[] = $service->generate(2025);
        }

        $expected = [];
        for ($i = 1; $i <= 10; $i++) {
            $expected[] = sprintf('BANSUD-2025-%06d', $i);
        }

        $this->assertSame($expected, $numbers);
    }
}
