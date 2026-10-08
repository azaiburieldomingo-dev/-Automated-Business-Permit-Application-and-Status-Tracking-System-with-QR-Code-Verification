<?php

namespace App\Http\Controllers\Inspector;

use App\Http\Controllers\Controller;
use App\Models\Permit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermitController extends Controller
{
    public function index(Request $request): View
    {
        $permits = Permit::query()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->query('q').'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('permit_number', 'like', $term)
                        ->orWhere('business_name', 'like', $term)
                        ->orWhere('business_address', 'like', $term);
                });
            })
            ->latest('issued_at')
            ->paginate(15)
            ->withQueryString();

        return view('inspector.index', [
            'permits' => $permits,
        ]);
    }

    public function show(Permit $permit): View
    {
        $permit->load(['qrVerifications' => fn ($q) => $q->latest('created_at')->limit(50)]);

        return view('inspector.show', [
            'permit' => $permit,
        ]);
    }
}
