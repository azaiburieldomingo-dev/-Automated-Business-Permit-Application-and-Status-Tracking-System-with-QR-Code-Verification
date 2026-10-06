<?php

namespace App\Services;

use App\Contracts\SmsChannel;
use App\Models\Application;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create an in-app notification. If the user has a contact number and an SMS
     * driver is configured, also send via the resolved SmsChannel inline.
     */
    public function notify(User $user, string $type, string $title, string $body, ?Application $application = null): void
    {
        $notification = Notification::create([
            'user_id' => $user->getKey(),
            'application_id' => $application?->getKey(),
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'channel' => 'in_app',
        ]);

        $driver = config('permit.sms.driver');

        if ($user->contact_number && $driver !== null) {
            try {
                $result = app(SmsChannel::class)->send($user->contact_number, $title.': '.$body);
                $notification->sms_status = $result['status'] ?? 'sent';
            } catch (\Throwable $e) {
                Log::warning('SMS send failed; in-app notification kept.', [
                    'user_id' => $user->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $notification->sms_status = 'failed';
            }

            $notification->save();
        }
    }
}
