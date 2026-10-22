<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('applicant.settings', [
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Only these four fields are ever written; role/is_active are never filled.
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'contact_number' => $data['contact_number'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
        $user->save();

        return redirect()
            ->route('settings')
            ->with('status', 'Profile information updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // password cast => 'hashed' hashes on assignment.
        $user->password = $data['password'];
        $user->save();

        $request->session()->regenerate();

        return redirect()
            ->route('settings')
            ->with('status', 'Password changed.');
    }
}
