<?php

namespace Database\Seeders;

use App\Models\FeeType;
use Illuminate\Database\Seeder;

class FeeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Business Tax', 'code' => 'business_tax', 'sort_order' => 1],
            ['name' => 'Permit Fee', 'code' => 'permit_fee', 'sort_order' => 2],
            ['name' => 'Regulatory Fees', 'code' => 'regulatory_fees', 'sort_order' => 3],
            ['name' => 'Other Fees', 'code' => 'other_fees', 'sort_order' => 4],
        ];

        foreach ($types as $type) {
            FeeType::create($type);
        }
    }
}
