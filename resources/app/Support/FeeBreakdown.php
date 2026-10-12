<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Lightweight DTO describing a computed fee breakdown.
 *
 * @implements Arrayable<string, mixed>
 */
class FeeBreakdown implements Arrayable
{
    /**
     * @param  array<int, array{code: string, name: string, amount: float}>  $lines
     */
    public function __construct(
        public array $lines = [],
        public float $total = 0.0,
    ) {}

    /**
     * Build a breakdown from raw line items, summing the total.
     *
     * @param  array<int, array{code: string, name: string, amount: float|int|string}>  $lines
     */
    public static function fromLines(array $lines): self
    {
        $normalized = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $amount = (float) $line['amount'];
            $normalized[] = [
                'code' => $line['code'],
                'name' => $line['name'],
                'amount' => $amount,
            ];
            $total += $amount;
        }

        return new self($normalized, round($total, 2));
    }

    /**
     * @return array{lines: array<int, array{code: string, name: string, amount: float}>, total: float}
     */
    public function toArray(): array
    {
        return [
            'lines' => $this->lines,
            'total' => $this->total,
        ];
    }
}
