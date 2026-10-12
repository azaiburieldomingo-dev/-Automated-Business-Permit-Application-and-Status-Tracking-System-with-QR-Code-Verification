<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RequirementTypeRequest;
use App\Models\RequirementType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RequirementTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.requirement-types.index', [
            'types' => RequirementType::orderBy('sort_order')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.requirement-types.create');
    }

    public function store(RequirementTypeRequest $request): RedirectResponse
    {
        RequirementType::create($this->normalized($request));

        return redirect()->route('admin.requirement-types.index')->with('success', 'Requirement type created.');
    }

    public function edit(RequirementType $requirementType): View
    {
        return view('admin.requirement-types.edit', ['type' => $requirementType]);
    }

    public function update(RequirementTypeRequest $request, RequirementType $requirementType): RedirectResponse
    {
        $requirementType->update($this->normalized($request));

        return redirect()->route('admin.requirement-types.index')->with('success', 'Requirement type updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalized(RequirementTypeRequest $request): array
    {
        $data = $request->validated();
        $data['is_required'] = (bool) ($data['is_required'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
