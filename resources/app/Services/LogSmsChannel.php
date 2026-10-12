<?php

namespace App\Services;

use App\Contracts\SmsChannel;
use Illuminate\Support\Facades\Log;

class LogSmsChannel implements SmsChannel
{
    public function send(string $to, string $message): array
    {
        Log::info('SMS (log driver)', [
            'to' => $to,
            'message' => $message,
        ]);

        return ['status' => 'logged'];
    }
}
