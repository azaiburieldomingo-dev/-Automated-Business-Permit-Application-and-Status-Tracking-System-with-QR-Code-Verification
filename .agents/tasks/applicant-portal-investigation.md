# Applicant Portal Investigation — Ground Truth Report

Municipality of Bansud Business Permit System · Laravel 13 / PHP 8.3 / SQLite · timezone Asia/Manila · manual GCash payment mode.
Laravel root: `c:\Users\HP\BUSINESS PERMIT_SYSTEM\app` (the `app` subfolder IS the Laravel root — parent path contains a space).

> READ-ONLY investigation. No code was changed. Test baseline was run to confirm it is green: **154 passed (519 assertions)** via `php artisan test` from `app/`.

---

## Summary (read this first)

The backend is a complete, event-driven permit workflow. Almost every feature the user asked for in the "control center" dashboard **already exists in the backend** and only needs to be surfaced in the applicant UI. The current `applicant/dashboard.blade.php` shows only two cards (My Applications table + Notifications list) and leaves the status tracker, requirements checklist, fee/payment card, permit+QR card, and application-history affordances scattered across other pages (`applicant/show`, `applicant/payment`, `permits/show`) rather than consolidated on the dashboard.

Three features need **new backend**: a **Business Profile** edit flow (a `businesses` table exists but is unused and not writable by applicants; business data is stored per-application), **Account/Security Settings** (no profile/password-change route for logged-in users), and a **Renewal Reminder** (no `application_type` column, no renewal concept — permits have `valid_until` but nothing reads it for reminders). A **Help/FAQ** page is purely a new static view + route.

Critical correctness note for the overhaul: **payment is verified by the TREASURER role, not the BPLO**, and the status tracker must use only the nine real statuses. Do not invent "For Payment / Paid / Released" labels — the real names are listed below.

---

## 1. Application Status Workflow

**Status constants** — `app/Models/Application.php` lines 12–30:

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

**Legal transitions** — the authoritative table is `ApplicationWorkflowService::TRANSITIONS`, `app/Services/ApplicationWorkflowService.php` lines 30–57:

```
DRAFT                -> SUBMITTED
SUBMITTED            -> UNDER_REVIEW | REJECTED
UNDER_REVIEW         -> FOR_CORRECTION | APPROVED_FOR_PAYMENT | REJECTED
FOR_CORRECTION       -> SUBMITTED
APPROVED_FOR_PAYMENT -> PAYMENT_PENDING
PAYMENT_PENDING      -> APPROVED_FOR_PAYMENT | PAYMENT_VERIFIED
PAYMENT_VERIFIED     -> PERMIT_RELEASED
```

**Ordered lifecycle (happy path):** DRAFT → SUBMITTED → UNDER_REVIEW → APPROVED_FOR_PAYMENT → PAYMENT_PENDING → PAYMENT_VERIFIED → PERMIT_RELEASED. Side branches: UNDER_REVIEW → FOR_CORRECTION → (resubmit) → SUBMITTED; any of SUBMITTED/UNDER_REVIEW → REJECTED; PAYMENT_PENDING → APPROVED_FOR_PAYMENT when a payment is rejected.

**Who transitions each step** (confirmed against controllers):

