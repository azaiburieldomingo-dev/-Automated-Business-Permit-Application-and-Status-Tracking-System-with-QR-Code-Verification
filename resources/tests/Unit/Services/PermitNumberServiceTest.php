<?php

namespace Tests\Unit\Services;

use App\Services\PermitNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_number_is_formatted_and_padded(): void
    {
        $service = app(PermitNumberService::class);

        $this->assertSame('BP-2025-000001', $service->generate(2025));
    }

    public function test_numbers_are_sequential_and_gap_free_in_same_year(): void
    {
        $service = app(PermitNumberService::class);

        $this->assertSame('BP-2025-000001', $service->generate(2025));
        $this->assertSame('BP-2025-000002', $service->generate(2025));
        $this->assertSame('BP-2025-000003', $service->generate(2025));
    }

    public function test_new_year_resets_the_sequence(): void
    {
        $service = app(PermitNumberService::class);

        $service->generate(2025);

        $this->assertSame('BP-2026-000001', $service->generate(2026));
        $this->assertSame('BP-2025-000002', $service->generate(2025));
    }

    public function test_prefix_comes_from_config(): void
    {
        config(['permit.permit_prefix' => 'PERMIT']);
        $service = app(PermitNumberService::class);

        $this->assertSame('PERMIT-2025-000001', $service->generate(2025));
    }
}
