<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Permit;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PermitService
{
    public function __construct(
        private readonly PermitNumberService $permitNumbers,
        private readonly ApplicationWorkflowService $workflow,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Release a permit. Guards PAYMENT_VERIFIED. Generates permit number + token,
     * snapshots business fields, renders the PDF, and transitions to PERMIT_RELEASED.
     */
    public function release(Application $application, ?User $actor = null): Permit
    {
        if ($application->status !== Application::STATUS_PAYMENT_VERIFIED) {
            Log::error('Permit release blocked: application not PAYMENT_VERIFIED.', [
                'application_id' => $application->getKey(),
                'status' => $application->status,
            ]);

            throw new RuntimeException('Permit can only be released once payment is verified.');
        }

        return DB::transaction(function () use ($application, $actor): Permit {
            $issuedAt = now()->startOfDay();
            $validUntil = $issuedAt->copy()->addMonths((int) config('permit.valid_months'));

            $permit = $this->createPermitRecord($application, $actor, $issuedAt, $validUntil);

            $permit->pdf_path = $this->renderPdf($permit);
            $permit->save();

            $this->workflow->transition($application, Application::STATUS_PERMIT_RELEASED, $actor);

            $this->auditLogger->log(
                'permit.released',
                $permit,
                ['permit_number' => $permit->permit_number],
                $actor,
            );

            return $permit;
        });
    }

    /**
     * Render the Mayor's Permit PDF via dompdf and store it on the local disk.
     * Returns the stored path.
     */
    public function renderPdf(Permit $permit): string
    {
        $verifyUrl = rtrim((string) config('app.url'), '/').'/verify/'.$permit->verification_token;

        $pdf = Pdf::loadView('permits.pdf', [
            'permit' => $permit,
            'qrDataUri' => $this->qrDataUri($verifyUrl),
            'verifyUrl' => $verifyUrl,
            'municipality' => config('permit.municipality'),
            'province' => config('permit.province'),
        ]);

        $path = 'permits/'.$permit->getKey().'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Build an SVG base64 data-URI for the QR code of the given URL.
     * SVG only — no PNG/imagick/endroid.
     */
    public function qrDataUri(string $url): string
    {
        $svg = (string) QrCode::format('svg')->size(200)->generate($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Issue a fresh verification token and re-render the PDF. Does not change the
     * permit number. BPLO/admin action, audited.
     */
    public function regenerate(Permit $permit, ?User $actor = null): Permit
    {
        return DB::transaction(function () use ($permit, $actor): Permit {
            $permit->verification_token = $this->uniqueToken();
            $permit->save();

            $permit->pdf_path = $this->renderPdf($permit);
            $permit->save();

            $this->auditLogger->log(
                'permit.regenerated',
                $permit,
                ['permit_number' => $permit->permit_number],
                $actor,
            );

            return $permit;
        });
    }

    private function createPermitRecord(Application $application, ?User $actor, $issuedAt, $validUntil): Permit
    {
        $year = (int) $issuedAt->year;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $permitNumber = $this->permitNumbers->generate($year);

            try {
                return Permit::create([
                    'application_id' => $application->getKey(),
                    'permit_number' => $permitNumber,
                    'verification_token' => $this->uniqueToken(),
                    'business_name' => $application->business_name,
                    'owner_name' => $application->applicant_name,
                    'business_address' => $application->business_address,
                    'business_nature' => $application->business_nature,
                    'issued_at' => $issuedAt,
                    'valid_until' => $validUntil,
                    'status' => Permit::STATUS_ACTIVE,
                    'generated_by' => $actor?->getKey(),
                ]);
            } catch (QueryException $e) {
                Log::warning('Permit number collision; retrying.', [
                    'attempt' => $attempt,
                    'permit_number' => $permitNumber,
                ]);

                if ($attempt === 5) {
                    Log::error('Permit number generation failed after 5 attempts.', [
                        'application_id' => $application->getKey(),
                    ]);

                    throw $e;
                }
            }
        }

        throw new RuntimeException('Unable to generate a unique permit number.');
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (Permit::query()->where('verification_token', $token)->exists());

        return $token;
    }
}
