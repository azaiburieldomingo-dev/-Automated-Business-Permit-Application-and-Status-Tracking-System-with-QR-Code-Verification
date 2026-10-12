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

        $verifyUrl = $permit->verifyUrl();

        // Inline SVG QR (rendered directly by simple-qrcode, not a data-URI).
        $qrSvg = (string) QrCode::format('svg')->size(220)->margin(1)->generate($verifyUrl);

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

        // Always render fresh so the QR uses the current public address (a
        // cached PDF could still point at an old host such as localhost).
        $path = $permits->renderPdf($permit);
        if ($permit->pdf_path !== $path) {
            $permit->pdf_path = $path;
            $permit->save();
        }

        return Storage::disk('local')->download(
            $permit->pdf_path,
            $permit->permit_number.'.pdf',
        );
    }
}
