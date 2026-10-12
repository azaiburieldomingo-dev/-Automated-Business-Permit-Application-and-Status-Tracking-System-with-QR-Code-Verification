<?php

namespace App\Contracts;

interface SmsChannel
{
    /**
     * Send an SMS message.
     *
     * @return array<string, mixed>
     */
    public function send(string $to, string $message): array;
}
