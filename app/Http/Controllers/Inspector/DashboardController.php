<?php

namespace App\Http\Controllers\Inspector;

use App\Http\Controllers\Controller;
use App\Models\Permit;
use App\Models\QrVerification;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $active = Permit::query()
            ->where('status', Permit::STATUS_ACTIVE)
            ->where(function ($q): void {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', today());
            })
            ->count();

        $expired = Permit::query()
            ->where('status', Permit::STATUS_ACTIVE)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', today())
            ->count();

        $revoked = Permit::query()
            ->where('status', Permit::STATUS_REVOKED)
            ->count();

        $scansToday = QrVerification::query()
            ->whereDate('created_at', today())
            ->count();

        $counts = [
            'active' => $active,
            'expired' => $expired,
            'revoked' => $revoked,
            'scans_today' => $scansToday,
        ];

        $recentScans = QrVerification::query()
            ->with('permit')
            ->latest('created_at')
            ->limit(15)
            ->get();

        return view('inspector.dashboard', [
            'counts' => $counts,
            'recentScans' => $recentScans,
        ]);
    }
}
