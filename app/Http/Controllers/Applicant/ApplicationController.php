<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\ApplicationRequirement;
use App\Models\Business;
use App\Models\RequirementType;
use App\Services\ApplicationWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function create(Request $request): View
    {
        $u = $request->user();
        $b = $u->businesses()->oldest()->first(); // one-per-user: oldest row

        // Prefill uses APPLICATION field names so _form-fields binds correctly.
        $prefill = $b ? (object) [
            'applicant_name' => $u->name,
            'contact_number' => $u->contact_number,
            'email' => $u->email,
            'business_name' => $b->name,
            'business_address' => $b->address,
            'barangay' => $b->barangay,
            'business_nature' => $b->nature,
            'ownership_type' => $b->ownership_type,
            'dti_sec_type' => $b->dti_sec_type,
            'dti_sec_number' => $b->dti_sec_number,
            'number_of_employees' => $b->number_of_employees ?? 0,
        ] : null;

        return view('applicant.create', [
            'requirementTypes' => RequirementType::where('is_active', true)->orderBy('sort_order')->get(),
            'application' => $prefill,
        ]);
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $application = new Application($data);
        $application->user()->associate($user);
        $application->status = Application::STATUS_DRAFT;

        // Best-effort: link the application to the user's business, upserting one
        // from this first application when the user has none. Never overwrite an
        // existing business from an application. A failure here must NOT block the
        // application being created, so the save() below always runs last.
        try {
            $business = $user->businesses()->oldest()->first();
            if (! $business) {
                $business = new Business();
                $business->fill([
                    'name' => $data['business_name'],
                    'address' => $data['business_address'],
                    'barangay' => $data['barangay'],
                    'nature' => $data['business_nature'],
                    'ownership_type' => $data['ownership_type'],
                    'dti_sec_type' => $data['dti_sec_type'] ?? null,
                    'dti_sec_number' => $data['dti_sec_number'] ?? null,
                    'number_of_employees' => $data['number_of_employees'] ?? 0,
                ]);
                $business->user()->associate($user); // user_id via associate, never mass-assigned
                $business->save();
            }
            $application->business()->associate($business);
        } catch (\Throwable $e) {
            report($e);
        }

        $application->save(); // ALWAYS runs last; business_id stays null if the upsert threw

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
