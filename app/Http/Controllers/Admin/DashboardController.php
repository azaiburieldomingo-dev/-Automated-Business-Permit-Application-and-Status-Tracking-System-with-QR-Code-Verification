<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now()->startOfDay();

        $counts = [
            'users' => User::count(),
            'applications' => Application::count(),
            'permits' => Permit::count(),
            'payments' => Payment::count(),
            'active_permits' => Permit::where('status', Permit::STATUS_ACTIVE)
                ->whereDate('valid_until', '>=', $today)
                ->count(),
            'expired_permits' => Permit::where('status', Permit::STATUS_ACTIVE)
                ->whereDate('valid_until', '<', $today)
                ->count(),
        ];

        $recentActivities = AuditLog::with('actor')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.dashboard', [
            'counts' => $counts,
            'recentActivities' => $recentActivities,
        ]);
    }
}
