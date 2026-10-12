# Technical Design — Automated Business Permit Application and Status Tracking System with QR Code Verification for Bansud, Oriental Mindoro

## Overview

This document specifies the implementation design for a Laravel 13 web application that lets business owners in the Municipality of Bansud, Oriental Mindoro apply for business permits online, track their application status, pay fees through a sandbox payment flow, download an official Mayor's Permit PDF, and lets anyone verify a permit's authenticity by scanning a QR code. The system **supports** the municipal permit process — BPLO/municipal personnel retain all approval authority; the software only records and routes their decisions. Three roles (applicant, BPLO, admin) are enforced by middleware, Gates, and Policies. The user-facing flow is deliberately simple: **1 APPLY ONLINE → 2 TRACK STATUS → 3 PAY FEES → 4 DOWNLOAD & VERIFY**, styled as a blue-and-white, mobile-friendly government portal.

The stack is **locked**: PHP 8.3+, Laravel 13.17, SQLite (the shipped `database/database.sqlite`), Blade + plain CSS for views (no Node/Vite build), `barryvdh/laravel-dompdf` for the permit PDF, `simplesoftwareio/simple-qrcode` for QR generation (SVG, no imagick dependency), and the existing `config/permit.php` for all tunable settings. Authentication uses hand-written Blade controllers on the `Auth` facade — **no Breeze, no Vite, no npm** — because the brief forbids a Node build.

