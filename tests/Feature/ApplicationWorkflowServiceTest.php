<?php

namespace Tests\Feature;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ApplicationWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplication(string $status = Application::STATUS_DRAFT): Application
    {
        $user = User::create([
            'name' => 'Test Applicant',
            'email' => 'applicant_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);
        $user->role = User::ROLE_APPLICANT;
        $user->save();

        $application = new Application([
            'applicant_name' => 'Test Applicant',
            'contact_number' => '09171234567',
            'email' => $user->email,
            'business_name' => 'Test Business',
            'business_address' => '123 Main St',
            'business_nature' => 'Retail',
            'ownership_type' => 'sole proprietorship',
            'barangay' => 'Poblacion',
            'number_of_employees' => 2,
        ]);
        $application->user()->associate($user);
        $application->status = $status;
        $application->save();

        return $application;
    }

    public function test_submit_generates_tracking_number_and_transitions(): void
    {
        Event::fake();
        $application = $this->makeApplication();

        app(ApplicationWorkflowService::class)->submit($application);

        $this->assertSame(Application::STATUS_SUBMITTED, $application->fresh()->status);
        $this->assertNotNull($application->fresh()->tracking_number);
        $this->assertNotNull($application->fresh()->submitted_at);
    }

    public function test_illegal_transition_throws(): void
    {
        $application = $this->makeApplication(Application::STATUS_DRAFT);

        $this->expectException(InvalidStatusTransitionException::class);

        app(ApplicationWorkflowService::class)->transition($application, Application::STATUS_PERMIT_RELEASED);
    }

    public function test_transition_writes_history_and_audit_rows(): void
    {
        Event::fake();
        $application = $this->makeApplication();

        app(ApplicationWorkflowService::class)->submit($application);

        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $application->getKey(),
            'to_status' => Application::STATUS_SUBMITTED,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.status_changed',
            'subject_id' => $application->getKey(),
        ]);
    }

    public function test_return_for_correction_creates_remark(): void
    {
        Event::fake();
        $application = $this->makeApplication(Application::STATUS_UNDER_REVIEW);

        $bplo = User::create([
            'name' => 'BPLO Officer',
            'email' => 'bplo_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);
        $bplo->role = User::ROLE_BPLO;
        $bplo->save();

        app(ApplicationWorkflowService::class)->returnForCorrection($application, $bplo, 'Please fix the DTI number.');

        $this->assertSame(Application::STATUS_FOR_CORRECTION, $application->fresh()->status);
        $this->assertDatabaseHas('application_remarks', [
            'application_id' => $application->getKey(),
            'type' => 'CORRECTION',
            'body' => 'Please fix the DTI number.',
        ]);
    }
}
