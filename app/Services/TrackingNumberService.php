<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class TrackingNumberService
{
    private const SCOPE = 'tracking';

    /**
     * Generate the next per-year tracking number, e.g. BANSUD-2025-000001.
     *
     * Uses an atomic UPDATE on number_sequences inside a transaction. SQLite
     * ignores FOR UPDATE, so correctness rests on the atomic increment plus the
     * UNIQUE index on applications.tracking_number (the caller retries on collision).
     */
    public function generate(int $year): string
    {
        $prefix = config('permit.tracking_prefix');

        $seq = DB::transaction(function () use ($year): int {
            $affected = NumberSequence::query()
                ->where('scope', self::SCOPE)
                ->where('year', $year)
                ->update([
                    'last_value' => DB::raw('last_value + 1'),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                NumberSequence::create([
                    'scope' => self::SCOPE,
                    'year' => $year,
                    'last_value' => 1,
                ]);

                return 1;
            }

            return (int) NumberSequence::query()
                ->where('scope', self::SCOPE)
                ->where('year', $year)
                ->value('last_value');
        });

        return sprintf('%s-%d-%06d', $prefix, $year, $seq);
    }
}
