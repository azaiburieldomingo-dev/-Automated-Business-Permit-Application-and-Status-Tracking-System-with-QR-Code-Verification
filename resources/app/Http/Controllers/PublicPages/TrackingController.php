<?php

namespace App\Http\Controllers\PublicPages;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackingLookupRequest;
use App\Models\Application;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TrackingController extends Controller
{
    public function show(): View
    {
        return view('public.track', ['application' => null]);
    }

    public function lookup(TrackingLookupRequest $request): View
    {
        $data = $request->validated();
        $verifier = trim($data['verifier']);

        $application = Application::query()
            ->where('tracking_number', $data['tracking_number'])
            ->first();

        // Second factor: match email (exact, case-insensitive) OR applicant last name.
        if ($application !== null && ! $this->verifierMatches($application, $verifier)) {
            $application = null;
        }

        if ($application !== null) {
            $application->load([
                'statusHistories' => fn ($q) => $q->orderByDesc('created_at'),
                'remarks',
                'requirements.requirementType',
                'payments',
                'permit',
            ]);
        }

        return view('public.track', [
            'application' => $application,
            'notFound' => $application === null,
            'tracking_number' => $data['tracking_number'],
        ]);
    }

    private function verifierMatches(Application $application, string $verifier): bool
    {
        if (Str::lower($application->email) === Str::lower($verifier)) {
            return true;
        }

        $parts = preg_split('/\s+/', trim((string) $application->applicant_name)) ?: [];
        $lastName = end($parts) ?: '';

        return $lastName !== '' && Str::lower($lastName) === Str::lower($verifier);
    }
}
