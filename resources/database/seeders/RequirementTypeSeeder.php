<?php

namespace Database\Seeders;

use App\Models\RequirementType;
use Illuminate\Database\Seeder;

class RequirementTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Barangay Business Clearance', 'code' => 'barangay_clearance', 'is_required' => true, 'sort_order' => 1],
            ['name' => 'DTI/SEC Registration', 'code' => 'dti_sec_registration', 'is_required' => true, 'sort_order' => 2],
            ['name' => 'Valid ID', 'code' => 'valid_id', 'is_required' => true, 'sort_order' => 3],
            ['name' => 'Previous Permit', 'code' => 'previous_permit', 'is_required' => false, 'sort_order' => 4],
            ['name' => 'Other', 'code' => 'other', 'is_required' => false, 'sort_order' => 5],
        ];

        foreach ($types as $type) {
            RequirementType::create($type);
        }
    }
}
