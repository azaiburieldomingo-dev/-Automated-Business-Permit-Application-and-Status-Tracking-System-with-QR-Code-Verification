<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            RequirementTypeSeeder::class,
            FeeTypeSeeder::class,
            FeeRateSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('===========================================');
        $this->command->info('  Seeded Credentials');
        $this->command->info('===========================================');
        $this->command->info('  Admin:     admin@bansud.gov.ph / Password123!');
        $this->command->info('  BPLO:      bplo@bansud.gov.ph / Password123!');
        $this->command->info('  Treasurer: treasurer@bansud.gov.ph / Password123!');
        $this->command->info('  Inspector: inspector@bansud.gov.ph / Password123!');
        $this->command->info('  Applicant: applicant@example.com / Password123!');
        $this->command->info('===========================================');
        $this->command->info('');
    }
}