| Transition | Role | Trigger (controller @ method, route name) |
|---|---|---|
| DRAFT/FOR_CORRECTION → SUBMITTED | **Applicant** | `Applicant\ApplicationController@submit` → `ApplicationWorkflowService::submit()` · route `applications.submit` |
| SUBMITTED → UNDER_REVIEW | **BPLO/admin** | `Bplo\ApplicationController@beginReview` → `beginReview()` · route `bplo.applications.review` |
| UNDER_REVIEW → APPROVED_FOR_PAYMENT | **BPLO/admin** | `Bplo\ApplicationController@approve` → `approveForPayment()` · route `bplo.applications.approve` |
| UNDER_REVIEW → FOR_CORRECTION | **BPLO/admin** | `Bplo\ApplicationController@returnForCorrection` → `returnForCorrection()` · route `bplo.applications.return` |
| → REJECTED | **BPLO/admin** | `Bplo\ApplicationController@reject` → `reject()` · route `bplo.applications.reject` |
| APPROVED_FOR_PAYMENT → PAYMENT_PENDING | **Applicant** | `Applicant\PaymentController@start` → `PaymentService::startPayment()` (calls `markPaymentPending`) · route `applications.pay.start` |
| PAYMENT_PENDING → PAYMENT_VERIFIED | **Treasurer/admin** | `Treasurer\PaymentController@confirm` → `PaymentService::verify()` (calls `markPaymentVerified`) · route `treasurer.payments.confirm` |
| PAYMENT_PENDING → APPROVED_FOR_PAYMENT | **Treasurer/admin** | `Treasurer\PaymentController@reject` → `PaymentService::reject()` · route `treasurer.payments.reject` |
| PAYMENT_VERIFIED → PERMIT_RELEASED | **BPLO/admin** | `Bplo\PermitController@release` → `PermitService::release()` · route `bplo.applications.release` |

> IMPORTANT: Payment verification is a **Treasurer** action (separation of duties), not BPLO. The `process-tracker.blade.php` component labels the "Payment Verification" step actor as "Treasurer" (line matching `App_::STATUS_PAYMENT_PENDING => 'Treasurer'`). The user's wording said "BPLO verifies payment"; the real system assigns this to the treasurer role.

**Reason / notes captured on correction/rejection:** `returnForCorrection()` and `reject()` both require a `remark` string (validated by `ReviewActionRequest`) and persist it two ways: an `ApplicationRemark` row (`type` = `CORRECTION` or `REJECTION`, column `body`) and the `note` column on `ApplicationStatusHistory`. See `ApplicationWorkflowService::returnForCorrection/reject` and `addRemark()`.

---

## 2. Requirements Model

**Model:** `app/Models/ApplicationRequirement.php`. **Migration:** `database/migrations/2025_01_01_000005_create_application_requirements_table.php`.

**Columns:** `id`, `application_id`, `requirement_type_id`, `original_filename`, `stored_path`, `mime`, `size_kb`, `checklist_status` (default `MISSING`), `correction_note`, `uploaded_at`, timestamps. Unique on `[application_id, requirement_type_id]`.

**Checklist status constants** (`ApplicationRequirement.php` lines 11–15): `CHECK_COMPLETE = 'COMPLETE'`, `CHECK_MISSING = 'MISSING'`, `CHECK_FOR_CORRECTION = 'FOR_CORRECTION'`. There is no separate verified/rejected boolean — state is the single `checklist_status` string. The "reason to replace a file" field is `correction_note`.

**Required-vs-optional** is NOT on `ApplicationRequirement`; it lives on the parent `RequirementType` (`app/Models/RequirementType.php`): columns `name`, `code`, `is_required` (bool), `is_active` (bool), `sort_order`. The checklist of docs is **global**, not per-business-type. Seeded in `database/seeders/RequirementTypeSeeder.php`: Barangay Business Clearance (required), DTI/SEC Registration (required), Valid ID (required), Previous Permit (optional), Other (optional). Admin can edit these via `admin.requirement-types.*` routes.

**BPLO flags a requirement:** `Bplo\ApplicationController@flagRequirement` · route `bplo.applications.requirements.flag`. Validates `checklist_status` in `COMPLETE,MISSING,FOR_CORRECTION` + optional `correction_note` (max 2000), writes them to the `ApplicationRequirement` row.

