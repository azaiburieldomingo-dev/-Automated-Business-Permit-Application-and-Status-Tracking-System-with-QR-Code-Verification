<?php

// Temporary live end-to-end check against the dev database. Delete after use.
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\FeeComputationService;
use App\Services\PaymentService;
use App\Services\PermitService;
use Illuminate\Contracts\Console\Kernel;

$applicant = User::where('email', 'applicant@example.com')->firstOrFail();
$bplo = User::where('email', 'bplo@bansud.gov.ph')->firstOrFail();

$a = new Application([
    'applicant_name' => 'Juan Dela Cruz',
    'contact_number' => '09170000001',
    'email' => $applicant->email,
    'business_name' => 'Bansud Sari-Sari Store',
    'business_address' => 'Poblacion, Bansud, Oriental Mindoro',
    'business_nature' => 'Retail',
    'ownership_type' => 'Sole Proprietorship',
    'barangay' => 'Poblacion',
    'number_of_employees' => 2,
]);
$a->user()->associate($applicant);
$a->status = Application::STATUS_DRAFT;
$a->save();

$wf = app(ApplicationWorkflowService::class);
$wf->submit($a, $applicant);
echo "Submitted: {$a->fresh()->tracking_number} [{$a->fresh()->status}]\n";

$wf->beginReview($a->fresh(), $bplo);
$wf->transition($a->fresh(), Application::STATUS_APPROVED_FOR_PAYMENT, $bplo, 'Complete requirements.');
echo 'Approved: '.$a->fresh()->status."\n";

$fees = app(FeeComputationService::class)->computeFor($a->fresh());
echo 'Fee total: '.$fees->total."\n";

$pay = app(PaymentService::class)->startPayment($a->fresh(), 'gcash', $applicant);
echo "Payment started: {$pay->status} / {$pay->verification_status} / app={$a->fresh()->status}\n";

try {
    app(PermitService::class)->release($a->fresh(), $bplo);
    echo "ERROR: permit released before verification!\n";
} catch (Throwable $e) {
    echo 'Release blocked before verification: OK ('.class_basename($e).")\n";
}

app(PaymentService::class)->verify($pay->fresh(), [], $bplo);
echo "Payment verified: {$pay->fresh()->status} / {$pay->fresh()->verification_status} / app={$a->fresh()->status}\n";

$permit = app(PermitService::class)->release($a->fresh(), $bplo);
echo "Permit: {$permit->permit_number} valid until {$permit->valid_until} / app={$a->fresh()->status}\n";
echo "TOKEN={$permit->verification_token}\n";
echo "PDF={$permit->pdf_path}\n";
