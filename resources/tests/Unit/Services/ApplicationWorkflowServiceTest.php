<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApplicationWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplication(string $status): Application
    {
        $user = User::create([
            'name' => 'Flow Tester',
            'email' => 'flow_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);
        $user->role = User::ROLE_APPLICANT;
        $user->save();

        $application = new Application([
            'applicant_name' => 'Flow Tester',
            'contact_number' => '09171234567',
            'email' => $user->email,
            'business_name' => 'Flow Biz',
            'business_address' => '1 Street, Bansud',
            'business_nature' => 'Retail',
            'ownership_type' => 'Sole Proprietorship',
            'barangay' => 'Poblacion',
            'number_of_employees' => 0,
        ]);
        $application->user()->associate($user);
        $application->status = $status;

        if ($status !== Application::STATUS_DRAFT) {
            $application->tracking_number = 'BANSUD-2025-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        }

        $application->save();

        return $application;
    }

    private function staff(): User
    {
        $user = User::create([
            'name' => 'BPLO Staff',
            'email' => 'staff_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);
        $user->role = User::ROLE_BPLO;
        $user->save();

        return $user;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function legalTransitions(): array
    {
        return [
            'draft to submitted' => [Application::STATUS_DRAFT, Application::STATUS_SUBMITTED],
            'submitted to under review' => [Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW],
            'submitted to rejected' => [Application::STATUS_SUBMITTED, Application::STATUS_REJECTED],
            'under review to for correction' => [Application::STATUS_UNDER_REVIEW, Application::STATUS_FOR_CORRECTION],
            'under review to approved' => [Application::STATUS_UNDER_REVIEW, Application::STATUS_APPROVED_FOR_PAYMENT],
            'under review to rejected' => [Application::STATUS_UNDER_REVIEW, Application::STATUS_REJECTED],
            'for correction to submitted' => [Application::STATUS_FOR_CORRECTION, Application::STATUS_SUBMITTED],
            'approved to payment pending' => [Application::STATUS_APPROVED_FOR_PAYMENT, Application::STATUS_PAYMENT_PENDING],
            'payment pending back to approved' => [Application::STATUS_PAYMENT_PENDING, Application::STATUS_APPROVED_FOR_PAYMENT],
            'payment pending to verified' => [Application::STATUS_PAYMENT_PENDING, Application::STATUS_PAYMENT_VERIFIED],
            'verified to released' => [Application::STATUS_PAYMENT_VERIFIED, Application::STATUS_PERMIT_RELEASED],
        ];
    }

    #[DataProvider('legalTransitions')]
    public function test_legal_transitions_succeed(string $from, string $to): void
    {
        Event::fake();
        $application = $this->makeApplication($from);
        $actor = $this->staff();

        $workflow = app(ApplicationWorkflowService::class);
        $result = $workflow->transition($application, $to, $actor);

        $this->assertSame($to, $result->status);
        $this->assertSame($to, $application->fresh()->status);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function illegalTransitions(): array
    {
        return [
            'draft to approved' => [Application::STATUS_DRAFT, Application::STATUS_APPROVED_FOR_PAYMENT],
            'draft to released' => [Application::STATUS_DRAFT, Application::STATUS_PERMIT_RELEASED],
            'submitted to approved' => [Application::STATUS_SUBMITTED, Application::STATUS_APPROVED_FOR_PAYMENT],
            'approved to verified' => [Application::STATUS_APPROVED_FOR_PAYMENT, Application::STATUS_PAYMENT_VERIFIED],
            'approved to released' => [Application::STATUS_APPROVED_FOR_PAYMENT, Application::STATUS_PERMIT_RELEASED],
            'verified to approved' => [Application::STATUS_PAYMENT_VERIFIED, Application::STATUS_APPROVED_FOR_PAYMENT],
            'released is terminal' => [Application::STATUS_PERMIT_RELEASED, Application::STATUS_SUBMITTED],
            'rejected is terminal' => [Application::STATUS_REJECTED, Application::STATUS_SUBMITTED],
        ];
    }

    #[DataProvider('illegalTransitions')]
    public function test_illegal_transitions_throw(string $from, string $to): void
    {
        $application = $this->makeApplication($from);

        $this->expectException(InvalidStatusTransitionException::class);

        app(ApplicationWorkflowService::class)->transition($application, $to);
    }

    public function test_transition_creates_a_status_history_row(): void
    {
        Event::fake();
        $application = $this->makeApplication(Application::STATUS_SUBMITTED);
        $actor = $this->staff();

        app(ApplicationWorkflowService::class)
            ->transition($application, Application::STATUS_UNDER_REVIEW, $actor, 'Reviewing now');

        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $application->getKey(),
            'from_status' => Application::STATUS_SUBMITTED,
            'to_status' => Application::STATUS_UNDER_REVIEW,
            'actor_id' => $actor->getKey(),
            'actor_role' => User::ROLE_BPLO,
            'note' => 'Reviewing now',
        ]);
    }

    public function test_transition_creates_an_audit_log_row(): void
    {
        Event::fake();
        $application = $this->makeApplication(Application::STATUS_SUBMITTED);
        $actor = $this->staff();

        app(ApplicationWorkflowService::class)
            ->transition($application, Application::STATUS_UNDER_REVIEW, $actor);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.status_changed',
            'subject_type' => Application::class,
            'subject_id' => $application->getKey(),
            'actor_id' => $actor->getKey(),
        ]);
    }

    public function test_return_for_correction_requires_and_stores_a_remark(): void
    {
        Event::fake();
        $application = $this->makeApplication(Application::STATUS_UNDER_REVIEW);
        $actor = $this->staff();

        app(ApplicationWorkflowService::class)
            ->returnForCorrection($application, $actor, 'Please fix the DTI number.');

        $this->assertSame(Application::STATUS_FOR_CORRECTION, $application->fresh()->status);
        $this->assertDatabaseHas('application_remarks', [
            'application_id' => $application->getKey(),
            'type' => 'CORRECTION',
            'body' => 'Please fix the DTI number.',
        ]);
    }

    public function test_reject_stores_a_rejection_remark(): void
    {
        Event::fake();
        $application = $this->makeApplication(Application::STATUS_UNDER_REVIEW);
        $actor = $this->staff();

        app(ApplicationWorkflowService::class)
            ->reject($application, $actor, 'Incomplete requirements.');

        $this->assertSame(Application::STATUS_REJECTED, $application->fresh()->status);
        $this->assertDatabaseHas('application_remarks', [
            'application_id' => $application->getKey(),
            'type' => 'REJECTION',
            'body' => 'Incomplete requirements.',
        ]);
    }
}
