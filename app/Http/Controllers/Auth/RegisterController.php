<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();

        $user = new User;
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'contact_number' => $data['contact_number'] ?? null,
            'address' => $data['address'] ?? null,
            'is_active' => true,
        ]);
        // role is never mass-assignable; always forced to applicant on registration.
        $user->role = User::ROLE_APPLICANT;
        $user->save();

        $auditLogger->log('auth.registered', $user, [], $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }
}
