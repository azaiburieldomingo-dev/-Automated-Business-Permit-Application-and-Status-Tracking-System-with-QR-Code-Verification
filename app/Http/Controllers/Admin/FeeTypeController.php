<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeeTypeRequest;
use App\Models\FeeType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FeeTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.fee-types.index', [
            'feeTypes' => FeeType::orderBy('sort_order')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.fee-types.create');
    }

    public function store(FeeTypeRequest $request): RedirectResponse
    {
        FeeType::create($this->normalized($request));

        return redirect()->route('admin.fee-types.index')->with('success', 'Fee type created.');
    }

    public function edit(FeeType $feeType): View
    {
        return view('admin.fee-types.edit', ['feeType' => $feeType]);
    }

    public function update(FeeTypeRequest $request, FeeType $feeType): RedirectResponse
    {
        $feeType->update($this->normalized($request));

        return redirect()->route('admin.fee-types.index')->with('success', 'Fee type updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalized(FeeTypeRequest $request): array
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
