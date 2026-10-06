<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeeRateRequest;
use App\Models\FeeRate;
use App\Models\FeeType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FeeRateController extends Controller
{
    public function index(): View
    {
        return view('admin.fee-rates.index', [
            'feeRates' => FeeRate::with('feeType')->orderBy('fee_type_id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.fee-rates.create', [
            'feeTypes' => FeeType::orderBy('sort_order')->get(),
        ]);
    }

    public function store(FeeRateRequest $request): RedirectResponse
    {
        FeeRate::create($this->normalized($request));

        return redirect()->route('admin.fee-rates.index')->with('success', 'Fee rate created.');
    }

    public function edit(FeeRate $feeRate): View
    {
        return view('admin.fee-rates.edit', [
            'feeRate' => $feeRate,
            'feeTypes' => FeeType::orderBy('sort_order')->get(),
        ]);
    }

    public function update(FeeRateRequest $request, FeeRate $feeRate): RedirectResponse
    {
        $feeRate->update($this->normalized($request));

        return redirect()->route('admin.fee-rates.index')->with('success', 'Fee rate updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalized(FeeRateRequest $request): array
    {
        $data = $request->validated();
        $data['calc_type'] = $data['calc_type'] ?? 'fixed';
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
