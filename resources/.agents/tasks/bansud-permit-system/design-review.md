# Design Review — Automated Business Permit Application and Status Tracking System with QR Code Verification for Bansud, Oriental Mindoro

Reviewer: design-review subagent (fresh read, no prior authoring context)
Document under review: `.agents/tasks/bansud-permit-system/design.md`
Project root: `c:\Users\HP\BUSINESS PERMIT_SYSTEM\app`

## Verdict

CHANGES_REQUESTED — 1 MEDIUM finding. (HIGH = 0, MEDIUM = 1, NIT = 4.) Any count of HIGH + MEDIUM greater than zero blocks approval.

The design is, on the whole, strong: the status machine, the race-safe `number_sequences` numbering, the sandbox payment ordering (Pay button can never self-verify), the private-disk file serving, the data-minimized `/verify` page, and the Node-free bootstrap are all specified unambiguously and — where they make claims about the repo — verified against the actual files. The single blocking issue is a factually wrong, non-functional PNG fallback for the PDF QR that also leans on an undeclared dependency. The baseline SVG path is sound, so this is contained, but as written a coder implementing the fallback would ship broken code in this environment.

---

## Findings

### 1. MEDIUM — PDF QR PNG fallback is factually wrong and depends on an undeclared package
Location: Technology Stack (QR), "QR Token, Verification & Data Minimization" (QR rendering strategy), `PermitService::qrDataUri()`, Response-to-Findings #3 / NIT #5.

The design states the PNG fallback this way: "simple-qrcode's PNG writer requires the GD extension, so if GD is unavailable the implementation uses `endroid/qr-code` (added as a composer dependency) to emit PNG." Two parts of this are wrong or unverifiable against the repo:

- **Wrong extension.** `simplesoftwareio/simple-qrcode` renders raster output through `bacon/bacon-qr-code`. The installed vendor copy ships only `EpsImageBackEnd`, `ImagickImageBackEnd`, and `SvgImageBackEnd` (verified: `vendor/bacon/bacon-qr-code/src/Renderer/Image/`). There is **no GD-backed PNG backend**. PNG output through simple-qrcode requires the **imagick** extension, not GD. This machine has `GD:yes IMAGICK:no` (verified via `php -r`), so a PNG attempt through simple-qrcode would throw here.
- **Undeclared dependency.** `endroid/qr-code` is named as the GD-less escape hatch "added as a composer dependency," but it is **not** in `composer.json` `require` and **not** present in `vendor/` (verified: `vendor/endroid` does not exist). As written, the fallback references a package that is not installed and not declared.

Net effect: the baseline SVG data-URI path is fine (`dompdf/php-svg-lib` is present, so dompdf 3.x can render the inline SVG data-URI — verified), but the documented fallback is non-functional in the target environment and would not compile/run if a coder implements it verbatim.

Concrete fix — pick one and make the repo match the design:
- (a) Declare `endroid/qr-code` in `composer.json` `require` and specify that `qrDataUri()`'s PNG branch uses endroid (which can emit PNG via GD), then correct the prose to "simple-qrcode PNG requires imagick; because this environment ships GD but not imagick, the PNG fallback uses `endroid/qr-code` (GD-based)." Add it to the stack lock list; or
- (b) Drop the PNG fallback entirely and state that the baseline is SVG data-URI only, justified by the verified presence of `dompdf/php-svg-lib` (dompdf 3.x renders the SVG data-URI `<img>`). Keep `qrDataUri()` as the single swap point but document that switching to PNG later requires adding imagick or endroid. The PDF QR feature test then asserts a non-empty SVG base64 payload only.

Either way, correct the false "simple-qrcode's PNG writer requires the GD extension" sentence.

### 2. NIT — permit `REVOKED` status has no write path
Location: `permits` table (`status` = `ACTIVE` / `REVOKED`), `qr_verifications.result` (`REVOKED`), `/verify/{token}` classification, Response-to-Findings NIT #10.

`REVOKED` is a defined permit status, the `/verify` page classifies it, and `qr_verifications.result` can record it, but no route, controller action, or `PermitService` method ever sets a permit to `REVOKED`. `PermitService` lists `release`, `renderPdf`, `qrDataUri`, `regenerate` — none revoke. So the `REVOKED` branch is currently unreachable dead state. Revocation is not a brief requirement, so this is not a missing feature, only an internal inconsistency.

