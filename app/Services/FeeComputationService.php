<?php

namespace App\Services;

use App\Models\Application;
use App\Models\FeeRate;
use App\Support\FeeBreakdown;
use Illuminate\Support\Facades\Log;

class FeeComputationService
{
    /**
     * Compute the fee breakdown for an application from the active fee rates.
     *
     * Reads active fee_rates joined to active fee_types ordered by sort_order.
     * Amounts are data, never hard-coded. An empty rate table yields a zero
     * total and a logged warning.
     */
    public function computeFor(Application $application): FeeBreakdown
    {
        $rows = FeeRate::query()
            ->join('fee_types', 'fee_rates.fee_type_id', '=', 'fee_types.id')
            ->where('fee_rates.is_active', true)
            ->where('fee_types.is_active', true)
            ->orderBy('fee_types.sort_order')
            ->orderBy('fee_types.id')
            ->get([
                'fee_types.code as code',
                'fee_types.name as name',
                'fee_rates.amount as amount',
            ]);

        if ($rows->isEmpty()) {
            Log::warning('FeeComputationService: no active fee rates found; total will be zero.', [
                'application_id' => $application->getKey(),
            ]);

            return new FeeBreakdown([], 0.0);
        }

        $lines = $rows->map(fn ($row): array => [
            'code' => $row->code,
            'name' => $row->name,
            'amount' => (float) $row->amount,
        ])->all();

        return FeeBreakdown::fromLines($lines);
    }
}
