<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\PermitService;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PermitController extends Controller
{
    public function show(Application $application): View
    {
        $this->authorize('view', $application);

        $permit = $application->permit;
        abort_if($permit === null, 404, 'No permit has been released for this application yet.');

        $this->authorize('download', $permit);

        $verifyUrl = rtrim((string) config('app.url'), '/').'/verify/'.$permit->verification_token;

        // Inline SVG QR (rendered directly by simple-qrcode, not a data-URI).
        $qrSvg = (string) QrCode::format('svg')->size(220)->generate($verifyUrl);

        return view('permits.show', [
            'permit' => $permit,
            'application' => $application,
            'qrSvg' => $qrSvg,
            'verifyUrl' => $verifyUrl,
        ]);
    }

    public function download(Application $application, PermitService $permits): StreamedResponse
    {
        $this->authorize('view', $application);

        $permit = $application->permit;
        abort_if($permit === null, 404);

        $this->authorize('download', $permit);

        // Regenerate the PDF on the fly if it was never cached.
        if ($permit->pdf_path === null || ! Storage::disk('local')->exists($permit->pdf_path)) {
            $permit->pdf_path = $permits->renderPdf($permit);
            $permit->save();
        }

        return Storage::disk('local')->download(
            $permit->pdf_path,
            $permit->permit_number.'.pdf',
        );
    }
}