Concrete fix: either (a) add a brief note that revocation is out of scope for this build and `REVOKED` is a reserved value with no UI/route yet (keep the `/verify` branch as forward-compat), or (b) add a `PermitService::revoke(Permit $p, ?User $actor, string $reason): void` plus a `POST /bplo/permits/{permit}/revoke` route and an audit entry, mirroring `regenerate`.

### 3. NIT — service DTOs referenced but not defined
Location: `FeeComputationService` (`FeeBreakdown`), `PaymentGateway`/`PaymentService` (`GatewayResult`), `NotificationService`/`SmsChannel` (`SmsResult`).

`FeeBreakdown`, `GatewayResult`, and `SmsResult` are used in signatures but their shapes are never specified. `FeeBreakdown` is partly pinned (`{ lines: [...], total: decimal }`), but `GatewayResult` and `SmsResult` have no fields defined, leaving the success/failure contract between gateway and `PaymentService::verify()` to interpretation.

Concrete fix: define each as a small readonly DTO, e.g. `GatewayResult { bool $ok; string $status; ?string $reference; array $raw; }` and `SmsResult { string $status; /* queued|sent|logged|failed */ ?string $error; }`, and state that `PaymentService::verify()` keys the PAID/FAILED branch on `GatewayResult::$ok`.

### 4. NIT — admin "manage roles and permissions" maps only to role assignment
Location: Roles & Authorization, Admin route map (`/admin/users` "set role"); brief requirement C ("Manage roles and permissions").

The brief lists "Manage roles and permissions" for the System Administrator. The design implements a fixed 3-value `role` string plus admin ability to set a user's role; there is no permissions-management surface. This is a defensible scope reading (permissions are code-level Gates/Policies, roles are the only runtime-assignable axis), but the design should say so explicitly rather than leave the gap implicit.

Concrete fix: add one sentence to Roles & Authorization stating that "permissions" are fixed, policy-encoded capabilities per role and that admin management is limited to assigning one of the three roles and activating/deactivating accounts — no dynamic permission editing in scope.

### 5. NIT — `/track` second factor ("email or last name") is ambiguous for last name
Location: Public route map (`POST /track`), `TrackingLookupRequest`, "QR Token, Verification & Data Minimization" (track page).

The track lookup accepts tracking number plus "email or last name." Email matches `applications.email` (snapshot), but there is no `last_name` column — the snapshot stores `applicant_name` as a single string. The matching rule for "last name" against a full-name field is unspecified (exact match? substring? case-folding?), which is an ambiguous validation/lookup rule.

Concrete fix: specify the rule, e.g. "the `verifier` field matches if it equals `applications.email` (case-insensitive) OR is a case-insensitive substring of `applications.applicant_name`," and state the `TrackingLookupRequest` treats `verifier` as a free string validated `required|string|max:255`.

---

## Verified Assumptions

Each of these design claims was checked against the actual repo and holds:

