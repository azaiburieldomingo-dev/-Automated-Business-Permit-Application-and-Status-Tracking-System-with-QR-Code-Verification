<?php

namespace Tests\Feature\Notification;

use App\Models\Application;
use App\Models\Notification;
use App\Services\ApplicationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesDomainData;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use MakesDomainData, RefreshDatabase;

    public function test_notification_row_is_created_synchronously_on_submit(): void
    {
        // Prove no queue worker is involved: the DB queue is selected but the row
        // appears inline during the request.
        config(['queue.default' => 'database']);

        $applicant = $this->makeApplicant();
        $application = $this->makeApplication($applicant, Application::STATUS_DRAFT);

        $this->assertDatabaseCount('notifications', 0);
        // Jobs table stays empty — nothing is queued.
        $this->assertDatabaseCount('jobs', 0);

        app(ApplicationWorkflowService::class)->submit($application, $applicant);

        // The notification exists immediately — no queue:work needed.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $applicant->getKey(),
            'application_id' => $application->getKey(),
            'channel' => 'in_app',
            'type' => 'application.submitted',
        ]);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_each_status_change_produces_an_in_app_notification(): void
    {
        config(['queue.default' => 'database']);

        $applicant = $this->makeApplicant();
        $bplo = $this->makeBplo();
        $application = $this->makeApplication($applicant, Application::STATUS_SUBMITTED);

        $workflow = app(ApplicationWorkflowService::class);
        $workflow->beginReview($application, $bplo);
        $workflow->approveForPayment($application->fresh(), $bplo);

        // Both transitions notified the applicant inline.
        $this->assertSame(
            2,
            Notification::where('user_id', $applicant->getKey())->count(),
        );
    }
}
