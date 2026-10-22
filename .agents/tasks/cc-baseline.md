# CC Baseline — Ground Truth Summary

Municipality of Bansud Business Permit System · Laravel 13 / PHP 8.3 (Laragon) / SQLite · timezone Asia/Manila · payment mode `manual` (GCash), simple-qrcode SVG only, dompdf.

Laravel root for ALL commands: `c:\Users\HP\BUSINESS PERMIT_SYSTEM\app` (the `app` subfolder IS the Laravel root; the parent path contains a space — always quote paths).

## Baseline test count (observed this session)

- Command: `php artisan test` run from `app/`.
- Result: **154 passed (519 assertions)** — GREEN. Duration ~25.7s.
- Environment confirmed: PHP 8.3.16 (ZTS, VS2019 x64), Composer 2.8.10. No `composer install` was needed; tests ran on the first attempt.
- Nothing was modified to make tests pass. This is the true, unmodified baseline and matches the investigation's recorded 154/519.

## Nine application statuses (`app/Models/Application.php`, lines 12–30)

| Constant | String value |
|---|---|
| `STATUS_DRAFT` | `DRAFT` |
| `STATUS_SUBMITTED` | `SUBMITTED` |
| `STATUS_UNDER_REVIEW` | `UNDER_REVIEW` |
| `STATUS_FOR_CORRECTION` | `FOR_CORRECTION` |
| `STATUS_APPROVED_FOR_PAYMENT` | `APPROVED_FOR_PAYMENT` |
| `STATUS_PAYMENT_PENDING` | `PAYMENT_PENDING` |
| `STATUS_PAYMENT_VERIFIED` | `PAYMENT_VERIFIED` |
| `STATUS_PERMIT_RELEASED` | `PERMIT_RELEASED` |
| `STATUS_REJECTED` | `REJECTED` |

Happy path: DRAFT → SUBMITTED → UNDER_REVIEW → APPROVED_FOR_PAYMENT → PAYMENT_PENDING → PAYMENT_VERIFIED → PERMIT_RELEASED. Side branches: UNDER_REVIEW → FOR_CORRECTION → (resubmit) → SUBMITTED; SUBMITTED/UNDER_REVIEW → REJECTED; PAYMENT_PENDING → APPROVED_FOR_PAYMENT (payment rejected). Authoritative transition map: `ApplicationWorkflowService::TRANSITIONS` (`app/Services/ApplicationWorkflowService.php`, lines 30–57).

**Do not invent labels.** Use only these nine statuses and their `<x-status-badge>` labels. No "Paid"/"Released"/"For Payment" strings.

## Payment flow + roles (separation of duties)

- Applicant: APPROVED_FOR_PAYMENT → PAYMENT_PENDING via `Applicant\PaymentController@start` (route `applications.pay.start`) → `PaymentService::startPayment()`.
- **Treasurer (NOT BPLO)** verifies: PAYMENT_PENDING → PAYMENT_VERIFIED via `Treasurer\PaymentController@confirm` (route `treasurer.payments.confirm`) → `PaymentService::verify()`.
- Treasurer rejects: PAYMENT_PENDING → APPROVED_FOR_PAYMENT via `Treasurer\PaymentController@reject` (route `treasurer.payments.reject`).
- BPLO releases: PAYMENT_VERIFIED → PERMIT_RELEASED via `Bplo\PermitController@release` (route `bplo.applications.release`) → `PermitService::release()`.
- BPLO/admin handle review transitions: `bplo.applications.review` (begin review), `bplo.applications.approve`, `bplo.applications.return`, `bplo.applications.reject`.
- Role constants on `User`: `applicant`, `bplo`, `admin`, `treasurer`, `inspector`. Middleware: BPLO `role:bplo,admin`, Treasurer `role:treasurer,admin`, Inspector `role:inspector,admin`, Admin `role:admin`.
- Fee amounts are data-driven via `FeeComputationService::computeFor()` (active `fee_rates` + `fee_types`). Never hard-code totals; amount is only shown once status is `APPROVED_FOR_PAYMENT` (guarded in `Applicant\PaymentController@show`, route `applications.pay`).

## `businesses` table columns (`Business` model — currently DORMANT)

Migration `..._000002_create_businesses_table.php`, model `app/Models/Business.php`. `Application` has nullable FK `business_id` + `business()` relation; `User` has `businesses()` hasMany. **The applicant flow never writes this table** (`Applicant\ApplicationController@store` never sets `business_id`). Business data today lives per-application on `Application` (fillable: `applicant_name`, `contact_number`, `email`, `business_name`, `business_address`, `business_nature`, `ownership_type`, `dti_sec_type`, `dti_sec_number`, `barangay`, `number_of_employees`). A Business Profile feature needs NEW backend (routes + controller + form request + view) to wire up the existing table.

