<?php

namespace Database\Seeders;

use App\Models\FeeRate;
use App\Models\FeeType;
use Illuminate\Database\Seeder;

class FeeRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rates = [
            'business_tax' => ['label' => 'Standard business tax', 'amount' => 500.00],
            'permit_fee' => ['label' => 'Standard permit fee', 'amount' => 300.00],
            'regulatory_fees' => ['label' => 'Standard regulatory fees', 'amount' => 150.00],
            'other_fees' => ['label' => 'Standard other fees', 'amount' => 50.00],
        ];

        foreach ($rates as $code => $rate) {
            $feeType = FeeType::where('code', $code)->firstOrFail();

            FeeRate::create([
                'fee_type_id' => $feeType->id,
                'label' => $rate['label'],
                'amount' => $rate['amount'],
                'calc_type' => 'fixed',
                'is_active' => true,
            ]);
        }
    }
}
