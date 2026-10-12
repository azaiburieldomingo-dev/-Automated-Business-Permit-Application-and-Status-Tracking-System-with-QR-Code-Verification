<?php

namespace App\Http\Controllers\PublicPages;

use App\Http\Controllers\Controller;
use App\Models\Permit;
use App\Models\QrVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VerificationController extends Controller
{
    /**
     * Manual verification: accepts the code or the full verification link
     * printed on the permit, then redirects to the token page. With no input,
     * shows the entry form.
     */
    public function lookup(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
        ]);

        $code = trim((string) ($validated['code'] ?? ''));

        if ($code === '') {
            return view('public.verify-lookup');
        }

        // Accept a pasted link like https://host/verify/{token} or the bare token.
        $token = Str::afterLast(rtrim($code, '/'), '/verify/');
        $token = preg_replace('/[^A-Za-z0-9]/', '', $token) ?? '';

        if ($token === '') {
            return view('public.verify-lookup', ['invalid' => true]);
        }

        return redirect()->route('verify.show', ['token' => $token]);
    }

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
