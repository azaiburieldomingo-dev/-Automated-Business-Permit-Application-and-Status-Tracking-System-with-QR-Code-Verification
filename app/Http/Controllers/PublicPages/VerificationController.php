<?php

namespace App\Http\Controllers\PublicPages;

use App\Http\Controllers\Controller;
use App\Models\Permit;
use App\Models\QrVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $permit = Permit::query()
            ->where('verification_token', $token)
            ->first();

        $result = $this->classify($permit);

        QrVerification::create([
            'permit_id' => $permit?->getKey(),
            'token' => $token,
            'result' => $result,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'created_at' => now(),
        ]);

        return view('public.verify', [
            'result' => $result,
            // Data-minimized per RA 10173: only non-sensitive fields are exposed.
            'permit' => $permit,
        ]);
    }

    private function classify(?Permit $permit): string
    {
        if ($permit === null) {
            return 'NOT_FOUND';
        }

        if ($permit->status === Permit::STATUS_REVOKED) {
            return 'REVOKED';
        }

        if ($permit->valid_until !== null && $permit->valid_until->isPast()) {
            return 'EXPIRED';
        }

        return 'VALID';
    }
}
