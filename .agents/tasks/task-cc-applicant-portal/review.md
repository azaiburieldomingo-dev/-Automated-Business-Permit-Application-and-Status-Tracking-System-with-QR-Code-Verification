# Applicant control-center portal — convergence review

The three-part build (FEAT-001 scaffolding, FEAT-002 dashboard/business-wiring/renewal/flash, FEAT-003 tests) surfaces the existing 9-status permit workflow to the applicant as a control-center dashboard, wires the dormant `businesses` table into application create/store, adds Business Profile / Settings / Help flows, a read-only renewal reminder, and fixes one live applicant-facing payment-verifier string. The change is overwhelmingly UI surfacing plus small controller/config additions over data that already exists; no schema change, no new packages. Verified against the real feature diff (`795f8fc..a7b2f20`) and the recorded verification evidence.

**Watch for:** nothing blocking. (confirmed)

**Verdict**: APPROVED

<details>
<summary>Issues (0)</summary>

No blocking or non-blocking action items. All hard-correctness constraints hold and the recorded suite is green.

</details>

<details>
<summary>Details</summary>

## Hard-correctness constraints — all hold

**Nine statuses only (confirmed).** The dashboard rebuild references statuses exclusively through `Application::STATUS_*` constants and renders every chip via `<x-status-badge>` / `<x-process-tracker viewer="applicant">`. No new label strings ("Paid"/"For Payment"/"Released") appear. The payment chip in the fee summary (`Paid` / `Awaiting verification by the Treasurer` / `Pending`) is payment-row presentation, not an application status label.

**Treasurer verifies payment (confirmed).** `PaymentController@start` manual-mode flash changed from "The BPLO will check your payment…" to "The Treasurer will verify your GCash payment…". The dashboard PAYMENT_PENDING callout reads "The Treasurer is verifying your GCash payment." and the fee chip names the Treasurer. The flash and callout now agree; `ManualPaymentTest` asserts the flash contains "Treasurer" and not "BPLO".

**Single source of truth = `applications.status` (confirmed).** `DashboardController@index` derives all stat counts and callout partitions from the eager-loaded `status` column; correction text is read from the `remarks` relation (`type='CORRECTION'`). No second status store is introduced.

**Fees only from the service, never before APPROVED_FOR_PAYMENT (confirmed).** The fee block is gated by an 8-status `$order` array that deliberately omits REJECTED, with a membership guard before the index compare (`$idx !== false && $idx >= $payIdx`). REJECTED and FOR_CORRECTION therefore never show a fee. The controller does not call `FeeComputationService` eagerly; amounts shown come only from a persisted `Payment` row, and APPROVED_FOR_PAYMENT always links to the guarded `applications.pay` page (the service's single amount authority) — even when a prior FAILED payment exists, surfacing its `rejection_reason`. `DashboardTest` asserts the UNDER_REVIEW / FOR_CORRECTION / REJECTED no-fee branches, the re-payment routing, and latest-row selection.

**Permit + QR gated (confirmed).** The permit card renders only when `status === PERMIT_RELEASED && $application->permit` is non-null; the download/QR routes keep their existing `abort_if(permit===null,404)` guard untouched.

**Role never mass-assignable (confirmed).** None of `BusinessProfileRequest`, `UpdateProfileRequest`, or `UpdatePasswordRequest` includes `role` or `is_active`. `SettingsController@updateProfile` fills only `name`/`email`/`contact_number`/`address`. `user_id` on Business is set via `associate()`, never via `fill()`.

**`business_id`-null backward compatibility (confirmed).** `ApplicationController@store` builds the application in memory, wraps the business find-or-create + associate in `try/catch` with `report($e)` on throw, and runs `$application->save()` LAST, unconditionally. A failed business write leaves `business_id` null and still creates the application, so pre-existing null-business flows and `ApplicationFlowTest` are unaffected.

**ApplicationFlowTest not broken (confirmed, per evidence).** Recorded green in FEAT-002 and FEAT-003 verification runs.

**R1 adds no `application_type` column (confirmed).** No migration files changed in the diff; the only `application_type` string is in the FEAT-002 description text. Renewal is read-only over `permit.valid_until`.

## Password-change policy parity (confirmed)

`UpdatePasswordRequest` pins `password` to `Password::min(8)->mixedCase()->numbers()` — the exact `Auth\RegisterRequest` rule — and requires `current_password`. `SettingsTest` asserts wrong-current-password rejection with the password unchanged, and register/change parity.

## Renewal reminder math (confirmed)

Carbon 3's signed-float `diffInDays()` is floored and int-cast from start-of-day on both sides; `$expired = $daysLeft < 0`; included when `$daysLeft <= config('permit.renewal.reminder_days', 30)`. Candidates are filtered to ACTIVE permits with non-null `valid_until`. `DashboardTest` asserts a 10-day permit shows the banner and a 10-month permit does not. `makePermit($application, $status, $validUntil)` matches the test call signature.

## Test coverage and the honest bug note

Recorded full suite: **182 passed / 630 assertions** (baseline 154/519; +28 new, above the >154 gate). FEAT-003 added BusinessProfileTest, SettingsTest, DashboardTest, HelpPageTest and extended ManualPaymentTest. The evidence candidly records a pre-existing `@php(...)` single-line mis-compile in the rebuilt dashboard (nested parens / consecutive shortcuts emitting malformed PHP), found when the new DashboardTest first rendered the view with real data, and fixed by converting to full `@php … @endphp` blocks — a behavior-restoring change with no design deviation. The committed view confirms the full-block form.

</details>

<details>
<summary>File map</summary>

- `app/Http/Controllers/Applicant/ApplicationController.php` — create prefill from Business/User; store best-effort business link/upsert, save last.
- `app/Http/Controllers/Applicant/DashboardController.php` — eager loads, status-derived stats, renewal reminder computation.
- `app/Http/Controllers/Applicant/PaymentController.php` — one-line manual flash BPLO→Treasurer.
- `app/Http/Controllers/Applicant/BusinessProfileController.php` — idempotent create-or-update, oldest row, associate user_id.
- `app/Http/Controllers/Applicant/SettingsController.php` — profile (4 safe fields) + password (session regenerate).
- `app/Http/Controllers/Applicant/HelpController.php` — config-driven support/payment details.
- `app/Http/Requests/{BusinessProfile,UpdateProfile,UpdatePassword}Request.php` — rules only, no role/is_active.
- `config/permit.php` — `renewal.reminder_days` + nullable `support` block.
- `resources/views/applicant/dashboard.blade.php` — control-center IA rebuild.
- `resources/views/applicant/{business-profile,settings,help}.blade.php` + `create.blade.php` prefill include + `layouts/app.blade.php` nav.
- `routes/web.php` — 6 applicant routes in the existing role:applicant group.
- `tests/Feature/Applicant/{BusinessProfile,Settings,Dashboard,HelpPage}Test.php` + `Payment/ManualPaymentTest.php`.

Full diff: `git -C "c:\Users\HP\BUSINESS PERMIT_SYSTEM\app" diff 795f8fc..a7b2f20`

</details>
