<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessProfileRequest;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessProfileController extends Controller
{
    public function edit(Request $request): View
    {
        // One business per user (effective): always target the earliest row.
        $business = $request->user()->businesses()->oldest()->first();

        return view('applicant.business-profile', [
            'business' => $business,
        ]);
    }

    public function update(BusinessProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $business = $user->businesses()->oldest()->first() ?? new Business;
        $business->fill($request->validated());
        // user_id is set via the relationship, never mass-assigned.
        $business->user()->associate($user);
        $business->save();

        return redirect()
            ->route('business-profile.edit')
            ->with('status', 'Business profile saved.');
    }
}
