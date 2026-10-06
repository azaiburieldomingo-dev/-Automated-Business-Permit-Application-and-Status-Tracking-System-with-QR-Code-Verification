<?php

namespace App\Http\Controllers\Bplo;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $counts = [
            'total' => Application::count(),
            'pending_review' => Application::where('status', Application::STATUS_SUBMITTED)->count(),
            'for_correction' => Application::where('status', Application::STATUS_FOR_CORRECTION)->count(),
            'approved' => Application::where('status', Application::STATUS_APPROVED_FOR_PAYMENT)->count(),
            'awaiting_payment' => Application::where('status', Application::STATUS_PAYMENT_PENDING)->count(),
            'paid' => Application::where('status', Application::STATUS_PAYMENT_VERIFIED)->count(),
            'released' => Application::where('status', Application::STATUS_PERMIT_RELEASED)->count(),
        ];

        $recent = Application::whereNotNull('tracking_number')
            ->latest('submitted_at')
            ->limit(10)
            ->get();

        return view('bplo.dashboard', [
            'counts' => $counts,
            'recent' => $recent,
        ]);
    }
}