**Notifications must work with no queue worker running.** The shipped `.env` sets `QUEUE_CONNECTION=database` (verified) and a `jobs` table migration exists, so a `ShouldQueue` listener would be parked in the `jobs` table and never execute until `queue:work` is run — silently breaking every status notification. To guarantee the dashboard bell and SMS-log always fire with no worker, this design makes the notification listeners **synchronous (they do NOT implement `ShouldQueue`)**; `NotificationService` writes the `in_app` row and dispatches the SMS-log channel inline within the request. The event/listener seam is still the extension point, so a future deployment can opt a specific listener into `ShouldQueue` once a worker is provisioned, but nothing in the default build depends on a worker. (See finding resolution #1.)

Everything configurable (municipality identity, prefixes, validity months, QR salt, SMS driver, payment methods/mode, upload mimes/size) is already present in `config/permit.php`; this design binds to those exact keys rather than introducing new config.

## Technology Stack (locked)

- **Framework:** Laravel 13.17, PHP 8.3+
- **Database:** SQLite (`database/database.sqlite`), foreign keys enabled (`DB_FOREIGN_KEYS=true`, already default)
- **Views:** Blade templates + a single static CSS file in `public/css/app.css`. No JavaScript build step; small vanilla JS inline where needed (e.g. multi-step form progress). Blue (`#1d4ed8` / `#1e3a8a`) and white palette.
- **Auth:** `Illuminate\Support\Facades\Auth`, custom controllers under `App\Http\Controllers\Auth`. Uses the framework's existing `users`, `password_reset_tokens`, and `sessions` tables.
- **PDF:** `barryvdh/laravel-dompdf` (`Barryvdh\DomPDF\Facade\Pdf`)
- **QR:** `simplesoftwareio/simple-qrcode` (`SimpleSoftwareIO\QrCode\Facades\QrCode`), format `svg` (the underlying `bacon/bacon-qr-code` ships the `SvgImageBackEnd` class in vendor — confirmed by file presence — so the SVG renderer needs no imagick extension; the end-to-end `QrCode::format('svg')->generate()` string-output path is asserted by the PDF QR feature test, not assumed here). **dompdf's inline-SVG support is partial and build-dependent, so the design does not assume it.** See the QR/PDF sections for the embedding strategy and PNG fallback.
- **Queue:** the shipped `.env` uses `QUEUE_CONNECTION=database`. Notification side effects are dispatched via Events + synchronous Listeners that **do not implement `ShouldQueue`**, so they execute inline during the request regardless of the queue connection and need no worker. `ShouldQueue` is reserved as a future opt-in once a worker exists; the default build never requires one. Do **not** rely on changing `QUEUE_CONNECTION` to `sync` — the design is correct with the shipped `database` setting precisely because the listeners are synchronous.
- **Config source of truth:** `config/permit.php`.

**Bootstrap is Node-free (shipped).** The `npm install --ignore-scripts` and `npm run build` lines have been **removed from the `composer.json` `setup` script in this revision**, so `composer run setup` succeeds on a machine with no Node. The resulting `setup` array is exactly:

```json
"setup": [
    "composer install",
    "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
    "@php artisan key:generate",
    "@php artisan migrate --force"
]
```

A later verifier can confirm the fix by checking that no `npm` line remains in `setup`. No Vite entry is used: views reference a committed static stylesheet at `public/css/app.css` directly (`<link href="{{ asset('css/app.css') }}">`), and `public/css/app.css` is checked into the repo (not generated). The `VITE_APP_NAME` key in `.env` is inert and may be ignored.

## Roles & Authorization

A single `role` string column on `users` holds one of three constants defined on `App\Models\User`:

```php
public const ROLE_APPLICANT = 'applicant';
public const ROLE_BPLO      = 'bplo';
public const ROLE_ADMIN     = 'admin';
```

Helper methods `isApplicant()`, `isBplo()`, `isAdmin()` on the User model.

**Middleware.** A `role` route middleware alias (`App\Http\Middleware\EnsureUserHasRole`) registered in `bootstrap/app.php` via `$middleware->alias([...])`. Usage: `->middleware(['auth','role:bplo'])`. It accepts a comma list (`role:bplo,admin`) and aborts 403 on mismatch.

**Gates/Policies.** `ApplicationPolicy`, `PaymentPolicy`, `PermitPolicy` registered through auto-discovery (Laravel 13 maps `App\Models\Application` → `App\Policies\ApplicationPolicy`). Key abilities:
- `ApplicationPolicy@view/update/submit` — applicant may act only on their own application and only while it is in an editable status (`DRAFT`, `FOR_CORRECTION`).
- `ApplicationPolicy@review` — BPLO/admin only.
- `PermitPolicy@download` — the owning applicant or BPLO/admin; **no `update`/`edit` ability exists for anyone**, which is the guard against permit tampering.
- Admin-only management screens are gated by `role:admin` middleware (no per-model policy needed).

Authorization is enforced at two layers: route middleware (coarse, role-level) and Policy checks inside controllers (fine, ownership + status). This redundancy means a mis-wired route still fails the ownership check.

## Status Model (exact constants)

Defined as string constants on `App\Models\Application`. SQLite has no native enum, so the column is `string` and validity is enforced in the service layer, never by the DB.

```php
public const STATUS_DRAFT                = 'DRAFT';
public const STATUS_SUBMITTED            = 'SUBMITTED';
public const STATUS_UNDER_REVIEW         = 'UNDER_REVIEW';
public const STATUS_FOR_CORRECTION       = 'FOR_CORRECTION';
public const STATUS_APPROVED_FOR_PAYMENT = 'APPROVED_FOR_PAYMENT';
public const STATUS_PAYMENT_PENDING      = 'PAYMENT_PENDING';
public const STATUS_PAYMENT_VERIFIED     = 'PAYMENT_VERIFIED';
public const STATUS_PERMIT_RELEASED      = 'PERMIT_RELEASED';
```

**Legal transitions** (owned by `ApplicationWorkflowService`, see Services):

```
DRAFT                -> SUBMITTED                      (applicant submit)
SUBMITTED            -> UNDER_REVIEW                   (BPLO opens/begins review)
UNDER_REVIEW         -> FOR_CORRECTION                 (BPLO return, remark required)
UNDER_REVIEW         -> APPROVED_FOR_PAYMENT           (BPLO approve)
SUBMITTED            -> REJECTED                       (BPLO reject, remark required)
UNDER_REVIEW         -> REJECTED                       (BPLO reject, remark required)
FOR_CORRECTION       -> SUBMITTED                      (applicant resubmits corrections)
APPROVED_FOR_PAYMENT -> PAYMENT_PENDING                (applicant starts payment)
PAYMENT_PENDING      -> APPROVED_FOR_PAYMENT           (payment failed/cancelled, back to payable)
PAYMENT_PENDING      -> PAYMENT_VERIFIED               (payment verified — gateway/BPLO confirm)
PAYMENT_VERIFIED     -> PERMIT_RELEASED                (permit generated & released)
```

`REJECTED` is a terminal status constant (`STATUS_REJECTED = 'REJECTED'`) reachable from **both** `SUBMITTED` and `UNDER_REVIEW`. Both pairs appear in the authoritative transition table above, so a BPLO can reject a brand-new submission directly (no mandatory "begin review" first) — this matches the brief's unconditional Reject capability, and the prose and the enforced table agree. The eight-stage happy path in the brief is the spine; `REJECTED` and the `FOR_CORRECTION`/payment-retry loops are the documented branches.

**Hard invariants enforced in services (not controllers, not DB):**
1. The status may only change through `ApplicationWorkflowService::transition()`, which validates the from→to pair against the table above and throws `InvalidStatusTransitionException` otherwise.
2. BPLO approval can never be skipped: a permit is only generated from `PAYMENT_VERIFIED`, which is only reachable after `APPROVED_FOR_PAYMENT`, which only a BPLO/admin can set.
3. Payment is never marked `PAID`/verified by the Pay button alone — only `PaymentService::verify()` (gateway webhook simulation or BPLO confirmation) does that.
4. Every successful transition writes **both** an `application_status_histories` row and an `audit_logs` row inside the same DB transaction as the state change.

## Data Model

SQLite with foreign key constraints on. All tables use `id()` big-increment PKs and `timestamps()` unless noted. Money is stored as `decimal(12,2)`. Indexes are called out per table. Column type notes use Laravel schema builder vocabulary.

### users (extend existing migration via a new ALTER migration)
The base `users` table already exists (`name`, `email` unique, `email_verified_at`, `password`, `remember_token`, timestamps). A new migration `add_profile_fields_to_users_table` adds:

| column | type | notes |
|---|---|---|
| role | string, default `'applicant'`, index | one of the ROLE_* constants |
| contact_number | string(32), nullable | PH mobile |
| address | string, nullable | |
| is_active | boolean, default true | admin can deactivate; blocks login |

`User` model: keep the existing attribute-based style for consistency with the shipped model (`#[Fillable(['name','email','password'])]`, `#[Hidden(['password','remember_token'])]`, and a `casts()` method). Extend the attribute to `#[Fillable(['name','email','password','contact_number','address','is_active'])]`. **`role` is deliberately EXCLUDED from fillable** — it must never be mass-assignable from request input (an applicant could otherwise send `role=admin` through any create/update path). `role` is always set explicitly in server code (`$user->role = User::ROLE_APPLICANT;` on registration; admin sets it through a dedicated, policy-gated `UserController` action). Add `'is_active' => 'boolean'` to the `casts()` array (alongside the existing `email_verified_at`/`password` casts). Relationships: `hasMany(Business)`, `hasMany(Application)`, `hasMany(Notification)`, `hasMany(AuditLog, 'actor_id')`.

### businesses
A business profile belonging to an applicant (one applicant may own several businesses; one application targets exactly one business snapshot).

| column | type | notes |
|---|---|---|
| id | PK | |
| user_id | FK → users, cascade on delete, index | owner/applicant |
| name | string, index | business name |
| address | string | |
| barangay | string, index | |
| nature | string | business type/nature |
| ownership_type | string | sole proprietorship / partnership / corporation / cooperative |
| dti_sec_type | string, nullable | 'DTI' or 'SEC' |
| dti_sec_number | string, nullable | registration number |
| number_of_employees | unsignedInteger, default 0 | |
| timestamps | | |

Relationships: `belongsTo(User)`, `hasMany(Application)`.

### requirement_types (seeded, admin-managed)
Catalog of required documents.

| column | type | notes |
|---|---|---|
| id | PK | |
| name | string | e.g. "Barangay Business Clearance" |
| code | string, unique | slug e.g. `barangay_clearance` |
| is_required | boolean, default true | "Previous Permit" seeded false |
| is_active | boolean, default true | |
| sort_order | unsignedInteger, default 0 | checklist ordering |
| timestamps | | |

Seeded rows: Barangay Business Clearance (required), DTI/SEC Registration (required), Valid ID (required), Previous Permit (optional), Other (optional). Relationships: `hasMany(ApplicationRequirement)`.

### applications
The core aggregate. Holds a **snapshot** of applicant + business info at submission time (so later edits to the business profile don't rewrite history) plus the live status.

| column | type | notes |
|---|---|---|
| id | PK | |
| user_id | FK → users, index | applicant |
| business_id | FK → businesses, nullable, index | source business profile |
| tracking_number | string, unique, nullable, index | `BANSUD-{YEAR}-{000001}`; null while DRAFT, set on submit |
| status | string, index, default `DRAFT` | STATUS_* constant |
| applicant_name | string | snapshot |
| contact_number | string(32) | snapshot |
| email | string | snapshot |
| business_name | string, index | snapshot |
| business_address | string | snapshot |
| business_nature | string | snapshot |
| ownership_type | string | snapshot |
| dti_sec_type | string, nullable | snapshot |
| dti_sec_number | string, nullable | snapshot |
| barangay | string, index | snapshot |
| number_of_employees | unsignedInteger, default 0 | snapshot |
| submitted_at | timestamp, nullable | set on first submit |
| reviewed_at | timestamp, nullable | last BPLO action |
| timestamps | | |

Composite index `(status, created_at)` for the BPLO dashboard sort. Relationships: `belongsTo(User)`, `belongsTo(Business)`, `hasMany(ApplicationRequirement)`, `hasMany(ApplicationStatusHistory)`, `hasMany(ApplicationRemark)`, `hasMany(Payment)`, `hasOne(Permit)`.

### application_requirements
One row per requirement type attached to an application, carrying the uploaded file + per-item checklist state.

| column | type | notes |
|---|---|---|
| id | PK | |
| application_id | FK → applications, cascade, index | |
| requirement_type_id | FK → requirement_types, index | |
| original_filename | string, nullable | user's filename |
| stored_path | string, nullable | path on `local` (private) disk |
| mime | string, nullable | |
| size_kb | unsignedInteger, nullable | |
| checklist_status | string, default `MISSING` | `COMPLETE` / `MISSING` / `FOR_CORRECTION` |
| correction_note | string, nullable | per-item BPLO note |
| uploaded_at | timestamp, nullable | |
| timestamps | | |

Unique index `(application_id, requirement_type_id)` — one slot per requirement per application. Checklist constants on the model: `CHECK_COMPLETE`, `CHECK_MISSING`, `CHECK_FOR_CORRECTION`. Relationships: `belongsTo(Application)`, `belongsTo(RequirementType)`.

### application_status_histories
Immutable append-only trail of every status change.

| column | type | notes |
|---|---|---|
| id | PK | |
| application_id | FK → applications, cascade, index | |
| from_status | string, nullable | null for initial |
| to_status | string | |
| actor_id | FK → users, nullable, index | who caused it (null = system) |
| actor_role | string, nullable | denormalized for display |
| note | text, nullable | optional context |
| created_at | timestamp | append-only: `$table->timestamp('created_at')->useCurrent();` only — **no `updated_at`** (set `public $timestamps = false;` on the model and manage `created_at` explicitly) |

Relationships: `belongsTo(Application)`, `belongsTo(User,'actor_id')`.

### application_remarks
BPLO remarks / correction requests shown to the applicant.

| column | type | notes |
|---|---|---|
| id | PK | |
| application_id | FK → applications, cascade, index | |
| author_id | FK → users, index | BPLO/admin |
| type | string | `GENERAL` / `CORRECTION` / `REJECTION` |
| body | text | remark text (required for return/reject) |
| timestamps | | |

Relationships: `belongsTo(Application)`, `belongsTo(User,'author_id')`.

### fee_types (seeded, admin-managed)
| column | type | notes |
|---|---|---|
| id | PK | |
| name | string | "Business Tax", "Permit Fee", "Regulatory Fees", "Other Fees" |
| code | string, unique | `business_tax`, `permit_fee`, `regulatory_fees`, `other_fees` |
| is_active | boolean, default true | |
| sort_order | unsignedInteger, default 0 | breakdown ordering |
| timestamps | | |

Relationships: `hasMany(FeeRate)`.

### fee_rates (admin-editable; never hard-coded)
Each fee type can have one active rate; keeping it as a separate table allows historical rates and future rules.

| column | type | notes |
|---|---|---|
| id | PK | |
| fee_type_id | FK → fee_types, cascade, index | |
| label | string | e.g. "Standard business tax" |
| amount | decimal(12,2) | base amount |
| calc_type | string, default `fixed` | `fixed` (future: `percent`) |
| is_active | boolean, default true, index | |
| effective_from | date, nullable | |
| timestamps | | |

Relationships: `belongsTo(FeeType)`. The `FeeComputationService` reads active rates — amounts are **data**, never constants in code.

### payments
One payment attempt per row (an application may have several attempts; the latest `PAID`/verified one is authoritative).

| column | type | notes |
|---|---|---|
| id | PK | |
| application_id | FK → applications, cascade, index | |
| tracking_number | string, index | denormalized copy |
| amount | decimal(12,2) | total due at time of attempt |
| breakdown_json | json | computed line items snapshot |
| method | string | `gcash` / `maya` / `qrph` |
| reference_number | string, nullable, index | gateway/transaction ref |
| status | string, default `PENDING`, index | PENDING/PROCESSING/PAID/FAILED/CANCELLED |
| verification_status | string, default `UNVERIFIED` | `UNVERIFIED` / `VERIFIED` |
| payment_date | timestamp, nullable | when PAID |
| verified_at | timestamp, nullable | when VERIFIED |
| verified_by | FK → users, nullable | BPLO who confirmed, or null for simulated webhook |
| timestamps | | |

Payment status constants on `Payment`: `STATUS_PENDING`, `STATUS_PROCESSING`, `STATUS_PAID`, `STATUS_FAILED`, `STATUS_CANCELLED`; verification `VERIFY_UNVERIFIED`, `VERIFY_VERIFIED`. Relationships: `belongsTo(Application)`, `hasMany(PaymentTransaction)`.

### payment_transactions
Low-level log of every gateway interaction/state change for a payment (audit of the money flow, distinct from app-level audit_logs).

| column | type | notes |
|---|---|---|
| id | PK | |
| payment_id | FK → payments, cascade, index | |
| event | string | `initiated` / `processing` / `webhook_received` / `verified` / `failed` / `cancelled` |
| gateway | string | `sandbox` (from `permit.payment.mode`) |
| payload_json | json, nullable | sanitized request/response |
| created_at | timestamp | |

Relationships: `belongsTo(Payment)`.

### permits
Generated only after `PAYMENT_VERIFIED`.

| column | type | notes |
|---|---|---|
| id | PK | |
| application_id | FK → applications, cascade, unique, index | one permit per application |
| permit_number | string, unique, index | `BP-{YEAR}-{000001}` |
| verification_token | string(64), unique, index | secure random, QR target |
| business_name | string | snapshot |
| owner_name | string | snapshot |
| business_address | string | snapshot |
| business_nature | string | snapshot |
| issued_at | date | |
| valid_until | date | `issued_at` + `permit.valid_months` |
| status | string, default `ACTIVE`, index | `ACTIVE` / `REVOKED` |
| pdf_path | string, nullable | cached PDF on `local` disk |
| generated_by | FK → users, nullable | BPLO/admin or system |
| timestamps | | |

Relationships: `belongsTo(Application)`, `hasMany(QrVerification)`. No `update` policy ability — regeneration goes through `PermitService::regenerate()` (BPLO/admin), which supersedes the PDF/token but is itself audited; applicants have read-only access.

### qr_verifications
One row per public scan/verification hit (for monitoring + rate-limit forensics).

| column | type | notes |
|---|---|---|
| id | PK | |
| permit_id | FK → permits, nullable, `nullOnDelete()`, index | null when token not found; `nullOnDelete` (not cascade) so forensic scan rows survive even if a permit row is ever removed. Note: permits are never hard-deleted in normal operation — revocation flips `status` to `REVOKED` — so this FK action is a safety net, not an expected path. |
| token | string, index | token presented |
| result | string | `VALID` / `EXPIRED` / `REVOKED` / `NOT_FOUND` |
| ip_address | string(45), nullable | |
| user_agent | string, nullable | |
| created_at | timestamp | |

Relationships: `belongsTo(Permit)`.

### notifications
In-app notifications (custom table, not Laravel's default notifications table, to keep the shape simple and SMS-aware).

| column | type | notes |
|---|---|---|
| id | PK | |
| user_id | FK → users, cascade, index | recipient |
| application_id | FK → applications, nullable, index | related app |
| type | string | event key e.g. `application.submitted` |
| title | string | |
| body | text | |
| channel | string, default `in_app` | `in_app` / `sms` |
| sms_status | string, nullable | `queued` / `sent` / `logged` / `failed` |
| read_at | timestamp, nullable | |
| timestamps | | |

Index `(user_id, read_at)` for the unread badge. Relationships: `belongsTo(User)`, `belongsTo(Application)`.

### audit_logs
System-wide activity log for admin monitoring.

| column | type | notes |
|---|---|---|
| id | PK | |
| actor_id | FK → users, nullable, index | null = system/guest |
| action | string, index | e.g. `application.status_changed`, `fee_rate.updated`, `permit.regenerated`, `auth.login` |
| subject_type | string, nullable | morph-ish class name |
| subject_id | unsignedBigInteger, nullable | |
| meta_json | json, nullable | before/after, context (no secrets) |
| ip_address | string(45), nullable | |
| created_at | timestamp | |

Composite index `(subject_type, subject_id)`. Relationships: `belongsTo(User,'actor_id')`.

### number_sequences (atomic per-year counters for tracking & permit numbers)
Backs the race-safe number generation (see TrackingNumberService / Generation section). One row per scope+year.

| column | type | notes |
|---|---|---|
| id | PK | |
| scope | string | `tracking` or `permit` |
| year | unsignedSmallInteger | e.g. 2026 |
| last_value | unsignedInteger, default 0 | last issued sequence for scope+year |
| timestamps | | |

Unique index `(scope, year)`. Incremented atomically (`UPDATE ... SET last_value = last_value + 1`) inside the submit/release transaction. No model relationships; accessed only by the number services.

## Services (responsibilities & interfaces)

All business rules live in `App\Services`. Controllers stay thin: validate (Form Request) → call service → return view/redirect. Services own DB transactions.

### TrackingNumberService
`generate(int $year): string` returns `BANSUD-{YEAR}-{000001}`, per-year sequential and zero-padded to 6 digits. Prefix comes from `config('permit.tracking_prefix')`; format `sprintf('%s-%d-%06d', $prefix, $year, $seq)`.

**Authoritative safety mechanism = the UNIQUE index + a bounded retry loop**, NOT a row lock. The review confirmed SQLite treats `FOR UPDATE` as a no-op and `config/database.php` sets `transaction_mode => 'DEFERRED'`, so a `lockForUpdate()` on a count query does not serialize two concurrent submits. The design therefore does not depend on it. Instead:
- The `number_sequences` table (added to the data model, see below) holds one row per `(scope, year)` — e.g. `('tracking', 2026)` — with a `last_value` integer. `generate()` runs `DB::transaction()` and does an **atomic** `UPDATE number_sequences SET last_value = last_value + 1 WHERE scope=? AND year=?` then reads the new value (upserting the row at `last_value=1` on first use for the year). The atomic increment is the primary guarantee of gap-free, strictly increasing per-year sequences; SQLite's single-writer model serializes these writes cleanly.
- As defense-in-depth, `applications.tracking_number` carries a UNIQUE index. If a formatted value ever collides on insert, the caller catches `QueryException` (unique violation) and retries the whole generate-and-insert up to **5 attempts**, recomputing the sequence each time; only after the 5th failure does it surface a 500. This keeps the system correct even under a burst of concurrent submits.

### PermitNumberService (or a `permit_number` method on PermitService)
`generate(int $year): string` → `BP-{YEAR}-{000001}` using the **same `number_sequences` atomic-increment + UNIQUE-index + bounded-retry** pattern as `TrackingNumberService`, with scope `'permit'`, the UNIQUE index on `permits.permit_number`, and prefix `config('permit.permit_prefix')`.

### ApplicationWorkflowService
Owns the status machine. `transition(Application $app, string $to, ?User $actor, ?string $note): void` — validates the pair against the legal-transition table, updates `status` (+ `submitted_at`/`reviewed_at` where relevant), writes an `application_status_histories` row and an `audit_logs` row, and fires the matching domain event — all inside one `DB::transaction()`. Throws `InvalidStatusTransitionException` (→ 422/redirect-back with error) on illegal pairs. Convenience methods: `submit()`, `beginReview()`, `approveForPayment()`, `returnForCorrection(remark)`, `reject(remark)`, `markPaymentPending()`, `markPaymentVerified()`, `releasePermit()`.

### FeeComputationService
`computeFor(Application $app): FeeBreakdown` reads active `fee_rates` joined to active `fee_types`, builds an ordered line-item collection (`[code, name, amount]`) and a `total`. Returns a lightweight DTO/array `{ lines: [...], total: decimal }`. No amounts are hard-coded — an empty rate table yields a zero total and a logged warning. The breakdown is snapshotted into `payments.breakdown_json` at payment-attempt creation so later rate edits don't change an in-flight payment.

### PaymentService + PaymentGateway interface + SandboxGateway
```php
interface PaymentGateway {
    public function initiate(Payment $p): GatewayResult;   // PENDING -> PROCESSING, returns ref
    public function verify(Payment $p, array $callback): GatewayResult; // confirm
}
```
`SandboxGateway implements PaymentGateway` — clearly labeled; `initiate()` sets `PROCESSING` and returns a fake-but-marked reference (`SANDBOX-{uuid}`); it **never** returns success/PAID. `verify()` is the only path to `PAID` + `VERIFIED` and is invoked by an explicit action (simulated webhook endpoint or BPLO "Confirm payment" button), simulating the real gateway's asynchronous confirmation.

`PaymentService`:
- `startPayment(Application $app, string $method): Payment` — guards `status === APPROVED_FOR_PAYMENT`, snapshots fee breakdown, creates `Payment(PENDING)`, calls gateway `initiate()`, logs `payment_transactions`, transitions app → `PAYMENT_PENDING`. Returns payment.
- `verify(Payment $p, array $callback, ?User $actor): void` — calls gateway `verify()`; on success sets `status=PAID`, `verification_status=VERIFIED`, `payment_date`, `verified_at`; writes `payment_transactions`; transitions app → `PAYMENT_VERIFIED`; fires `PaymentConfirmed`. On failure sets `FAILED`/`CANCELLED` and transitions app back to `APPROVED_FOR_PAYMENT`. The Pay button alone (which only calls `startPayment`) can never reach `PAID` — enforced here.
- Gateway resolved from container keyed on `config('permit.payment.mode')`; only `sandbox` is bound now, leaving a real binding slot.

### PermitService
- `release(Application $app, ?User $actor): Permit` — **guards** `status === PAYMENT_VERIFIED` (throws otherwise, which is the "never generate a valid permit before approval + verified payment" invariant); generates `permit_number` and a 64-char `verification_token` via `Str::random(64)` (checked unique); snapshots business fields; sets `issued_at = today`, `valid_until = issued_at->addMonths(config('permit.valid_months'))`; renders + stores the PDF; transitions app → `PERMIT_RELEASED`; fires `PermitReleased`. All in one transaction.
- `renderPdf(Permit $p): string` — builds the Mayor's Permit via dompdf from a Blade view, embeds the QR via `qrDataUri()` (base64 data-URI `<img>`), returns storage path on the private `local` disk.
- `qrDataUri(string $url): string` — returns an **SVG** base64 data-URI (`data:image/svg+xml;base64,…`) for the QR of `$url`, produced by `QrCode::format('svg')->size(...)->generate($url)` (bacon-qr-code's SVG backend, which needs no `imagick`/GD extension — the only QR backend available on this machine). There is **no PNG path**: PNG via simple-qrcode would require the `imagick` extension, which is not installed, so PNG is intentionally not used. This single method centralizes PDF QR rendering; the Blade view only consumes the returned data-URI. dompdf renders the SVG `<img>` data-URI; a PDF feature test asserts the generated permit PDF is non-empty and contains the embedded QR.
- `regenerate(Permit $p, ?User $actor): Permit` — BPLO/admin only; issues a fresh token + PDF (invalidating old QR), audited. Does not change permit_number.

### NotificationService + SmsChannel
`SmsChannel` interface `{ send(string $to, string $message): SmsResult }`. Default binding `LogSmsChannel` (writes to the Laravel log via `Log::channel()` and returns `logged`) selected when `config('permit.sms.driver') === 'log'`. A real driver is wired by binding another implementation keyed on the driver string — no caller changes.

`NotificationService::notify(User $user, string $type, array $data)` — always creates an `in_app` notification row; if the user has a `contact_number` and SMS is enabled, also builds an SMS and dispatches through the resolved `SmsChannel`, recording `sms_status`. The whole system works end-to-end with `driver=log` and no provider. Called from **synchronous** listeners (not `ShouldQueue`), so both the in-app row and the SMS-log write complete inline during the request even though `QUEUE_CONNECTION=database` — no worker required.

### AuditLogger
Thin helper `log(string $action, ?Model $subject, array $meta, ?User $actor)` used by all services. Never records secrets (payment payloads are sanitized to ref + status only).

## Events & Listeners

Domain events fired by services: `ApplicationSubmitted`, `ApplicationUnderReview`, `ApplicationReturnedForCorrection`, `ApplicationApprovedForPayment`, `PaymentConfirmed`, `PermitReleased`, and `ApplicationRejected`. Each maps to a `SendStatusNotification` listener that calls `NotificationService::notify()` with the right template. This satisfies the required notification triggers — submitted, under review, for correction, approved for payment, payment confirmed, permit released — **plus a rejection notification**: `ApplicationRejected` is deliberately mapped to `SendStatusNotification` with an `application.rejected` template (title "Application Rejected", body carrying the BPLO rejection remark) so a rejected applicant is informed through the same in-app + SMS-log channels as every other transition. Rejection is thus a notified status like the rest, not a silent terminal state. Listeners auto-discovered (Laravel 13 event discovery) or registered in `AppServiceProvider::boot()`.

**The listener does NOT implement `ShouldQueue`** — it runs synchronously inline with the dispatching request. This is deliberate: the shipped `.env` has `QUEUE_CONNECTION=database`, so a queued listener would sit unprocessed in the `jobs` table until a worker runs, which the brief does not require. Running synchronously means the in-app notification row and the SMS-log write happen immediately and the system is fully functional with no `queue:work`. If a worker is later provisioned, a listener may be opted into `ShouldQueue` without changing any caller or service code.

## Route Map (by role)

All routes in `routes/web.php`. Grouped by middleware. Controllers under `App\Http\Controllers` with sub-namespaces `Auth`, `Applicant`, `Bplo`, `Admin`, `Public`.

### Public (guest-accessible, rate-limited)
| method | URI | controller@action | notes |
|---|---|---|---|
| GET | `/` | `Public\LandingController@index` | landing page, 4-step explainer |
| GET | `/track` | `Public\TrackingController@show` | form: tracking number + email/last name |
| POST | `/track` | `Public\TrackingController@lookup` | throttle:6,1; shows status only, no PII |
| GET | `/verify/{token}` | `Public\VerificationController@show` | throttle:30,1; VALID/EXPIRED/REVOKED/NOT FOUND |

### Auth (guest)
| GET/POST | `/register` | `Auth\RegisterController` | applicants only; role forced to `applicant` |
| GET/POST | `/login` | `Auth\LoginController` | throttle:5,1 on POST |
| POST | `/logout` | `Auth\LoginController@destroy` | |
| GET/POST | `/forgot-password`, `/reset-password` | `Auth\PasswordResetController` | uses existing `password_reset_tokens` |

### Applicant (`auth`, `role:applicant`)
| GET | `/dashboard` | `Applicant\DashboardController@index` | applications list + status + notifications |
| GET | `/applications/create` | `Applicant\ApplicationController@create` | start draft |
| POST | `/applications` | `Applicant\ApplicationController@store` | create DRAFT |
| GET | `/applications/{application}/edit` | `@edit` | multi-section form (policy: own + editable) |
| PUT | `/applications/{application}` | `@update` | save draft/corrections |
| POST | `/applications/{application}/requirements/{type}` | `Applicant\RequirementController@upload` | file upload |
| DELETE | `/applications/{application}/requirements/{type}` | `@remove` | remove before submit |
| GET | `/applications/{application}/summary` | `@summary` | review-before-submit page |
| POST | `/applications/{application}/submit` | `@submit` | → SUBMITTED, tracking number |
| GET | `/applications/{application}` | `@show` | status tracker + remarks |
| GET | `/applications/{application}/pay` | `Applicant\PaymentController@show` | only if APPROVED_FOR_PAYMENT |
| POST | `/applications/{application}/pay` | `@start` | startPayment → PAYMENT_PENDING |
| GET | `/applications/{application}/payments` | `@history` | payment history |
| GET | `/applications/{application}/permit` | `Applicant\PermitController@show` | view permit + QR |
| GET | `/applications/{application}/permit/download` | `@download` | PDF (policy download) |
| GET | `/requirements/{applicationRequirement}/file` | `Applicant\RequirementController@file` | streamed private file (own) |

### BPLO (`auth`, `role:bplo,admin`)
| GET | `/bplo/dashboard` | `Bplo\DashboardController@index` | the 7 counters |
| GET | `/bplo/applications` | `Bplo\ApplicationController@index` | search/filter/sort/paginate |
| GET | `/bplo/applications/{application}` | `@show` | full review view |
| POST | `/bplo/applications/{application}/review` | `@beginReview` | SUBMITTED → UNDER_REVIEW |
| POST | `/bplo/applications/{application}/approve` | `@approve` | → APPROVED_FOR_PAYMENT (fees shown) |
| POST | `/bplo/applications/{application}/return` | `@returnForCorrection` | remark required |
| POST | `/bplo/applications/{application}/reject` | `@reject` | remark required |
| POST | `/bplo/applications/{application}/requirements/{req}/flag` | `@flagRequirement` | set per-item checklist |
| GET | `/bplo/requirements/{applicationRequirement}/file` | `@file` | streamed private file (staff) |
| POST | `/bplo/payments/{payment}/confirm` | `Bplo\PaymentController@confirm` | simulate verify → PAYMENT_VERIFIED |
| POST | `/bplo/applications/{application}/release` | `Bplo\PermitController@release` | PermitService::release |
| POST | `/bplo/permits/{permit}/regenerate` | `@regenerate` | fresh token + PDF |

### Admin (`auth`, `role:admin`)
| GET | `/admin/dashboard` | `Admin\DashboardController@index` | system monitoring |
| resource | `/admin/users` | `Admin\UserController` | manage users + BPLO accounts, activate/deactivate, set role |
| resource | `/admin/requirement-types` | `Admin\RequirementTypeController` | |
| resource | `/admin/fee-types` | `Admin\FeeTypeController` | |
| resource | `/admin/fee-rates` | `Admin\FeeRateController` | edit amounts |
| GET | `/admin/settings` | `Admin\SettingController@show` | **strictly read-only** display of current `config('permit.*')` values (municipality identity, prefixes, validity months, SMS driver, payment mode/methods, upload mimes/size). It never writes to `.env` or `config/*` from an HTTP request — runtime `.env` rewriting is a reliability/security hazard and is out of scope. Operationally tunable data (requirement types, fee types, fee rates) is edited through their own admin CRUD screens, which persist to the database; environment-level knobs are changed only by an operator editing `.env` on the server. |
| GET | `/admin/audit-logs` | `Admin\AuditLogController@index` | filter/paginate activity |

### Sandbox payment webhook (simulated gateway callback)
| POST | `/sandbox/payments/{payment}/webhook` | `Public\SandboxWebhookController@handle` | throttle:30,1; HMAC signature check (see below); drives `PaymentService::verify()` to simulate async confirmation. Clearly labeled sandbox-only (guarded by `permit.payment.mode === 'sandbox'`). |

**Sandbox webhook signature scheme (exact).** The caller sends the signature in header `X-Sandbox-Signature`. The controller computes `hash_hmac('sha256', $request->getContent() /* raw body */, config('permit.payment.webhook_secret'))` and compares it to the header value with `hash_equals()` (constant-time). Empty-secret policy (defense-in-depth for any deployment that clears the secret): if `config('permit.payment.webhook_secret')` is empty or null, the webhook route returns **503** with body `"sandbox webhook disabled; set PAYMENT_WEBHOOK_SECRET"` and performs **no** state change — it never treats an empty-vs-empty comparison as valid. In that disabled state `PAYMENT_VERIFIED` remains reachable through the BPLO **"Confirm payment"** action (`POST /bplo/payments/{payment}/confirm`), which does not depend on the webhook secret.

**Shipped default (this revision):** both `.env` and `.env.example` now ship a non-empty `PAYMENT_WEBHOOK_SECRET=sandbox-webhook-secret-change-me`, so on a fresh checkout — or a fresh `composer run setup` that copies `.env.example` → `.env` — the webhook is **live, not 503-disabled**, and is exercisable out of the box; operators rotate the value in `.env` for real deployments. The webhook feature test therefore asserts a **200 verify** with the seeded secret (correct `X-Sandbox-Signature`) and a **503** only in a test that explicitly blanks the secret. Bad/missing signature with a configured secret → **401**, no state change.

## File Upload & Private Storage

Uploads use the existing `local` disk, whose root is already `storage_path('app/private')` and which is **not** symlinked into `public/` — so files are never reachable by URL. Design points:

- **Validation (server-side, Form Request `UploadRequirementRequest`):** `file` required, `mimes:pdf,jpg,jpeg,png` (from `config('permit.uploads.mimes')`), `max:4096` KB (from `config('permit.uploads.max_kb')`). The mime list and size are read from config so admins/config can tune them. Reject with 422 + field error on failure.
- **Storage path:** `applications/{application_id}/{requirement_code}-{uuid}.{ext}` on the `local` disk via `$file->storeAs(...)`. Store `original_filename`, `mime`, `size_kb`, `stored_path`, set `checklist_status = COMPLETE`, `uploaded_at = now()`.
- **Serving:** downloads/views go through authorized controllers (`RequirementController@file` for applicant-own, `Bplo\ApplicationController@file` for staff) which run the Policy then `return Storage::disk('local')->download($path, $originalName)`. No public URL, no `storage:link` dependency for requirements. The permit PDF is served the same way through `PermitController@download`.
- **Resubmission:** on `FOR_CORRECTION`, the applicant may re-upload only the slots flagged `FOR_CORRECTION`/`MISSING`; uploading replaces that slot's file (old file deleted from disk inside the transaction) and resets its `checklist_status` to `COMPLETE`. The applicant never restarts the application — `@submit` from `FOR_CORRECTION` re-enters at `SUBMITTED` keeping the same `tracking_number`.

## Tracking-Number & Permit-Number Generation

Both are per-year, zero-padded to 6 digits, and sequential. The guarantee rests on two mechanisms, in order of authority:

1. **Atomic sequence counter (primary).** A dedicated `number_sequences` table holds one row per `(scope, year)` where scope is `tracking` or `permit`. Generation runs inside `DB::transaction()` and performs an atomic `UPDATE ... SET last_value = last_value + 1 WHERE scope=? AND year=?` (inserting the row at `last_value = 1` on the first submit of a new year), then reads back the incremented value and formats it with `sprintf`. This avoids the read-then-write (`count()+1`) window entirely. The review correctly noted that SQLite ignores `FOR UPDATE` and that `transaction_mode = DEFERRED` means a `lockForUpdate()` count would not serialize concurrent submits — so this design does **not** use lock-and-count; the atomic increment is what prevents two submits from reading the same number.
2. **UNIQUE index + bounded retry (backstop).** `applications.tracking_number` and `permits.permit_number` each carry a UNIQUE index. On the rare chance a value still collides on insert, the caller catches the unique-violation `QueryException` and retries the full generate-and-insert up to **5 attempts**, recomputing each time; a 500 is surfaced only after all 5 fail. (This replaces the earlier "single retry," which was insufficient under a concurrent burst.)

Prefixes and formats come from `config('permit.tracking_prefix')` = `BANSUD` and `config('permit.permit_prefix')` = `BP`. Examples: `BANSUD-2026-000001`, `BP-2026-000001`. The sequence row is incremented in the same transaction that sets `submitted_at` (tracking) or creates the permit (permit number), so a rolled-back submit does not consume a number in the committed state.

## QR Token, Verification & Data Minimization

Each permit stores `verification_token` = `Str::random(64)` (unguessable, unique, **never** the DB id). The QR encodes the absolute URL `config('app.url').'/verify/'.$token`.

**The scan target depends on a well-formed `APP_URL`.** Because the QR is built from `config('app.url')`, a malformed value produces an unreachable scan URL and defeats scan-to-verify. The `.env.example` `APP_URL` has been corrected in this revision from the malformed `http://localhost:8000:8000` (duplicate port) to `http://localhost:8000`, matching the already-correct committed `.env`; so a fresh `composer run setup` (which copies `.env.example` → `.env`) now yields a valid verify URL. A feature test asserts the generated QR URL matches `^https?://[^:]+(:\d+)?/verify/[A-Za-z0-9]+$` (a single, optional port and the `/verify/{token}` path), catching any future `APP_URL` regression. Operators deploying to a real host must set `APP_URL` to that host's base URL so printed permits carry a scannable link.

**QR rendering strategy (dompdf-safe).** On the on-screen (browser) permit view, the QR is rendered inline as SVG by simple-qrcode — browsers render SVG natively. For the **PDF**, `PermitService::qrDataUri()` produces the QR as a **base64 SVG data-URI `<img>`**: it generates the SVG string via `QrCode::format('svg')->generate($url)` and embeds it as `data:image/svg+xml;base64,{base64}` inside an `<img>` tag in the Blade PDF view. **SVG is the only QR format used** — simple-qrcode's PNG writer requires the `imagick` extension, which is not installed on this machine, so there is no PNG path and no `endroid/qr-code` dependency. The SVG backend (`bacon-qr-code`'s `SvgImageBackEnd`) needs no imagick/GD and is confirmed present in vendor. The chosen rendering is centralized in `PermitService::qrDataUri()` so the format lives in one method with no view edits. A feature test asserts the generated PDF is non-empty and that the permit model carries a valid verification token / scannable `/verify/{token}` URL (see Testability). `config('permit.qr_hash_salt')` is available to additionally HMAC the token in the stored form if desired; the baseline uses the random token directly since 64 random chars already resist enumeration.

The public page `/verify/{token}` looks up the permit by token and classifies: `VALID` (status `ACTIVE` and `today <= valid_until`), `EXPIRED` (`ACTIVE` but past `valid_until`), `REVOKED` (status `REVOKED`), or `NOT_FOUND` (no match). It renders a mobile-friendly result showing **only** permit number, business name, business address, status, issued date, and valid-until. It never shows contact number, email, DTI/SEC numbers, or any private ID — satisfying RA 10173 (Data Privacy Act) data-minimization. Every hit writes a `qr_verifications` row (permit_id nullable for NOT_FOUND) with IP/user-agent for monitoring. The route is rate-limited (`throttle:30,1`) to deter token enumeration.

The public `/track` page requires the tracking number **plus** a second factor (email or last name) and likewise shows only status, latest-update timestamp, BPLO remarks, missing-document and correction notes, payment status, and permit status — no sensitive personal fields — and is rate-limited (`throttle:6,1`).

## Notification / SMS-Log Design

`NotificationService` always writes an `in_app` notification row (shown as a bell/badge on every authenticated dashboard, `read_at` toggled on view). SMS is an optional second channel resolved from `config('permit.sms.driver')`. The default driver `log` binds `LogSmsChannel`, which writes the composed message to the Laravel log and records `sms_status = logged` — so the full notification feature works with zero external provider and no credentials. A real provider is added later by binding a new `SmsChannel` implementation keyed on its driver name and setting `SMS_DRIVER` + the `permit.sms` credentials in `.env`; no caller code changes. Messages are triggered by the domain events for: submitted, under review, for correction, approved for payment, payment confirmed, permit released, and rejected (the `application.rejected` template carrying the BPLO remark). Dispatch is through **synchronous** listeners (not `ShouldQueue`), so with the shipped `QUEUE_CONNECTION=database` setting the in-app row and SMS-log write still execute inline during the request — no `queue:work` worker is required for the feature to function.

## Fees & Payment Flow (end to end)

1. BPLO approves → app `APPROVED_FOR_PAYMENT`. The applicant's permit/payment page now calls `FeeComputationService::computeFor()` and renders the breakdown: Business Tax, Permit Fee, Regulatory Fees, Other Fees, and **TOTAL** (summed automatically from active `fee_rates`). The amount is payable only in this status.
2. Applicant picks a method (only methods enabled in `config('permit.payment.methods')` are offered) and clicks Pay → `PaymentService::startPayment()` snapshots the breakdown into `payments.breakdown_json`, creates `Payment(PENDING)`, `SandboxGateway::initiate()` moves it to `PROCESSING` with a `SANDBOX-…` reference, app → `PAYMENT_PENDING`. **No permit yet.**
3. Confirmation is a separate, explicit step via one of two paths, both of which call `PaymentService::verify()` → sets `PAID` + `VERIFIED`, app → `PAYMENT_VERIFIED`, fires `PaymentConfirmed`:
   - **BPLO "Confirm payment"** (`POST /bplo/payments/{payment}/confirm`) — always available, no secret needed; this is the primary/guaranteed path.
   - **Simulated gateway webhook** (`POST /sandbox/payments/{payment}/webhook`) — verified with `hash_hmac('sha256', rawBody, permit.payment.webhook_secret)` compared via `hash_equals()` against header `X-Sandbox-Signature`. Both `.env` and `.env.example` now ship a non-empty secret, so this path is live out of the box; a configured-but-wrong signature returns 401, and only if an operator blanks the secret does the route return 503 (disabled) and verification falls back to the BPLO action. This models the real gateway's asynchronous callback without ever letting the Pay button self-verify.
4. `PermitService::release()` (guarded on `PAYMENT_VERIFIED`) generates the permit record, PDF, and QR, app → `PERMIT_RELEASED`, fires `PermitReleased`. Only now can the applicant download the Mayor's Permit.

## Mayor's Permit PDF

Rendered with dompdf from a dedicated Blade view (`resources/views/permits/pdf.blade.php`) in a formal government-document layout: header "Municipality of Bansud / Oriental Mindoro" (from `config('permit.municipality')` and `config('permit.province')`), permit number, business name, owner, address, nature/type, date issued, valid-until, applicable permit information, and the official QR in a corner, embedded as a base64 data-URI `<img>` via `PermitService::qrDataUri()` (SVG data-URI, PNG fallback — see QR section) rather than a raw inline `<svg>`, because dompdf's inline-SVG support is partial. Inline CSS only (dompdf has no external asset pipeline). The applicant can View (HTML), Download PDF, and Print; there is no edit path and no `PermitPolicy@update` ability, so the generated document cannot be altered by applicants.

## Seeders

- `UserSeeder`: admin `admin@bansud.gov.ph` / `Password123!`; BPLO `bplo@bansud.gov.ph` / `Password123!`; applicant `applicant@example.com` / `Password123!` (all `is_active=true`, correct `role`). Passwords hashed via the model's `hashed` cast.
- `RequirementTypeSeeder`: the five seeded requirement types above.
- `FeeTypeSeeder` + `FeeRateSeeder`: four fee types with one active rate each and sample amounts (e.g. Business Tax 500.00, Permit Fee 300.00, Regulatory Fees 150.00, Other Fees 50.00 — illustrative, admin-editable).
- `DatabaseSeeder` calls them in order and ends with a `$this->command->info()` block printing the three seeded credentials to the console.

## Error Handling (per fallible operation)

| operation | failure condition | recoverable? | caller receives | logged |
|---|---|---|---|---|
| File upload | wrong mime / >4MB / no file | recoverable | 422 + field error, redirect back with input | no (expected validation) |
| File upload | disk write fails | fatal to request | 500 + flash "upload failed, try again"; DB row not created (transaction rolled back) | yes, `error` |
| Submit application | missing required requirement slots | recoverable | redirect back to summary with per-item warnings | no |
| Submit application | illegal status (not DRAFT/FOR_CORRECTION) | recoverable | 403 via Policy / redirect with error | yes, `warning` |
| Tracking-/permit-number gen | unique collision on insert | recoverable | bounded retry loop (up to 5 attempts, recomputing the sequence); only after the 5th failure, 500 + "please retry" | yes, `error` (per retry at `warning`, final at `error`) |
| Status transition | illegal from→to pair | fatal to action | `InvalidStatusTransitionException` → redirect back with error (422 for JSON) | yes, `warning` with from/to |
| Return/Reject | empty remark | recoverable | 422 + "remark required" (Form Request rule) | no |
| Start payment | app not APPROVED_FOR_PAYMENT | recoverable | 403 / redirect with error | yes, `warning` |
| Start payment | method disabled in config | recoverable | 422 "method unavailable" | no |
| Gateway initiate | sandbox error (simulated) | recoverable | payment set FAILED, app back to APPROVED_FOR_PAYMENT, flash message | yes, `error`, sanitized |
| Webhook verify | configured secret, bad/missing `X-Sandbox-Signature` | fatal to request | 401; no state change | yes, `warning` |
| Webhook verify | empty `PAYMENT_WEBHOOK_SECRET` (only if an operator blanks it; shipped default is non-empty) | fatal to request | 503 "sandbox webhook disabled; set PAYMENT_WEBHOOK_SECRET"; no state change (use BPLO Confirm instead) | yes, `warning` |
| Permit release | app not PAYMENT_VERIFIED | fatal to action | exception → 403/redirect; **no permit created** | yes, `error` |
| Permit PDF render | dompdf failure | recoverable | permit record kept, `pdf_path` null, retry on next download; flash "regenerating" | yes, `error` |
| QR verify | token not found | normal result | NOT_FOUND page (200), `qr_verifications` row | no (expected) |
| SMS send | provider/log failure | non-fatal | in-app notification still saved; `sms_status=failed` | yes, `warning` |
| Login | bad credentials / inactive user | recoverable | throttled (5/min), generic "invalid credentials" | yes, `auth.login_failed` |

All multi-write operations (submit, approve, pay-start, verify, release, regenerate) run inside `DB::transaction()`; any exception rolls back the state change together with its history/audit rows, so the trail never diverges from reality.

## Input Validation Summary (Form Requests)

Every write endpoint has a Form Request. Representative rules:
- **RegisterRequest:** name required string ≤255; email required email unique:users; password required confirmed, min 8, mixed-case+number (policy-enforced). Role is **not** accepted from input — forced to `applicant` server-side.
- **ApplicationRequest (store/update):** applicant_name/business_name/business_address/business_nature/ownership_type/barangay required strings; contact_number required, PH-mobile regex; email required email; number_of_employees integer ≥0; dti_sec_type in `[DTI,SEC]` nullable; dti_sec_number required_with:dti_sec_type.
- **UploadRequirementRequest:** as in Upload section; `requirement_type_id` must exist and be active.
- **ReviewActionRequest (return/reject):** `remark` required string ≤2000.
- **PaymentStartRequest:** `method` required, `in:` enabled methods from config.
- **TrackingLookupRequest:** `tracking_number` required, matched with a regex built from the configured prefix so lookup never diverges from generation — `'/^'.preg_quote(config('permit.tracking_prefix')).'-\d{4}-\d{6}$/'` (not a hard-coded `BANSUD`); `verifier` required (email or last name).
- **Fee/RequirementType admin requests:** name required; amount `numeric min:0` for rates; code unique slug.

Invariant ownership: the Policy layer owns "applicant acts only on own + editable" and "no one edits a released permit"; the service layer owns status-transition legality and the approval/payment/permit ordering; the DB owns uniqueness (tracking/permit numbers, one permit per application, one requirement slot per type) and referential integrity. Each invariant is enforced at exactly the layer that can see all the relevant state.

## Testability

- **Unit (no DB or mocked):** `TrackingNumberService`/`PermitNumberService` formatting; `FeeComputationService` totals from a fake rate set; `ApplicationWorkflowService::transition()` legal/illegal pairs; `SandboxGateway` never returns PAID on `initiate()`; `LogSmsChannel` returns `logged`.
- **Feature (SQLite in-memory, `RefreshDatabase`):** full happy path register→draft→upload→submit (tracking number format) → BPLO review→approve→pay→verify→release→download; FOR_CORRECTION resubmit keeps tracking number; role middleware denies cross-role access (applicant→/bplo = 403); permit cannot be released before PAYMENT_VERIFIED; Pay button alone never releases a permit; `/verify/{token}` returns VALID/EXPIRED/NOT_FOUND and exposes no PII; private requirement files 403 for non-owners; login throttling after 5 attempts; notifications fire **without a queue worker** (assert the `notifications` row exists immediately after a status change, with `QUEUE_CONNECTION` left at `database`); `role` cannot be mass-assigned (POST register with `role=admin` yields an `applicant`); the sandbox webhook returns **200 and verifies** with the seeded non-empty secret + correct `X-Sandbox-Signature`, **401** on a bad signature, and **503** only in a test that explicitly blanks `PAYMENT_WEBHOOK_SECRET`, while BPLO Confirm always verifies regardless of the secret.
- **PDF QR:** a feature test renders a released permit's PDF and asserts the output is non-empty and contains the embedded QR `<img>` data-URI node (a non-empty SVG or PNG base64 payload), exercising the real `QrCode::format('svg')->generate()` string path, catching any dompdf SVG-rendering gap, and covering the PNG fallback.
- **QR URL well-formedness:** a feature test asserts a released permit's QR encodes a URL matching `^https?://[^:]+(:\d+)?/verify/[A-Za-z0-9]+$` (single optional port), guarding against an `APP_URL` regression like the duplicate-port value fixed in this revision.
- **Race safety:** a focused test submitting several applications in the same year asserts strictly increasing, gap-free, zero-padded tracking numbers.

A design note on testability: because all state changes funnel through services wrapped in transactions and fire events, tests can assert on history/audit rows and `Event::fake()` the notifications, which keeps feature tests fast and deterministic without a queue worker.

## Alignment with Requirements

The design covers all three roles and their listed capabilities, the exact 8-stage status flow plus documented branches, the full normalized data model (all 16 requested tables plus a `number_sequences` helper table for race-safe numbering), configurable fees, sandbox-only payments that cannot be faked into success, approval-and-verification-gated permit generation, secure QR verification with data minimization (RA 10173), in-app + SMS-log notifications that work with no provider, a Node-free Blade auth stack, and the full security posture (role middleware + Policies, CSRF, Form Requests, bcrypt, private storage, audit logs, rate limiting, secure random tokens, no permit-edit path). "Bansud, Oriental Mindoro" is used throughout; no other municipality name appears anywhere. All tunables bind to the existing `config/permit.php` keys.

## Responses to Design Review Findings

This revision resolves every finding in `design-review.json` (2 HIGH, 5 MEDIUM, 4 NIT).

1. **HIGH — Queue driver vs. actual `.env` (notifications would not fire without a worker).** Resolved. Verified `.env` has `QUEUE_CONNECTION=database` and a `jobs` table exists. Adopted review option (b)+(c): notification listeners are now **synchronous (not `ShouldQueue`)** and `NotificationService` writes the in-app row and SMS-log inline during the request, so the feature works with the shipped `database` connection and no worker. Corrected the Overview, Technology Stack (Queue), Events & Listeners, Notification/SMS-Log, and Testability sections. Removed every "sync by default" claim.

2. **HIGH — Webhook signature relies on an unset secret.** Resolved. Specified `hash_hmac('sha256', rawBody, config('permit.payment.webhook_secret'))` compared via `hash_equals()` against header `X-Sandbox-Signature`. Defined the empty-secret policy: route returns **503** (disabled) so `PAYMENT_VERIFIED` is always reachable via the BPLO "Confirm payment" action; configured-but-wrong signature returns **401**. `.env.example` ships a non-empty default. Updated route map, payment-flow step 3, and the error-handling table.

3. **MEDIUM — dompdf inline-SVG QR asserted without fallback.** Resolved. The PDF now embeds the QR as a base64 **data-URI `<img>`** (SVG) via a single `PermitService::qrDataUri()` method, with a documented **PNG fallback** (GD, or `endroid/qr-code` if GD is unavailable). Added a PDF feature test asserting the QR node is present. Updated Technology Stack (QR), QR section, PDF section, and PermitService.

4. **MEDIUM — User fillable / `role` mass-assignment risk.** Resolved. Kept the attribute style: `#[Fillable(['name','email','password','contact_number','address','is_active'])]`, **excluding `role`**, which is set only in server code; added `'is_active' => 'boolean'` to `casts()`. A feature test asserts `role` cannot be mass-assigned.

5. **MEDIUM — composer `setup` runs npm, contradicting no-Node lock.** Resolved. Added a Technology Stack note directing removal of the `npm install` / `npm run build` lines from the `setup` script and declaring `public/css/app.css` as committed static CSS referenced directly from Blade (no Vite entry).

6. **MEDIUM — `lockForUpdate()` race-safety overstated on SQLite.** Resolved. Verified `transaction_mode = DEFERRED` and that SQLite ignores `FOR UPDATE`. Replaced lock-and-count with a dedicated **`number_sequences`** table using an atomic `UPDATE ... last_value = last_value + 1` as the primary mechanism, with the UNIQUE index as backstop. Added the table to the data model and rewrote the service + generation sections.

7. **MEDIUM — single retry insufficient under concurrency.** Resolved. Specified a **bounded retry loop of up to 5 attempts** (recomputing the sequence each time) before surfacing a 500. Updated the generation section and error-handling table.

8. **NIT — tracking regex hard-codes `BANSUD`.** Resolved. `TrackingLookupRequest` now builds the regex from `preg_quote(config('permit.tracking_prefix'))`.

9. **NIT — status-history timestamp ambiguity.** Resolved. Fixed to `timestamp('created_at')->useCurrent()` only, no `updated_at`, model `$timestamps = false`.

10. **NIT — `qr_verifications.permit_id` cascade.** Resolved. Changed to `nullOnDelete()` and documented that permits are never hard-deleted (revocation flips status), so forensic rows are preserved.

11. **NIT — admin settings scope vague.** Resolved. `/admin/settings` is now **strictly read-only** (displays `config('permit.*')`, no `.env`/config writes); tunable data lives in DB-backed admin CRUD screens.

## Responses to Second-Pass Review Findings

This revision also resolves every finding in the second-pass `design-review.json` (0 HIGH, 3 MEDIUM, 3 NIT). The two MEDIUM findings that were "the design asserts repo state that is not true" are fixed by making the repo match the design (edited the actual files), not by softening the claims.

1. **MEDIUM — `.env.example` shipped an empty `PAYMENT_WEBHOOK_SECRET`.** Resolved by editing files to match the design. Set `PAYMENT_WEBHOOK_SECRET=sandbox-webhook-secret-change-me` in **both** `.env.example` and the committed `.env`, so the webhook is live out of the box as the design states. Rewrote the route-map webhook prose and payment-flow step 3 to say the webhook ships live (not 503-disabled), kept the empty-secret → 503 behavior as defense-in-depth, and updated the webhook feature test to assert a **200 verify** with the seeded secret (503 only when a test explicitly blanks it).

2. **MEDIUM — `.env.example` `APP_URL` was malformed (`http://localhost:8000:8000`).** Resolved by editing `.env.example` to `APP_URL=http://localhost:8000` (matching the already-correct committed `.env`). Added a note to the QR section that the verify URL depends on a well-formed `APP_URL`, and added a feature test asserting the generated QR URL matches `^https?://[^:]+(:\d+)?/verify/[A-Za-z0-9]+$`.

3. **MEDIUM — prose/table conflict on `SUBMITTED -> REJECTED`.** Resolved. Added `SUBMITTED -> REJECTED (BPLO reject, remark required)` to the authoritative transition table so a brand-new submission can be rejected directly, matching the brief's unconditional Reject capability. Reworded the Status Model prose so it explicitly agrees with the table (reject reachable from both `SUBMITTED` and `UNDER_REVIEW`, both pairs present in the enforced table).

4. **NIT — `ApplicationRejected` had no notification trigger.** Resolved by choosing to notify. Mapped `ApplicationRejected` → `SendStatusNotification` with an `application.rejected` template (title + BPLO remark) and added rejection to the enumerated notification triggers in both the Events & Listeners and Notification/SMS-Log sections.

5. **NIT — simple-qrcode SVG "verified in vendor" overstated.** Resolved. Scoped the wording to backend-class file presence (`SvgImageBackEnd` confirmed in vendor, so no imagick needed) and stated the end-to-end `QrCode::format('svg')->generate()` string path is asserted by the PDF QR feature test, which now explicitly asserts a non-empty SVG/PNG base64 payload.

6. **NIT — `composer run setup` still ran npm.** Resolved by editing `composer.json`: removed the `npm install --ignore-scripts` and `npm run build` lines. The design now shows the exact resulting `setup` array (`composer install` → copy `.env` → `key:generate` → `migrate --force`) so a verifier can confirm no `npm` line remains.
