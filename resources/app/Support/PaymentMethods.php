<?php

namespace App\Support;

/**
 * Single source for which payment methods applicants may use, read from
 * config('permit.payment'). Shared by the Pay Fees page, its validation, and
 * the payment service so they never disagree.
 */
class PaymentMethods
{
    public static function isManual(): bool
    {
        return config('permit.payment.mode') === 'manual';
    }

    /**
     * Every enabled method with its receiving account and availability.
     *
     * @return array<string, array{key: string, label: string, number: ?string, name: ?string, available: bool}>
     */
    public static function all(): array
    {
        $methods = [];

        foreach ((array) config('permit.payment.methods') as $key => $enabled) {
            if (! $enabled) {
                continue;
            }

            $account = (array) config("permit.payment.accounts.{$key}", []);
            $number = trim((string) ($account['number'] ?? '')) ?: null;

            $methods[$key] = [
                'key' => $key,
                'label' => $account['label'] ?? strtoupper($key),
                'number' => $number,
                'name' => trim((string) ($account['name'] ?? '')) ?: null,
                // Manual payments need somewhere to send the money.
                'available' => ! self::isManual() || $number !== null,
            ];
        }

        return $methods;
    }

    /**
     * Keys of methods an applicant can actually pay with right now.
     *
     * @return array<int, string>
     */
    public static function availableKeys(): array
    {
        return array_keys(array_filter(self::all(), fn (array $m): bool => $m['available']));
    }

    public static function label(?string $key): string
    {
        return self::all()[$key]['label']
            ?? (string) config("permit.payment.accounts.{$key}.label", strtoupper((string) $key));
    }
}
