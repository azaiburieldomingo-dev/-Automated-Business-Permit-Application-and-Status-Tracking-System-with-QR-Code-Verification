<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => $this->roles(),
        ]);
    }

    public function store(UserRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();

        $user = new User;
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'contact_number' => $data['contact_number'] ?? null,
            'address' => $data['address'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        // role is set explicitly in server code (never mass-assignable).
        $user->role = $data['role'];
        $user->save();

        $auditLogger->log('user.created', $user, ['role' => $user->role], $request->user());

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->roles(),
        ]);
    }

    public function update(UserRequest $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();

        // An admin cannot deactivate or demote their own account.
        $isSelf = $request->user()->getKey() === $user->getKey();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'contact_number' => $data['contact_number'] ?? null,
            'address' => $data['address'] ?? null,
            'is_active' => $isSelf ? true : (bool) ($data['is_active'] ?? false),
        ]);

        if (! $isSelf) {
            $user->role = $data['role'];
        }

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        $auditLogger->log('user.updated', $user, ['role' => $user->role], $request->user());

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    /**
     * @return array<string, string>
     */
    private function roles(): array
    {
        return [
            User::ROLE_APPLICANT => 'Applicant',
            User::ROLE_BPLO => 'BPLO',
            User::ROLE_TREASURER => 'Treasurer',
            User::ROLE_INSPECTOR => 'Inspector',
            User::ROLE_ADMIN => 'Admin',
        ];
    }
}
