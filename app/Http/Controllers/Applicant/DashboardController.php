<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Notification;
use App\Models\Permit;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // /dashboard is the generic "home" URL; staff belong on their own dashboard.
        if (! $user->isApplicant()) {
            return redirect($user->homePath());
        }

        $applications = $user->applications()
            ->with([
                'requirements.requirementType',
                'payments',
                'permit',
                'remarks', // the Action-needed callout reads the latest CORRECTION remark; avoids N+1.
            ])
            ->latest()
            ->get();

        // Status-derived stat counts (single source of truth = applications.status).
        $stats = [
            'total' => $applications->count(),
            'pending' => $applications->whereIn('status', [
                Application::STATUS_SUBMITTED,
                Application::STATUS_UNDER_REVIEW,
            ])->count(),
            'approved' => $applications->where('status', Application::STATUS_PERMIT_RELEASED)->count(),
            'action' => $applications->whereIn('status', [
                Application::STATUS_FOR_CORRECTION,
                Application::STATUS_APPROVED_FOR_PAYMENT,
            ])->count(),

            'released' => $applications->where('status', Application::STATUS_PERMIT_RELEASED)->count(),
            'paymentPending' => $applications->where('status', Application::STATUS_PAYMENT_PENDING)->count(),
            'paymentVerified' => $applications->where('status', Application::STATUS_PAYMENT_VERIFIED)->count(),
            'draft' => $applications->where('status', Application::STATUS_DRAFT)->count(),
            'rejected' => $applications->where('status', Application::STATUS_REJECTED)->count(),
        ];

        // Renewal reminders: read-only over ACTIVE permits' valid_until (Part D).
        $reminderDays = (int) config('permit.renewal.reminder_days', 30);
        $renewalReminders = $applications
            ->filter(fn (Application $application) => ($permit = $application->permit) !== null
                && $permit->status === Permit::STATUS_ACTIVE
                && $permit->valid_until !== null)
            ->map(function (Application $application) {
                $permit = $application->permit;

                // Carbon 3 diffInDays() returns a signed float; floor+cast for a whole-day count.
                $daysLeft = (int) floor(
                    Carbon::now()->startOfDay()->diffInDays($permit->valid_until->copy()->startOfDay(), false)
                );

                return [
                    'application' => $application,
                    'permit' => $permit,
                    'daysLeft' => $daysLeft,
                    'expired' => $daysLeft < 0,
                ];
            })
            ->filter(fn (array $reminder) => $reminder['daysLeft'] <= $reminderDays)
            ->values();

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
            'stats' => $stats,
            'renewalReminders' => $renewalReminders,
        ]);
    }
}
