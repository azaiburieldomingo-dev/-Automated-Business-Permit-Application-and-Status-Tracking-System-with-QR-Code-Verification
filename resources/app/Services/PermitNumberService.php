<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class PermitNumberService
{
    private const SCOPE = 'permit';

    /**
     * Generate the next per-year permit number, e.g. BP-2025-000001.
     *
     * Same atomic-increment + UNIQUE-index + bounded-retry pattern as
     * TrackingNumberService, scoped to 'permit'.
     */
    public function generate(int $year): string
    {
        $prefix = config('permit.permit_prefix');

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
