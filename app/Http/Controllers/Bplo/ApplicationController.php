<?php

namespace App\Http\Controllers\Bplo;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewActionRequest;
use App\Models\Application;
use App\Models\ApplicationRequirement;
use App\Services\ApplicationWorkflowService;
use App\Services\FeeComputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('review', Application::class);

        $sortable = ['created_at', 'submitted_at', 'status', 'business_name', 'applicant_name', 'tracking_number'];
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $applications = Application::query()
            ->whereNotNull('tracking_number')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->query('q').'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('tracking_number', 'like', $term)
                        ->orWhere('applicant_name', 'like', $term)
                        ->orWhere('business_name', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('submitted_at', $request->query('date')))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('bplo.index', [
            'applications' => $applications,
            'statuses' => $this->statusOptions(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function show(Application $application, FeeComputationService $fees): View
    {
        $this->authorize('review', $application);

        $application->load([
            'requirements.requirementType',
            'statusHistories' => fn ($q) => $q->orderByDesc('created_at'),
            'remarks.author',
            'payments.transactions',
            'permit',
            'user',
        ]);

        // Show the fees the applicant is being asked to pay while payment is open.
        $breakdown = in_array($application->status, [
            Application::STATUS_APPROVED_FOR_PAYMENT,
            Application::STATUS_PAYMENT_PENDING,
        ], true) ? $fees->computeFor($application) : null;

        return view('bplo.show', [
            'application' => $application,
            'breakdown' => $breakdown,
        ]);
    }

    public function beginReview(Application $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('review', $application);

        return $this->runTransition(
            fn () => $workflow->beginReview($application, request()->user()),
            $application,
            'Application moved to Under Review.',
        );
    }

    public function approve(Application $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('review', $application);

        return $this->runTransition(
            fn () => $workflow->approveForPayment($application, request()->user()),
            $application,
            'Application approved. The applicant can now pay the fees.',
        );
    }

    public function returnForCorrection(ReviewActionRequest $request, Application $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('review', $application);

        return $this->runTransition(
            fn () => $workflow->returnForCorrection($application, $request->user(), $request->validated()['remark']),
            $application,
            'Application returned to the applicant for correction.',
        );
    }

    public function reject(ReviewActionRequest $request, Application $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('review', $application);

        return $this->runTransition(
            fn () => $workflow->reject($application, $request->user(), $request->validated()['remark']),
            $application,
            'Application rejected.',
        );
    }

    public function flagRequirement(Request $request, Application $application, ApplicationRequirement $req): RedirectResponse
    {
        $this->authorize('review', $application);

        abort_unless($req->application_id === $application->getKey(), 404);

        $validated = $request->validate([
            'checklist_status' => ['required', 'in:COMPLETE,MISSING,FOR_CORRECTION'],
            'correction_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $req->checklist_status = $validated['checklist_status'];
        $req->correction_note = $validated['correction_note'] ?? null;
        $req->save();

        return redirect()
            ->route('bplo.applications.show', $application)
            ->with('success', 'Requirement checklist updated.');
    }

    public function file(ApplicationRequirement $applicationRequirement): StreamedResponse
    {
        $this->authorize('review', $applicationRequirement->application);

        abort_if($applicationRequirement->stored_path === null, 404);

        return Storage::disk('local')->download(
            $applicationRequirement->stored_path,
            $applicationRequirement->original_filename,
        );
    }

    private function runTransition(callable $action, Application $application, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (\Throwable $e) {
            return redirect()
                ->route('bplo.applications.show', $application)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('bplo.applications.show', $application)
            ->with('success', $message);
    }

    /**
     * @return array<int, string>
     */
    private function statusOptions(): array
    {
        return [
            Application::STATUS_SUBMITTED,
            Application::STATUS_UNDER_REVIEW,
            Application::STATUS_FOR_CORRECTION,
            Application::STATUS_APPROVED_FOR_PAYMENT,
            Application::STATUS_PAYMENT_PENDING,
            Application::STATUS_PAYMENT_VERIFIED,
            Application::STATUS_PERMIT_RELEASED,
            Application::STATUS_REJECTED,
        ];
    }
}
