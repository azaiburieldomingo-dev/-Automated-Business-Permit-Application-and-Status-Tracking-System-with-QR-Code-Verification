<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@bansud.gov.ph',
            'password' => 'Password123!',
        ]);
        $admin->role = User::ROLE_ADMIN;
        $admin->is_active = true;
        $admin->save();

        $bplo = User::create([
            'name' => 'BPLO Staff',
            'email' => 'bplo@bansud.gov.ph',
            'password' => 'Password123!',
        ]);
        $bplo->role = User::ROLE_BPLO;
        $bplo->is_active = true;
        $bplo->save();

        $applicant = User::create([
            'name' => 'Sample Applicant',
            'email' => 'applicant@example.com',
            'password' => 'Password123!',
        ]);
        $applicant->role = User::ROLE_APPLICANT;
        $applicant->is_active = true;
        $applicant->save();
    }
}
