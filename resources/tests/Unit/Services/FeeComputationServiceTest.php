<?php

namespace Tests\Unit\Services;

use App\Models\Application;
use App\Models\FeeRate;
use App\Models\FeeType;
use App\Models\User;
use App\Services\FeeComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FeeComputationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplication(): Application
    {
        $user = User::create([
            'name' => 'Fee Tester',
            'email' => 'fee_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);
        $user->role = User::ROLE_APPLICANT;
        $user->save();

        $application = new Application([
            'applicant_name' => 'Fee Tester',
            'contact_number' => '09171234567',
            'email' => 'fee@example.com',
            'business_name' => 'Fee Biz',
            'business_address' => '1 Street',
            'business_nature' => 'Retail',
            'ownership_type' => 'Sole Proprietorship',
            'barangay' => 'Poblacion',
            'number_of_employees' => 0,
        ]);
        $application->user()->associate($user);
        $application->status = Application::STATUS_APPROVED_FOR_PAYMENT;
        $application->save();

        return $application;
    }

    private function seedRate(string $code, string $name, float $amount, int $sort, bool $typeActive = true, bool $rateActive = true): void
    {
        $type = FeeType::create([
            'name' => $name,
            'code' => $code,
            'is_active' => $typeActive,
            'sort_order' => $sort,
        ]);

        FeeRate::create([
            'fee_type_id' => $type->getKey(),
            'label' => $name.' rate',
            'amount' => $amount,
            'calc_type' => 'fixed',
            'is_active' => $rateActive,
        ]);
    }

    public function test_computes_breakdown_and_total_from_active_rates(): void
    {
        $this->seedRate('business_tax', 'Business Tax', 500.00, 1);
        $this->seedRate('permit_fee', 'Permit Fee', 300.00, 2);
        $this->seedRate('regulatory_fees', 'Regulatory Fees', 150.00, 3);

        $breakdown = app(FeeComputationService::class)->computeFor($this->makeApplication());

        $this->assertCount(3, $breakdown->lines);
        $this->assertSame(950.0, $breakdown->total);
        // Ordered by fee type sort_order.
        $this->assertSame('business_tax', $breakdown->lines[0]['code']);
        $this->assertSame('regulatory_fees', $breakdown->lines[2]['code']);
    }

    public function test_inactive_rates_and_types_are_excluded(): void
    {
        $this->seedRate('business_tax', 'Business Tax', 500.00, 1);
        $this->seedRate('inactive_rate', 'Inactive Rate', 999.00, 2, true, false);
        $this->seedRate('inactive_type', 'Inactive Type', 888.00, 3, false, true);

        $breakdown = app(FeeComputationService::class)->computeFor($this->makeApplication());

        $this->assertCount(1, $breakdown->lines);
        $this->assertSame(500.0, $breakdown->total);
    }

    public function test_empty_rate_table_yields_zero_total_and_logs_warning(): void
    {
        Log::spy();

        $breakdown = app(FeeComputationService::class)->computeFor($this->makeApplication());

        $this->assertSame([], $breakdown->lines);
        $this->assertSame(0.0, $breakdown->total);

        Log::shouldHaveReceived('warning')->once();
    }
}
