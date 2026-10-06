<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $applications = $user->applications()
            ->latest()
            ->get();

        $notifications = Notification::where('user_id', $user->getKey())
            ->latest()
            ->limit(20)
            ->get();

        $unreadCount = $notifications->whereNull('read_at')->count();

        // Mark the listed notifications as read on view.
        Notification::where('user_id', $user->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('applicant.dashboard', [
            'applications' => $applications,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }
}
