<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $awaiting = Payment::query()
            ->where('status', Payment::STATUS_PROCESSING)
            ->where('verification_status', Payment::VERIFY_UNVERIFIED)
            ->count();

        $verifiedToday = Payment::query()
            ->where('verification_status', Payment::VERIFY_VERIFIED)
            ->whereDate('verified_at', today())
            ->count();

        $totalVerified = Payment::query()
            ->where('verification_status', Payment::VERIFY_VERIFIED)
            ->count();

        $totalCollected = (float) Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->sum('amount');

        $counts = [
            'awaiting' => $awaiting,
            'verified_today' => $verifiedToday,
            'total_verified' => $totalVerified,
            'total_collected' => $totalCollected,
        ];

        $pending = Payment::query()
            ->with('application')
            ->where('status', Payment::STATUS_PROCESSING)
            ->where('verification_status', Payment::VERIFY_UNVERIFIED)
            ->latest('created_at')
            ->limit(15)
            ->get();

        return view('treasurer.dashboard', [
            'counts' => $counts,
            'pending' => $pending,
        ]);
    }
}
