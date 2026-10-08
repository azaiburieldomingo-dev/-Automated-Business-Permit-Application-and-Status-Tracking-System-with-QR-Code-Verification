<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeeRateController;
use App\Http\Controllers\Admin\FeeTypeController;
use App\Http\Controllers\Admin\RequirementTypeController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Applicant\ApplicationController as ApplicantApplicationController;
use App\Http\Controllers\Applicant\DashboardController as ApplicantDashboardController;
use App\Http\Controllers\Applicant\PaymentController as ApplicantPaymentController;
use App\Http\Controllers\Applicant\PermitController as ApplicantPermitController;
use App\Http\Controllers\Applicant\RequirementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Bplo\ApplicationController as BploApplicationController;
use App\Http\Controllers\Bplo\DashboardController as BploDashboardController;
use App\Http\Controllers\Bplo\PaymentController as BploPaymentController;
use App\Http\Controllers\Bplo\PermitController as BploPermitController;
use App\Http\Controllers\Inspector\DashboardController as InspectorDashboardController;
use App\Http\Controllers\Inspector\PermitController as InspectorPermitController;
use App\Http\Controllers\PublicPages\LandingController;
use App\Http\Controllers\PublicPages\SandboxWebhookController;
use App\Http\Controllers\PublicPages\TrackingController;
use App\Http\Controllers\PublicPages\VerificationController;
use App\Http\Controllers\Treasurer\DashboardController as TreasurerDashboardController;
use App\Http\Controllers\Treasurer\PaymentController as TreasurerPaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (guest-accessible, rate-limited)
|--------------------------------------------------------------------------
*/
Route::get('/', [LandingController::class, 'index'])->name('landing');

Route::get('/track', [TrackingController::class, 'show'])->name('track.show');
Route::post('/track', [TrackingController::class, 'lookup'])
    ->middleware('throttle:track')
    ->name('track.lookup');

// Manual entry form (no rate limit needed: it only redirects to verify.show,
// which is the throttled, logged lookup).
Route::get('/verify', [VerificationController::class, 'lookup'])
    ->name('verify.lookup');

Route::get('/verify/{token}', [VerificationController::class, 'show'])
    ->middleware('throttle:verify')
    ->name('verify.show');

