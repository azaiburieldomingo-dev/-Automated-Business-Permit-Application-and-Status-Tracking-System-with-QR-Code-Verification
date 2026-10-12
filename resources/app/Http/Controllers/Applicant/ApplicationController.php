<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\ApplicationRequirement;
use App\Models\RequirementType;
use App\Services\ApplicationWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function create(): View
    {
        return view('applicant.create', [
            'requirementTypes' => RequirementType::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        $application = new Application($request->validated());
        $application->user()->associate($request->user());
        $application->status = Application::STATUS_DRAFT;
        $application->save();

        return redirect()
            ->route('applications.edit', $application)
            ->with('success', 'Draft application created. Upload your requirements, then review and submit.');
    }

    public function edit(Application $application): View
    {
        $this->authorize('update', $application);

        $application->load('requirements.requirementType');

        return view('applicant.edit', [
            'application' => $application,
            'requirementTypes' => RequirementType::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(ApplicationRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $application->fill($request->validated());
        $application->save();

        return redirect()
            ->route('applications.edit', $application)
            ->with('success', 'Application details saved.');
    }

    public function summary(Application $application): View
    {
        $this->authorize('view', $application);

        $application->load('requirements.requirementType');

        return view('applicant.summary', [
            'application' => $application,
            'requirementTypes' => RequirementType::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function submit(Application $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('submit', $application);

        // Guard: every required requirement slot must have a completed upload.
        $requiredCodes = RequirementType::where('is_active', true)
            ->where('is_required', true)
            ->pluck('id');

        $completed = $application->requirements()
            ->where('checklist_status', ApplicationRequirement::CHECK_COMPLETE)
            ->pluck('requirement_type_id');

        $missing = $requiredCodes->diff($completed);

        if ($missing->isNotEmpty()) {
            return redirect()
                ->route('applications.summary', $application)
                ->with('error', 'Please upload all required documents before submitting.');
        }

        $workflow->submit($application, $application->user);

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Application submitted. Your tracking number is '.$application->tracking_number.'.');
    }

    public function show(Application $application): View
    {
        $this->authorize('view', $application);

        $application->load([
            'requirements.requirementType',
            'statusHistories' => fn ($q) => $q->orderByDesc('created_at'),
            'remarks.author',
            'payments',
            'permit',
        ]);

        return view('applicant.show', [
            'application' => $application,
        ]);
    }
}
