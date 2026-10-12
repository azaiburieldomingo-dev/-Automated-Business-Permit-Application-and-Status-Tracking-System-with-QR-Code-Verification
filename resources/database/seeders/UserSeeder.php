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

        $treasurer = User::create([
            'name' => 'Treasurer Staff',
            'email' => 'treasurer@bansud.gov.ph',
            'password' => 'Password123!',
        ]);
        $treasurer->role = User::ROLE_TREASURER;
        $treasurer->is_active = true;
        $treasurer->save();

        $inspector = User::create([
            'name' => 'Inspector Staff',
            'email' => 'inspector@bansud.gov.ph',
            'password' => 'Password123!',
        ]);
        $inspector->role = User::ROLE_INSPECTOR;
        $inspector->is_active = true;
        $inspector->save();

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