**Applicant upload / replace:**
- Single: `Applicant\RequirementController@upload` · route `applications.requirements.upload` (`UploadRequirementRequest`).
- Batch: `Applicant\RequirementController@uploadMany` · route `applications.requirements.uploadMany` — files keyed `files[<requirementTypeId>]` (`UploadManyRequirementsRequest`).
- Remove: `@remove` · route `applications.requirements.remove`.
- Serve file (applicant): `@file` · route `requirements.file`.
Every upload sets `checklist_status = COMPLETE` and clears `correction_note`. Replacing deletes the old stored file. Upload rules from `config('permit.uploads')`: mimes pdf/jpg/jpeg/png, max 4096 KB.

**Submit guard:** `Applicant\ApplicationController@submit` blocks submission unless every active `is_required` RequirementType has a `COMPLETE` requirement row.

---

## 3. Fees & Payment

**Models/migrations:**
- `app/Models/Payment.php` + `database/migrations/2025_01_01_000010_create_payments_table.php` + `database/migrations/2026_10_07_000001_add_manual_payment_fields_to_payments_table.php`.
- Fee config is **data-driven**: `app/Models/FeeType.php`, `app/Models/FeeRate.php` (migrations `..._000008` / `..._000009`). There is no stored "assessment" table — the breakdown is computed on demand and snapshotted into the Payment row.

**Payment columns:** `id`, `application_id`, `tracking_number`, `amount` (decimal 12,2), `breakdown_json` (JSON, cast to array), `method`, `reference_number`, `status` (default `PENDING`), `verification_status` (default `UNVERIFIED`), `payment_date`, `verified_at`, `verified_by`, timestamps; plus manual-mode columns: `payer_name`, `payer_number`, `proof_path`, `proof_original_name`, `rejection_reason` (max 500).

