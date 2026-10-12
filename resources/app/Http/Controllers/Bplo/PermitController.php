<?php

namespace App\Http\Controllers\Bplo;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Permit;
use App\Services\PermitService;
use Illuminate\Http\RedirectResponse;

class PermitController extends Controller
{
    public function release(Application $application, PermitService $permits): RedirectResponse
    {
        $this->authorize('review', $application);

        try {
            $permits->release($application, request()->user());
        } catch (\Throwable $e) {
            return redirect()
                ->route('bplo.applications.show', $application)
                ->with('error', 'Permit release failed: '.$e->getMessage());
        }

        return redirect()
            ->route('bplo.applications.show', $application)
            ->with('success', 'Permit released successfully.');
    }

    public function regenerate(Permit $permit, PermitService $permits): RedirectResponse
    {
        $this->authorize('review', Application::class);

        try {
            $permits->regenerate($permit, request()->user());
        } catch (\Throwable $e) {
            return back()->with('error', 'Permit regeneration failed: '.$e->getMessage());
        }

        return redirect()
            ->route('bplo.applications.show', $permit->application)
            ->with('success', 'Permit regenerated with a fresh verification token.');
    }
}
