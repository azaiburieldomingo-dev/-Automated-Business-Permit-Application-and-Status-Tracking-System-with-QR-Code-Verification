<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\FeeRate;
use App\Models\FeeType;
use App\Services\FeeComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class FeeManagementTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_admin_can_create_a_fee_type(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/fee-types', [
            'name' => 'Sanitary Fee',
            'code' => 'sanitary_fee',
            'is_active' => true,
            'sort_order' => 5,
        ])->assertRedirect('/admin/fee-types');

        $this->assertDatabaseHas('fee_types', ['code' => 'sanitary_fee']);
    }

    public function test_admin_can_create_and_edit_a_fee_rate(): void
    {
        $admin = $this->makeAdmin();
        $type = FeeType::create([
            'name' => 'Business Tax',
            'code' => 'business_tax',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)->post('/admin/fee-rates', [
            'fee_type_id' => $type->getKey(),
            'label' => 'Standard business tax',
            'amount' => 750.00,
            'is_active' => true,
        ])->assertRedirect('/admin/fee-rates');

        $rate = FeeRate::where('fee_type_id', $type->getKey())->firstOrFail();
        $this->assertSame('750.00', $rate->amount);

        // Edit the amount — amounts are data, not constants.
        $this->actingAs($admin)->put("/admin/fee-rates/{$rate->getKey()}", [
            'fee_type_id' => $type->getKey(),
            'label' => 'Updated business tax',
            'amount' => 1234.50,
            'is_active' => true,
        ])->assertRedirect('/admin/fee-rates');

        $this->assertSame('1234.50', $rate->fresh()->amount);
    }

    public function test_fee_amounts_are_data_driven_not_hardcoded(): void
    {
        $admin = $this->makeAdmin();
        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_APPROVED_FOR_PAYMENT);

        $type = FeeType::create([
            'name' => 'Business Tax',
            'code' => 'business_tax',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $rate = FeeRate::create([
            'fee_type_id' => $type->getKey(),
            'label' => 'Standard business tax',
            'amount' => 500.00,
            'calc_type' => 'fixed',
            'is_active' => true,
        ]);

        $this->assertSame(500.0, app(FeeComputationService::class)->computeFor($application)->total);

        // Change the amount through the admin screen; the computed total follows.
        $this->actingAs($admin)->put("/admin/fee-rates/{$rate->getKey()}", [
            'fee_type_id' => $type->getKey(),
            'label' => 'Standard business tax',
            'amount' => 900.00,
            'is_active' => true,
        ]);

        $this->assertSame(900.0, app(FeeComputationService::class)->computeFor($application)->total);
    }

    public function test_non_admin_cannot_manage_fees(): void
    {
        $bplo = $this->makeBplo();

        $this->actingAs($bplo)->get('/admin/fee-types')->assertForbidden();
        $this->actingAs($bplo)->get('/admin/fee-rates')->assertForbidden();
    }
}