**Payment status constants** (`Payment.php` lines 11–23): `STATUS_PENDING='PENDING'`, `STATUS_PROCESSING='PROCESSING'`, `STATUS_PAID='PAID'`, `STATUS_FAILED='FAILED'`, `STATUS_CANCELLED='CANCELLED'`; verification: `VERIFY_UNVERIFIED='UNVERIFIED'`, `VERIFY_VERIFIED='VERIFIED'`. Helper `isAwaitingVerification()` = status `PROCESSING` AND not `VERIFIED`. (Note: the `startPayment` path writes `status = PENDING`; the ManualGateway's `initiate` moves it to the awaiting-verification state — see `PaymentService::startPayment` then `$this->gateway()->initiate()`.)

**Assessment creation:** there is no up-front assessment record. `FeeComputationService::computeFor($application)` (`app/Services/FeeComputationService.php`) reads **active** `fee_rates` joined to **active** `fee_types` ordered by `sort_order`, and returns a `FeeBreakdown` (`app/Support/FeeBreakdown.php`). Amounts are data, never hard-coded; empty rate table → total 0 with a logged warning. Seeded amounts (`FeeRateSeeder.php`): business_tax 500, permit_fee 300, regulatory_fees 150, other_fees 50.

**Where the breakdown is shown to the applicant:** `Applicant\PaymentController@show` (route `applications.pay`) — **only when `status === APPROVED_FOR_PAYMENT`** (guarded with `abort_unless`). The dashboard/show pages do NOT display a fixed amount before assessment; the amount only becomes meaningful once BPLO approves for payment. This satisfies the user's rule "do not show a fixed amount before assessment."

**GCash manual flow:** `config/permit.php` → `payment` block. Key values (confirmed in `.env`): `PAYMENT_MODE=manual`, GCash enabled, `PAYMENT_GCASH_NUMBER=09670829993`, `PAYMENT_GCASH_NAME="Aiza Domingo"`. `config('permit.payment.mode')='manual'`, `methods` (gcash/maya/qrph toggles), `accounts` (label/number/name per method). `app/Support/PaymentMethods.php` is the single source for enabled/available methods (a method with no number shows as unavailable). Manual flow: applicant pays externally, then submits reference number + payer name/number + receipt proof via `Applicant\PaymentController@start`; treasurer confirms/rejects. Reference numbers are normalized (`PaymentService::normalizeReference`) and de-duplicated.

**Payment history for applicant:** `Applicant\PaymentController@history` · route `applications.payments`; own receipt download `@proof` · route `payments.proof`.

---

## 4. Notifications

**Model:** `app/Models/Notification.php`. **Migration:** `database/migrations/2025_01_01_000014_create_notifications_table.php`.

**Columns:** `id`, `user_id`, `application_id` (nullable — links to the related application), `type`, `title`, `body` (text), `channel` (default `in_app`), `sms_status` (nullable), `read_at` (nullable timestamp), timestamps. Index on `[user_id, read_at]`.

**Where created:** `app/Services/NotificationService::notify()` creates the row (and optionally fires SMS). It is called by the listener `app/Listeners/SendStatusNotification.php`, which handles all seven domain events (`ApplicationSubmitted`, `ApplicationUnderReview`, `ApplicationReturnedForCorrection`, `ApplicationApprovedForPayment`, `PaymentConfirmed`, `PermitReleased`, `ApplicationRejected`). Events are dispatched from `ApplicationWorkflowService::transition()` via its `EVENTS` map, and `PaymentConfirmed` is also dispatched from `PaymentService::verify()`. The listener builds human-readable bodies (e.g., "Your Mayor's Permit for application {tracking} has been released and is ready to download.") and specially re-labels a payment-rejection case ("Payment Not Accepted" with the rejection reason). The listener is intentionally synchronous (not `ShouldQueue`).

**Unread indicator mechanism:** unread = `read_at IS NULL`.
- Topbar bell count: `layouts/app.blade.php` counts `Notification::where('user_id', …)->whereNull('read_at')->count()` into `$unread`, rendered by `components/notification-bell.blade.php` (the bell links to `homePath()`, i.e. `/dashboard` for applicants).
- `Applicant\DashboardController@index` loads the latest 20 notifications, computes `$unreadCount`, then **marks all unread as read** on view (`->update(['read_at' => now()])`). So visiting the dashboard clears the unread badge — relevant for the overhaul: an "Action Required" card that needs a persistent indicator cannot rely on `read_at` alone, because the dashboard zeroes it on load.

---

## 5. Permit + QR

**Model:** `app/Models/Permit.php`. **Migration:** `database/migrations/2025_01_01_000012_create_permits_table.php`.

**Columns:** `id`, `application_id` (unique), `permit_number` (unique), `verification_token` (64, unique), `business_name`, `owner_name`, `business_address`, `business_nature`, `issued_at` (date), `valid_until` (date), `status` (default `ACTIVE`), `pdf_path` (nullable), `generated_by`, timestamps. Status constants: `STATUS_ACTIVE='ACTIVE'`, `STATUS_REVOKED='REVOKED'`. `valid_until = issued_at + config('permit.valid_months')` (default 12).

**Download PDF (applicant):** `Applicant\PermitController@download` · route `applications.permit.download`. Re-renders the PDF fresh each time (so the QR uses the current public URL) via `PermitService::renderPdf()` (dompdf, view `permits/pdf.blade.php`).

**Permit + QR page (applicant):** `Applicant\PermitController@show` · route `applications.permit` → view `permits/show.blade.php`. Renders inline **SVG** QR with simple-qrcode (`QrCode::format('svg')`), encoding `$permit->verifyUrl()` = `config('permit.public_url')/verify/{token}`.

**Public verify page:** `PublicPages\VerificationController@show` · route `verify.show` (`/verify/{token}`, throttled). Classifies NOT_FOUND / REVOKED / EXPIRED / VALID and logs a `QrVerification` row. Manual-entry form: `@lookup` · route `verify.lookup`.

**Guard that permit is only available when truly released:** `PermitService::release()` throws unless `status === PAYMENT_VERIFIED`; it creates the Permit row and transitions to `PERMIT_RELEASED` inside one transaction. The applicant permit routes `abort_if($permit === null, 404)` — the `permit` relation only exists after release. `PermitPolicy@download` additionally gates it. So there is no way to reach a valid permit before BPLO release.

---

## 6. Application Reference / Tracking Number

**Column:** `applications.tracking_number` (string, unique, nullable, indexed) — migration `..._000004`, line adding `$table->string('tracking_number')->unique()->nullable()->index()`.

**Generation:** assigned on first submit by `ApplicationWorkflowService::submit()` → `assignTrackingNumber()` → `TrackingNumberService::generate($year)` (`app/Services/TrackingNumberService.php`). Format `BANSUD-{year}-{000001}` (prefix `config('permit.tracking_prefix')='BANSUD'`), backed by atomic `number_sequences` increments with a 5-attempt collision retry against the unique index. Resubmitting from FOR_CORRECTION keeps the same tracking number (confirmed by test `test_resubmit_from_for_correction_keeps_the_same_tracking_number`).

> Note: `config/permit.php` also defines `permit_prefix='BP'`, used for permit numbers (not tracking numbers). The user's mock reference "BP-2026-XXXX" resembles the **permit** prefix; actual applicant tracking numbers are `BANSUD-YYYY-NNNNNN`.

**Public tracking lookup:** `PublicPages\TrackingController` · routes `track.show` (GET `/track`) and `track.lookup` (POST `/track`, throttled). Looks up by `tracking_number` **plus a second factor** (`verifier` = email OR applicant last name) via `verifierMatches()`. View `public/track.blade.php`.

---

## 7. Current Applicant Dashboard

**Controller:** `app/Http/Controllers/Applicant/DashboardController.php` (`index`). Non-applicants are redirected to `homePath()`. Data passed to the view:
- `applications` = `$user->applications()->latest()->get()` (NO eager loads — requirements/payments/permit are not loaded here).
- `notifications` = latest 20 `Notification` rows for the user.
- `unreadCount` = unread among those 20. **Side effect:** all unread notifications are marked read on load.

**View:** `resources/views/applicant/dashboard.blade.php`. Current cards:
1. **Welcome banner** — "Welcome to the BPLO Applicant Portal" + the exact subtitle the user requested.
2. **My Applications** table — columns Tracking No. / Business / Status (`<x-status-badge>`) / Last Updated / actions (Edit shown only for DRAFT & FOR_CORRECTION; View always). "New Application" button.
3. **Notifications** list — title, body, timestamp (uses `<x-empty-state>` when empty).

There are **no** stat cards, no status tracker, no requirements/fees/permit cards on the dashboard today. Those exist on `applicant/show.blade.php` (per-application detail, which loads requirements/statusHistories/remarks/payments/permit and the `<x-process-tracker>`), `applicant/payment.blade.php`, `applicant/payments.blade.php`, and `permits/show.blade.php`.

---

## 8. Layout + Nav

**File:** `resources/views/layouts/app.blade.php`.

**`$nav` construction** (inline `@php` block, lines ~15–50): a flat PHP array of `[url, label]` pairs chosen by role via `$user?->isApplicant()` / `isBplo()` / `isTreasurer()` / `isInspector()` / `isAdmin()`. Current applicant nav: `['/dashboard','Dashboard']`, `['/applications/create','New Application']`. The sidebar `@foreach ($nav as [$url, $label])` renders links and marks active by path prefix. **To add applicant-only nav items** (Business Profile, Help, Settings) you extend the `isApplicant()` branch of this array; icons are not currently used (the `[url,label,icon]` comment is aspirational — only two elements are consumed).

`$unread` is computed here independently (not passed from controllers) for the bell. `$roleLabel = ucfirst($user->role)`.

**Blade components (paths + props):**
- `<x-stat-card>` — `resources/views/components/stat-card.blade.php`. Props: `value` (rendered with `{!! !!}`, so HTML allowed), `label`, `icon` (declared but unused in markup), `accent` (`default|green|amber|red|mint`). Wrapper class `stat-card accent-{accent}`.
- `<x-empty-state>` — `components/empty-state.blade.php`. Props: `icon` (declared, not rendered in markup), `title`, `message`; optional slot for an action button.
- `<x-status-badge>` — `components/status-badge.blade.php`. Prop: `status`. Maps all nine statuses to a badge class + human label (e.g. APPROVED_FOR_PAYMENT → "Approved for Payment", PERMIT_RELEASED → green "Permit Released").
- `<x-process-tracker>` — `components/process-tracker.blade.php`. Props: `application`, `viewer` (`applicant|bplo|treasurer|inspector|admin`). Renders a 6-step progress list + a tailored "What Happens Next" action message per viewer/status. **This is the ready-made status tracker for the dashboard overhaul.**
- `<x-notification-bell>` — `components/notification-bell.blade.php`. Prop: `count`.
- Also present: `<x-flash-messages>`, `<x-pagination>`, `<x-theme-switcher>`.

---

## 9. User / Business Profile

**User model:** `app/Models/User.php`. Fillable (via `#[Fillable]` attribute): `name`, `email`, `password`, `contact_number`, `address`, `is_active`. Role is NEVER mass-assignable (forced to `applicant` on registration). Role constants: `applicant`, `bplo`, `admin`, `treasurer`, `inspector`. **Migrations:** `0001_01_01_000000_create_users_table.php` (name, email, password) + `2025_01_01_000001_add_profile_fields_to_users_table.php` (role, contact_number, address, is_active).

So the User carries only **personal/account** fields: name, email, contact_number, address. No business fields on the user.

**Business data location:** the business fields live **per-application** on `Application` (fillable: `applicant_name`, `contact_number`, `email`, `business_name`, `business_address`, `business_nature`, `ownership_type`, `dti_sec_type`, `dti_sec_number`, `barangay`, `number_of_employees`).

**A `Business` model/table EXISTS but is effectively unused:** `app/Models/Business.php` + migration `..._000002_create_businesses_table.php`; `Application` has a `business_id` (nullable FK) and a `business()` relation, and `User` has a `businesses()` hasMany. **However**, `Applicant\ApplicationController@store` never sets `business_id` and never creates a `Business`; nothing in the applicant flow writes the `businesses` table. There is no controller/route for applicants to view or edit a Business Profile.

**Implication for the "Business Profile" feature:** the table exists but is dormant and not wired to any applicant UI/route. A Business Profile feature needs **new backend** — at minimum a controller + routes to create/read/update a `Business` for the applicant (and a decision on whether new applications should prefill from / link to it via the existing `business_id`). No new table is strictly required since `businesses` already exists, but new routes, a controller, a form request, and a Blade view are required.

---

## 10. Renewal

**No renewal concept exists.** There is **no `application_type` column** on `applications` (confirmed against the migration and model fillable — only business/applicant fields, no new/renewal flag). No renewal application flow, no renewal route, no "previous permit" linkage beyond the optional `previous_permit` requirement type.

Permits DO have `valid_until` (and `issued_at`), and the public verify page classifies `EXPIRED` when `valid_until` is past. But **nothing surfaces an expiration/renewal reminder to the applicant** — no scheduled job, no dashboard logic, no notification on upcoming expiry. A **Renewal Reminder** feature therefore needs new backend: at minimum dashboard/notification logic that reads `permit.valid_until`, and (if a true renewal application is wanted) an `application_type` column + renewal flow.

---

## 11. Routes Inventory (applicant-prefixed)

From `routes/web.php`. The applicant group uses middleware `['auth', 'role:applicant']` (no URL prefix — applicant routes sit at the root). `/dashboard` itself is `['auth']` only (shared entry point; non-applicants are redirected by the controller).

| Method | URI | Name | Controller@method |
|---|---|---|---|
| GET | /dashboard | `dashboard` | `Applicant\DashboardController@index` |
| GET | /applications/create | `applications.create` | `Applicant\ApplicationController@create` |
| POST | /applications | `applications.store` | `@store` |
| GET | /applications/{application}/edit | `applications.edit` | `@edit` |
| PUT | /applications/{application} | `applications.update` | `@update` |
| GET | /applications/{application}/summary | `applications.summary` | `@summary` |
| POST | /applications/{application}/submit | `applications.submit` | `@submit` |
| POST | /applications/{application}/requirements | `applications.requirements.uploadMany` | `Applicant\RequirementController@uploadMany` |
| POST | /applications/{application}/requirements/{type} | `applications.requirements.upload` | `@upload` |
| DELETE | /applications/{application}/requirements/{type} | `applications.requirements.remove` | `@remove` |
| GET | /requirements/{applicationRequirement}/file | `requirements.file` | `@file` |
| GET | /applications/{application}/pay | `applications.pay` | `Applicant\PaymentController@show` |
| POST | /applications/{application}/pay | `applications.pay.start` | `@start` |
| GET | /applications/{application}/payments | `applications.payments` | `@history` |
| GET | /payments/{payment}/receipt | `payments.proof` | `@proof` |
| GET | /applications/{application}/permit | `applications.permit` | `Applicant\PermitController@show` |
| GET | /applications/{application}/permit/download | `applications.permit.download` | `@download` |
| GET | /applications/{application} | `applications.show` | `Applicant\ApplicationController@show` |

Public (guest) relevant routes: `landing` (`/`), `track.show`/`track.lookup` (`/track`), `verify.lookup` (`/verify`), `verify.show` (`/verify/{token}`); auth: `register`, `login`, `logout`, `password.*`.

**`homePath()` redirect logic** (`User::homePath()`): admin → `/admin/dashboard`, bplo → `/bplo/dashboard`, treasurer → `/treasurer/dashboard`, inspector → `/inspector/dashboard`, default (applicant) → `/dashboard`.

Role middleware on the other portals: BPLO `role:bplo,admin` (prefix `bplo`), Treasurer `role:treasurer,admin` (prefix `treasurer`), Inspector `role:inspector,admin` (prefix `inspector`), Admin `role:admin` (prefix `admin`).

---

## 12. Tests (applicant-flow relevant)

Under `tests/Feature`. Command: **`php artisan test`** run from `app/`. **Confirmed baseline this session: 154 passed (519 assertions)**, ~33s.

Most relevant to applicant/portal changes:
- `tests/Feature/Applicant/ApplicationFlowTest.php` — `test_happy_path_from_draft_to_submitted`, `test_cannot_submit_without_required_requirements`, `test_resubmit_from_for_correction_keeps_the_same_tracking_number`.
- `tests/Feature/Applicant/RequirementUploadTest.php`.
- `tests/Feature/Bplo/ReviewFlowTest.php` (status transitions by BPLO).
- `tests/Feature/Treasurer/TreasurerPaymentTest.php` (confirm/reject payment, dashboard, receipt link).
- `tests/Feature/Payment/ManualPaymentTest.php`, `PaymentFlowTest.php`, `SandboxWebhookTest.php`.
- `tests/Feature/Permit/PermitTest.php`, `QrLinkTest.php`.
- `tests/Feature/Notification/NotificationTest.php`.
- `tests/Feature/PublicPages/TrackingTest.php`, `VerificationTest.php`.
- `tests/Feature/Roles/RoleAccessTest.php`, `SeparationOfDutiesTest.php`, `Authorization/RoleMiddlewareTest.php`, `MassAssignmentTest.php`.
- `tests/Feature/TimezoneTest.php` (Asia/Manila), `RaceConditionTest.php` (tracking/permit number uniqueness).

Any dashboard overhaul should keep all 154 green; UI-only changes to `applicant/dashboard.blade.php` and the `$nav` array carry low regression risk, while new routes/controllers (Business Profile, Settings, Renewal) should add their own feature tests.

---

## GAPS — what exists vs what needs new backend

### Already in backend — needs only UI surfacing on the dashboard

| User-requested feature | Backend truth |
|---|---|
| **Control-center dashboard layout** | All data available; `DashboardController` currently passes only `applications` + `notifications`. Needs eager loads (`requirements.requirementType`, `payments`, `permit`) and computed stat counts. UI-only + a controller tweak. |
| **Application Status Tracker** | Fully built: 9 statuses + `<x-process-tracker>` component (per-viewer "what's next"). Drop the component onto the dashboard per application. |
| **Requirements Checklist** | Fully built: `ApplicationRequirement` + `checklist_status` (COMPLETE/MISSING/FOR_CORRECTION) + `correction_note`; upload/replace/remove routes exist. Surface as a card. |
| **Fees / Payment card** | Fully built: `FeeComputationService` + `Payment` model + pay/history routes; amount only shown once `APPROVED_FOR_PAYMENT` (matches "no fixed amount before assessment"). Surface read-only summary + link to `applications.pay`. |
| **Action-required notifications** | Fully built: `Notification` with `read_at`, `application_id` link, titles/bodies per event. CAVEAT: dashboard currently marks all unread read on load — an "Action Required" indicator should be derived from application **status** (e.g., FOR_CORRECTION / APPROVED_FOR_PAYMENT / PERMIT_RELEASED), not from `read_at`. |
| **Permit download + QR** | Fully built: `applications.permit` (SVG QR) + `applications.permit.download` (dompdf), guarded by release. Surface a "Permit Ready" card when `PERMIT_RELEASED`. |
| **Application history** | Fully built: `applications` table + `tracking_number`, `status`, `business_name`, timestamps; `applications.show` detail page. Dashboard already lists them; add filtering/"view all" if desired. UI-only. |
| **Application reference number** | Fully built: `tracking_number` (BANSUD-YYYY-NNNNNN) + public `track.*` routes with 2-factor lookup. Already shown; ensure it's prominent. |

### Needs NEW backend

| User-requested feature | Why it's new |
|---|---|
| **Business Profile (edit)** | `businesses` table + `Business` model + `Application.business_id` exist but are **unused**: no applicant controller/route/form ever reads or writes a Business. Needs new routes + controller + form request + view (no new table strictly required, but the dormant table must be wired up). |
| **Account / Security Settings (change password, update contact)** | No authenticated profile/password-update route exists (only guest `password.*` reset). Needs new routes + controller (update name/contact_number/address on `User`, change password) + view. User columns already exist. |
| **Renewal Reminder** | No `application_type` column, no renewal flow, nothing reads `permit.valid_until` for reminders. Needs new backend: expiry logic (dashboard query and/or scheduled notification) and — if a true renewal application is desired — an `application_type` column + renewal entry point. |
| **Help / FAQ page** | No route/view today. Needs a new static route + Blade view (BPLO contact, office hours, FAQs). Lowest effort; no DB. A messaging feature, if ever wanted, would be a larger new backend (not present). |

### Correctness constraints for the overhaul (do not violate)

1. Use **only** the nine real statuses and their `<x-status-badge>` labels. Do not invent "Paid"/"Released"/"For Payment" strings.
2. **Payment is verified by the Treasurer**, not BPLO. The applicant's status moves APPROVED_FOR_PAYMENT → PAYMENT_PENDING (applicant submits) → PAYMENT_VERIFIED (treasurer) → PERMIT_RELEASED (BPLO).
3. The applicant status is the **same `applications.status`** the staff updates — reading it on the dashboard automatically satisfies the user's "one source of truth" rule. Correction/rejection reasons come from `ApplicationRemark` (CORRECTION/REJECTION) and status-history `note`.
4. Fee amounts come from `FeeComputationService` (active fee_rates); never hard-code a total and never show it before `APPROVED_FOR_PAYMENT`.
5. The dashboard's current "mark all read on load" behavior means an action-required badge should key off application status, not `read_at`.