- **`composer.json` `setup` is Node-free.** The exact array is `composer install` → copy `.env` → `key:generate` → `migrate --force`; no `npm` line remains. Matches the design verbatim. Verified.
- **`simplesoftwareio/simple-qrcode` (`^4.2`) and `barryvdh/laravel-dompdf` (`^3.1`) are declared and installed.** `vendor/simplesoftwareio/simple-qrcode` and `vendor/dompdf/php-svg-lib` both exist. Verified.
- **`bacon-qr-code` ships `SvgImageBackEnd`.** File present at `vendor/bacon/bacon-qr-code/src/Renderer/Image/SvgImageBackEnd.php`, so the SVG renderer needs no imagick. Verified. (The PNG side is NOT — see Finding 1.)
- **`QUEUE_CONNECTION=database` in `.env`, and a `jobs` table migration exists.** Confirmed in `.env` and `database/migrations/0001_01_01_000002_create_jobs_table.php`. This is exactly why the synchronous (non-`ShouldQueue`) listener design is correct. Verified.
- **`config/database.php` sets `transaction_mode => 'DEFERRED'` for sqlite** and SQLite treats `FOR UPDATE` as a no-op — so the design's rejection of lock-and-count in favor of the `number_sequences` atomic increment is well-founded. Verified.
- **`FILESYSTEM_DISK=local`; the `local` disk root is `storage_path('app/private')`** and is not symlinked into `public/` (only the `public` disk is linked). Private file serving through authorized controllers is sound. Verified.
- **`.env` and `.env.example` both ship `PAYMENT_WEBHOOK_SECRET=sandbox-webhook-secret-change-me`** (non-empty), so the sandbox webhook is live out of the box and the 503-disabled branch is only reached when an operator blanks it. Verified in both files.
- **`APP_URL=http://localhost:8000`** (well-formed, no duplicate port) in both `.env` and `.env.example`, so the QR verify URL is valid. The earlier malformed `:8000:8000` value is gone. Verified.
- **`config/permit.php` exposes every key the design binds to:** `tracking_prefix=BANSUD`, `permit_prefix=BP`, `valid_months`, `qr_hash_salt`, `sms.driver` (default `log`), `payment.mode` (default `sandbox`), `payment.methods` (gcash/maya/qrph), `payment.webhook_secret`, `uploads.mimes` (pdf,jpg,jpeg,png), `uploads.max_kb` (4096). No new config keys are introduced. Verified.
- **`User` model uses the attribute style** `#[Fillable(['name','email','password'])]`, `#[Hidden(['password','remember_token'])]`, and a `casts()` method with `password=>hashed`. The design's extend-fillable / exclude-`role` / add-`is_active`-cast plan is consistent with the shipped model. Verified.
- **Base `users` migration has exactly** `name`, `email` unique, `email_verified_at`, `password`, `rememberToken`, timestamps, plus `password_reset_tokens` and `sessions` tables — matching the "extend existing users table via ALTER migration" and "custom Blade auth on existing tables" claims. Verified.
- **`bootstrap/app.php` has an empty `withMiddleware` closure** and no middleware aliases yet — a clean place to register the `role` alias as described. Verified.
- **`database/database.sqlite` exists.** Verified.
- **"Bansud, Oriental Mindoro" is used; "Bongabong" never appears** in `design.md` or project source. The only `Bongabong` hits are inside the prior review document's own prose, not the design or code. Verified.
- **Status flow, guards, and data model coverage:** the 8-stage flow DRAFT→SUBMITTED→UNDER_REVIEW→FOR_CORRECTION→APPROVED_FOR_PAYMENT→PAYMENT_PENDING→PAYMENT_VERIFIED→PERMIT_RELEASED is defined as constants with an authoritative transition table; the no-skip (permit only from PAYMENT_VERIFIED), no-fake-payment (only `PaymentService::verify()` sets PAID), and no-premature-permit guards are each enforced in the service layer; all 16 requested tables plus the `number_sequences` helper are present with FKs/indexes called out. The prose/table conflict on `SUBMITTED -> REJECTED` is resolved (both pairs now in the table). Verified as internally consistent within the document.

## Unverified / Wrong Assumptions

- **WRONG — "simple-qrcode's PNG writer requires the GD extension."** It requires **imagick** (`ImagickImageBackEnd`); bacon-qr-code has no GD backend. This machine has GD but not imagick. See Finding 1.
- **WRONG/UNVERIFIABLE — "`endroid/qr-code` (added as a composer dependency)."** Not in `composer.json`, not in `vendor/`. See Finding 1.
- **UNVERIFIED (acceptable) — dompdf renders the SVG data-URI `<img>` for the permit PDF.** `dompdf/php-svg-lib` is present, which makes this plausible for dompdf 3.x, but actual rendering of a base64 SVG data-URI is only confirmed by running the stated PDF feature test. The design explicitly defers this to that test rather than asserting it, which is the right posture; flagged here only so the implementer knows this is test-confirmed, not statically proven.
- **UNVERIFIED (acceptable) — Laravel 13 policy/event auto-discovery maps `App\Models\Application` → `App\Policies\ApplicationPolicy` and auto-discovers listeners.** Standard framework behavior; no repo evidence contradicts it, but the models/policies/listeners do not exist yet (greenfield), so it is unverified by nature.
- **UNVERIFIED (acceptable) — the attribute-based `#[Fillable]`/`#[Hidden]` extend as described.** The shipped model uses the attribute style, so extending the attribute list is consistent; the extended form itself is not yet in the repo (it is the change the implementer will make).
