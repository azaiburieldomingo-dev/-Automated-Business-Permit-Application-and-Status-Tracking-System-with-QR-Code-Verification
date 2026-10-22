<?php

namespace Tests\Feature\Applicant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'permit.payment.mode' => 'manual',
            'permit.payment.methods.gcash' => true,
            'permit.payment.accounts.gcash.number' => '09670829993',
            'permit.payment.accounts.gcash.name' => 'Aiza Domingo',
        ]);
    }

    public function test_help_page_renders_for_an_applicant_with_the_configured_gcash_number(): void
    {
        $applicant = $this->makeApplicant();

        $this->actingAs($applicant)->get('/help')
            ->assertOk()
            ->assertSee('Help &amp; Support', false)
            ->assertSee('09670829993')
            ->assertSee('Aiza Domingo');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/help')->assertRedirect(route('login'));
    }

    public function test_non_applicant_role_is_blocked_by_the_middleware(): void
    {
        $this->actingAs($this->makeBplo())->get('/help')->assertForbidden();
    }
}