/*
|--------------------------------------------------------------------------
| Auth (guest)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Applicant (auth + role:applicant)
|--------------------------------------------------------------------------
*/
// Shared entry point: applicants see their dashboard; BPLO/admin are sent to theirs.
Route::get('/dashboard', [ApplicantDashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

Route::middleware(['auth', 'role:applicant'])->group(function (): void {

    Route::get('/applications/create', [ApplicantApplicationController::class, 'create'])->name('applications.create');
    Route::post('/applications', [ApplicantApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications/{application}/edit', [ApplicantApplicationController::class, 'edit'])->name('applications.edit');
    Route::put('/applications/{application}', [ApplicantApplicationController::class, 'update'])->name('applications.update');
    Route::get('/applications/{application}/summary', [ApplicantApplicationController::class, 'summary'])->name('applications.summary');
    Route::post('/applications/{application}/submit', [ApplicantApplicationController::class, 'submit'])->name('applications.submit');

    Route::post('/applications/{application}/requirements', [RequirementController::class, 'uploadMany'])->name('applications.requirements.uploadMany');
    Route::post('/applications/{application}/requirements/{type}', [RequirementController::class, 'upload'])->name('applications.requirements.upload');
    Route::delete('/applications/{application}/requirements/{type}', [RequirementController::class, 'remove'])->name('applications.requirements.remove');
    Route::get('/requirements/{applicationRequirement}/file', [RequirementController::class, 'file'])->name('requirements.file');

    Route::get('/applications/{application}/pay', [ApplicantPaymentController::class, 'show'])->name('applications.pay');
    Route::post('/applications/{application}/pay', [ApplicantPaymentController::class, 'start'])->name('applications.pay.start');
    Route::get('/applications/{application}/payments', [ApplicantPaymentController::class, 'history'])->name('applications.payments');
    Route::get('/payments/{payment}/receipt', [ApplicantPaymentController::class, 'proof'])->name('payments.proof');

    Route::get('/applications/{application}/permit', [ApplicantPermitController::class, 'show'])->name('applications.permit');
    Route::get('/applications/{application}/permit/download', [ApplicantPermitController::class, 'download'])->name('applications.permit.download');

    Route::get('/applications/{application}', [ApplicantApplicationController::class, 'show'])->name('applications.show');
});

/*
|--------------------------------------------------------------------------
| BPLO (auth + role:bplo,admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:bplo,admin'])->prefix('bplo')->name('bplo.')->group(function (): void {
    Route::get('/dashboard', [BploDashboardController::class, 'index'])->name('dashboard');

    Route::get('/applications', [BploApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}', [BploApplicationController::class, 'show'])->name('applications.show');
    Route::post('/applications/{application}/review', [BploApplicationController::class, 'beginReview'])->name('applications.review');
    Route::post('/applications/{application}/approve', [BploApplicationController::class, 'approve'])->name('applications.approve');
    Route::post('/applications/{application}/return', [BploApplicationController::class, 'returnForCorrection'])->name('applications.return');
    Route::post('/applications/{application}/reject', [BploApplicationController::class, 'reject'])->name('applications.reject');
    Route::post('/applications/{application}/requirements/{req}/flag', [BploApplicationController::class, 'flagRequirement'])->name('applications.requirements.flag');
    Route::get('/requirements/{applicationRequirement}/file', [BploApplicationController::class, 'file'])->name('requirements.file');

    Route::get('/payments/{payment}/receipt', [BploPaymentController::class, 'proof'])->name('payments.proof');
    Route::post('/applications/{application}/release', [BploPermitController::class, 'release'])->name('applications.release');
    Route::post('/permits/{permit}/regenerate', [BploPermitController::class, 'regenerate'])->name('permits.regenerate');
});

/*
|--------------------------------------------------------------------------
| Treasurer (auth + role:treasurer,admin) — verifies GCash payments
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:treasurer,admin'])->prefix('treasurer')->name('treasurer.')->group(function (): void {
    Route::get('/dashboard', [TreasurerDashboardController::class, 'index'])->name('dashboard');

    Route::get('/payments', [TreasurerPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [TreasurerPaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment}/confirm', [TreasurerPaymentController::class, 'confirm'])->name('payments.confirm');
    Route::post('/payments/{payment}/reject', [TreasurerPaymentController::class, 'reject'])->name('payments.reject');
    Route::get('/payments/{payment}/receipt', [TreasurerPaymentController::class, 'proof'])->name('payments.proof');
});

/*
|--------------------------------------------------------------------------
| Inspector (auth + role:inspector,admin) — QR scan + read-only compliance
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:inspector,admin'])->prefix('inspector')->name('inspector.')->group(function (): void {
    Route::get('/dashboard', [InspectorDashboardController::class, 'index'])->name('dashboard');

    Route::get('/permits', [InspectorPermitController::class, 'index'])->name('permits.index');
    Route::get('/permits/{permit}', [InspectorPermitController::class, 'show'])->name('permits.show');
});

/*
|--------------------------------------------------------------------------
| Admin (auth + role:admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::resource('requirement-types', RequirementTypeController::class)
        ->parameters(['requirement-types' => 'requirementType'])
        ->except(['show', 'destroy']);
    Route::resource('fee-types', FeeTypeController::class)
        ->parameters(['fee-types' => 'feeType'])
        ->except(['show', 'destroy']);
    Route::resource('fee-rates', FeeRateController::class)
        ->parameters(['fee-rates' => 'feeRate'])
        ->except(['show', 'destroy']);

    Route::get('/settings', [SettingController::class, 'show'])->name('settings');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});

/*
|--------------------------------------------------------------------------
| Sandbox payment webhook (simulated gateway callback)
| CSRF is excluded for this route in bootstrap/app.php.
|--------------------------------------------------------------------------
*/
Route::post('/sandbox/payments/{payment}/webhook', [SandboxWebhookController::class, 'handle'])
    ->middleware('throttle:webhook')
    ->name('sandbox.webhook');
