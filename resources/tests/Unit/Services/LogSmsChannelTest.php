<?php

namespace Tests\Unit\Services;

use App\Services\LogSmsChannel;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogSmsChannelTest extends TestCase
{
    public function test_send_returns_logged_status(): void
    {
        $result = (new LogSmsChannel)->send('09171234567', 'Your permit is ready.');

        $this->assertSame('logged', $result['status']);
    }

    public function test_send_writes_the_message_to_the_log(): void
    {
        Log::spy();

        (new LogSmsChannel)->send('09171234567', 'Your permit is ready.');

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $context['to'] === '09171234567'
                    && $context['message'] === 'Your permit is ready.';
            });
    }
}
