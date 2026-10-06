<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\FeeRate;
use App\Models\FeeType;
use App\Models\RequirementType;
use App\Models\User;
use Database\Seeders\FeeRateSeeder;
use Database\Seeders\FeeTypeSeeder;
use Database\Seeders\RequirementTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RequirementTypeSeeder::class, FeeTypeSeeder::class, FeeRateSeeder::class]);
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'A', 'email' => 'a'.uniqid().'@x.com', 'password' => 'Password123!', 'is_active' => true]);
        $u->role = User::ROLE_ADMIN;
        $u->save();

        return $u;
    }

    private function applicant(): User
    {
        $u = User::create(['name' => 'B', 'email' => 'b'.uniqid().'@x.com', 'password' => 'Password123!', 'is_active' => true]);
        $u->role = User::ROLE_APPLICANT;
        $u->save();

        return $u;
    }

    public function test_admin_crud_views_render(): void
    {
        $admin = $this->admin();

        foreach ([
            '/admin/users', '/admin/users/create',
            '/admin/requirement-types', '/admin/requirement-types/create',
            '/admin/fee-types', '/admin/fee-types/create',
            '/admin/fee-rates', '/admin/fee-rates/create',
            '/admin/settings', '/admin/audit-logs',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->actingAs($admin)->get('/admin/users/'.$admin->getKey().'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin/requirement-types/'.RequirementType::first()->getKey().'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin/fee-types/'.FeeType::first()->getKey().'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin/fee-rates/'.FeeRate::first()->getKey().'/edit')->assertOk();
    }

    public function test_applicant_views_render(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant)->get('/dashboard')->assertOk();
        $this->actingAs($applicant)->get('/applications/create')->assertOk();

        $app = new Application([
            'applicant_name' => 'B', 'contact_number' => '09171234567', 'email' => $applicant->email,
            'business_name' => 'Biz', 'business_address' => 'Addr', 'business_nature' => 'Trade',
            'ownership_type' => 'Sole Proprietorship', 'barangay' => 'Pob', 'number_of_employees' => 0,
        ]);
        $app->user()->associate($applicant);
        $app->status = Application::STATUS_DRAFT;
        $app->save();

        $this->actingAs($applicant)->get("/applications/{$app->getKey()}/edit")->assertOk();
        $this->actingAs($applicant)->get("/applications/{$app->getKey()}/summary")->assertOk();
        $this->actingAs($applicant)->get("/applications/{$app->getKey()}")->assertOk();
    }

    public function test_bplo_views_render(): void
    {
        $bplo = User::create(['name' => 'C', 'email' => 'c'.uniqid().'@x.com', 'password' => 'Password123!', 'is_active' => true]);
        $bplo->role = User::ROLE_BPLO;
        $bplo->save();

        $applicant = $this->applicant();
        $app = new Application([
            'applicant_name' => 'Juan Cruz', 'contact_number' => '09171234567', 'email' => $applicant->email,
            'business_name' => 'Biz', 'business_address' => 'Addr', 'business_nature' => 'Trade',
            'ownership_type' => 'Sole Proprietorship', 'barangay' => 'Pob', 'number_of_employees' => 0,
        ]);
        $app->user()->associate($applicant);
        $app->status = Application::STATUS_SUBMITTED;
        $app->tracking_number = 'BANSUD-'.date('Y').'-000900';
        $app->submitted_at = now();
        $app->save();

        $this->actingAs($bplo)->get('/bplo/dashboard')->assertOk();
        $this->actingAs($bplo)->get('/bplo/applications')->assertOk();
        $this->actingAs($bplo)->get("/bplo/applications/{$app->getKey()}")->assertOk();
    }

    public function test_auth_views_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/sometoken')->assertOk();
        $this->get('/track')->assertOk();
    }
}
