<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Services\LogSmsChannel;
use Tests\TestCase;

class SandboxGatewayTest extends TestCase
{
    public function test_log_sms_channel_returns_logged(): void
    {
        $channel = new LogSmsChannel;

        $result = $channel->send('09171234567', 'Hello');

        $this->assertSame('logged', $result['status']);
    }

    public function test_payment_status_constants_are_distinct(): void
    {
        $this->assertNotSame(Payment::STATUS_PAID, Payment::STATUS_PROCESSING);
        $this->assertSame('PAID', Payment::STATUS_PAID);
        $this->assertSame('PROCESSING', Payment::STATUS_PROCESSING);
    }
}