## Permit `issued_at` / `valid_until` columns (`app/Models/Permit.php`)

Migration `2025_01_01_000012_create_permits_table.php`. Columns: `id`, `application_id` (unique), `permit_number` (unique), `verification_token` (64, unique), `business_name`, `owner_name`, `business_address`, `business_nature`, `issued_at` (date), `valid_until` (date), `status` (default `ACTIVE`; constants `STATUS_ACTIVE`/`STATUS_REVOKED`), `pdf_path` (nullable), `generated_by`, timestamps. `valid_until = issued_at + config('permit.valid_months')` (default 12). Public verify page classifies EXPIRED from `valid_until`, but **nothing surfaces an applicant-facing renewal/expiry reminder** — a Renewal Reminder is NEW backend (no `application_type` column, no renewal flow).

- Applicant permit view: `Applicant\PermitController@show` (route `applications.permit`) → `permits/show.blade.php`, inline SVG QR (simple-qrcode) encoding `$permit->verifyUrl()`.
- Download PDF: `Applicant\PermitController@download` (route `applications.permit.download`), dompdf, re-rendered each time. Guarded: permit only exists after BPLO release (`PermitService::release()` requires `PAYMENT_VERIFIED`).

## Applicant routes group (`routes/web.php`)

Middleware `['auth', 'role:applicant']`, NO URL prefix (applicant routes sit at root). `/dashboard` is `['auth']` only (shared entry; non-applicants redirected by controller). Key routes:

- `dashboard` → `Applicant\DashboardController@index`
- `applications.create` / `applications.store` / `applications.edit` / `applications.update` / `applications.summary` / `applications.submit`
- `applications.requirements.uploadMany` / `applications.requirements.upload` / `applications.requirements.remove` / `requirements.file`
- `applications.pay` / `applications.pay.start` / `applications.payments` / `payments.proof`
- `applications.permit` / `applications.permit.download`
- `applications.show`

Tracking number format: `BANSUD-YYYY-NNNNNN` (column `applications.tracking_number`, unique, assigned on first submit by `TrackingNumberService`). Public lookup: `track.show`/`track.lookup` (2-factor: tracking number + email or last name). `User::homePath()`: admin→`/admin/dashboard`, bplo→`/bplo/dashboard`, treasurer→`/treasurer/dashboard`, inspector→`/inspector/dashboard`, default applicant→`/dashboard`.

## `layouts/app.blade.php` `$nav` structure

File `resources/views/layouts/app.blade.php`. `$nav` is a flat PHP array of `[url, label]` pairs built in an inline `@php` block (lines ~15–50), selected by role via `$user?->isApplicant()` / `isBplo()` / `isTreasurer()` / `isInspector()` / `isAdmin()`. Current applicant nav: `['/dashboard','Dashboard']`, `['/applications/create','New Application']`. The sidebar `@foreach ($nav as [$url, $label])` renders links and marks active by path prefix. Only two array elements are consumed — the `[url,label,icon]` comment is aspirational; icons are NOT currently rendered. Add applicant-only nav items (Business Profile, Help, Settings) by extending the `isApplicant()` branch. `$unread` (bell count) is computed independently in this layout (`Notification::where('user_id', …)->whereNull('read_at')->count()`); `$roleLabel = ucfirst($user->role)`.

Ready-made Blade components for the overhaul: `<x-stat-card>` (props `value` [HTML via `{!! !!}`], `label`, `icon` [unused], `accent` = default|green|amber|red|mint), `<x-empty-state>` (`icon` [unused], `title`, `message`, optional action slot), `<x-status-badge>` (`status`; maps all 9 statuses), `<x-process-tracker>` (props `application`, `viewer` = applicant|bplo|treasurer|inspector|admin — the ready-made status tracker), `<x-notification-bell>` (`count`), plus `<x-flash-messages>`, `<x-pagination>`, `<x-theme-switcher>`.

## Correctness constraints (do not violate)

1. Use only the nine real statuses + `<x-status-badge>` labels.
2. Payment is verified by the **Treasurer**, not BPLO.
3. Applicant status is the SAME `applications.status` staff update (one source of truth). Correction/rejection reasons come from `ApplicationRemark` (CORRECTION/REJECTION) + status-history `note`.
4. Fee amounts from `FeeComputationService`; never hard-code; never show before `APPROVED_FOR_PAYMENT`.
5. Dashboard marks all unread notifications read on load, so an "Action Required" indicator must key off application STATUS (FOR_CORRECTION / APPROVED_FOR_PAYMENT / PERMIT_RELEASED), not `read_at`.

## Blockers

None. PHP/Composer present, dependencies installed, baseline green (154 passed / 519 assertions), investigation file present and complete, and this `cc-baseline.md` is written to `app/.agents/tasks/`.
