# Implementation Plan — Bansud Automated Business Permit System

Laravel root (all commands run here): `c:\Users\HP\BUSINESS PERMIT_SYSTEM\app`. OS Windows / PowerShell, PHP 8.3, SQLite. No Node/Vite. QR is SVG-only (no imagick/PNG/endroid). The design doc at `.agents/tasks/bansud-permit-system/design.md` is authoritative on every detail; this plan sequences it.

Current state (verified): fresh Laravel 13.34 skeleton. Only the base `users`/`cache`/`jobs` migrations, the stock `User` model, `welcome.blade.php`, and the two Example tests exist. `config/permit.php`, `.env`, `database/database.sqlite`, the `local` private disk (`storage/app/private`, not symlinked), the bacon-qr-code SVG backend, and dompdf are all present. `php artisan test` is green at baseline. Essentially nothing from the design is built yet — this plan builds the whole system.

Cross-cutting implementation notes (apply throughout):
- **Race-safe numbering:** `number_sequences(scope,year)` with atomic `UPDATE ... SET last_value = last_value + 1` inside `DB::transaction()`, UNIQUE index on `applications.tracking_number` / `permits.permit_number` as backstop, bounded retry up to 5 attempts. Never `lockForUpdate()`+count (SQLite ignores `FOR UPDATE`, mode is DEFERRED).
- **SVG-only QR:** `QrCode::format('svg')->generate($url)` → base64 `data:image/svg+xml;base64,...` `<img>`, centralized in `PermitService::qrDataUri()`. Used in both the browser permit view and the dompdf PDF. No PNG/GD/imagick/endroid anywhere. QR encodes `config('app.url').'/verify/'.$token`.
- **Synchronous listeners:** notification listeners do NOT implement `ShouldQueue`; `NotificationService` writes the in-app row and SMS-log inline so the feature works with `QUEUE_CONNECTION=database` and no worker.
- **Private upload serving:** files on the `local` disk under `applications/{id}/...`; served only via policy-gated controllers using `Storage::disk('local')->download()`. Never a public URL, never `storage:link` for requirements.
- **Config source of truth:** read `config('permit.*')` everywhere (municipality, province, prefixes, valid_months, qr_hash_salt, sms, payment methods/mode/webhook_secret, uploads mimes/max_kb). Never hard-code. `role` is never mass-assignable.
- All multi-write operations run in `DB::transaction()`; every status change writes `application_status_histories` + `audit_logs`.
- Use "Bansud, Oriental Mindoro" consistently; "Bongabong" must appear nowhere.

Verification commands (the implementer must run these and leave evidence):
- Build/boot: `php artisan config:clear` then `php artisan route:list` (routes resolve, no errors).
- Migrate fresh + seed: `php artisan migrate:fresh --seed` (all tables created, credentials printed).
- Tests: `php artisan test` (full suite green). Narrow with `php artisan test --filter=<Name>` per module.
- Lint: `vendor\bin\pint --test` (code style passes).

---

## Phase A — Foundation: config, migrations, models, seeders

- [ ] 1. Fix bootstrap config: remove `npm` lines from `composer.json` `setup` (if present), confirm `.env`/`.env.example` have non-empty `PAYMENT_WEBHOOK_SECRET` and well-formed `APP_URL=http://localhost:8000`; register the `role` middleware alias and `EnsureUserHasRole` in `bootstrap/app.php`.
      Files: `composer.json`, `.env.example`, `app/Http/Middleware/EnsureUserHasRole.php`, `bootstrap/app.php`
      Verify: `php artisan route:list` runs without error; `php artisan config:clear` succeeds.

- [ ] 2. Create all migrations: `add_profile_fields_to_users_table` (role/contact_number/address/is_active) and new tables businesses, requirement_types, applications, application_requirements, application_status_histories, application_remarks, fee_types, fee_rates, payments, payment_transactions, permits, qr_verifications, notifications, audit_logs, number_sequences — exact columns, types, indexes, and FK actions per the design's Data Model. Forward-only; no SQLite-unsupported drops/`->after()`.
      Files: `database/migrations/*` (one per table/alter)
      Verify: `php artisan migrate:fresh` creates every table with no error.

- [ ] 3. Create all Eloquent models with constants, casts, and relationships both directions per the design: extend `User` (role excluded from fillable, `is_active` cast, role constants + `isApplicant/isBplo/isAdmin`, relationships); Business, RequirementType, Application (STATUS_* constants), ApplicationRequirement (CHECK_* constants, `$timestamps` handling), ApplicationStatusHistory (`$timestamps=false`), ApplicationRemark, FeeType, FeeRate, Payment (STATUS_*/VERIFY_* constants), PaymentTransaction, Permit, QrVerification, Notification, AuditLog, NumberSequence.
      Files: `app/Models/*.php`
      Verify: `php artisan migrate:fresh` then `php artisan tinker --execute="App\Models\Application::STATUS_DRAFT; echo 'ok';"` — models load, no relationship errors.

- [ ] 4. Create seeders: UserSeeder (3 accounts, correct roles, is_active, hashed), RequirementTypeSeeder (5 types), FeeTypeSeeder + FeeRateSeeder (4 types each with one active sample rate); wire into DatabaseSeeder in dependency order and print the three credentials to console.
      Files: `database/seeders/*.php`
      Verify: `php artisan migrate:fresh --seed` runs clean and prints admin/bplo/applicant credentials.

## Phase B — Domain services, auth, authorization

- [ ] 5. Create numbering + workflow + fee services: TrackingNumberService, PermitNumberService (atomic sequence + bounded retry), ApplicationWorkflowService (legal transition table, writes history+audit, fires events, `InvalidStatusTransitionException`), FeeComputationService (reads active rates, builds breakdown+total), AuditLogger helper.
      Files: `app/Services/*.php`, `app/Exceptions/InvalidStatusTransitionException.php`, `app/Support/FeeBreakdown.php`
      Verify: unit tests for these pass (added in Phase F) — for now `php artisan test` stays green and `tinker` can resolve each service.

- [ ] 6. Create payment + permit + notification services and the events/listeners: PaymentGateway interface + SandboxGateway (never returns PAID on initiate), PaymentService (startPayment/verify ordering invariants), PermitService (release guarded on PAYMENT_VERIFIED, qrDataUri SVG, renderPdf, regenerate), NotificationService + SmsChannel interface + LogSmsChannel; domain events + synchronous SendStatusNotification listener; container bindings in a service provider.
      Files: `app/Services/*.php`, `app/Contracts/*.php`, `app/Events/*.php`, `app/Listeners/SendStatusNotification.php`, `app/Providers/AppServiceProvider.php` (or dedicated provider)
      Verify: `php artisan event:list` shows the listeners; `php artisan test` green.

- [ ] 7. Create authorization: EnsureUserHasRole middleware behavior (comma list, 403), ApplicationPolicy / PaymentPolicy / PermitPolicy (no update/edit ability on Permit); auth controllers on the Auth facade (Register forcing role=applicant, Login with throttle, Logout, PasswordReset) + their Form Requests.
      Files: `app/Policies/*.php`, `app/Http/Controllers/Auth/*.php`, `app/Http/Requests/Auth/*.php`
      Verify: `php artisan route:list` shows auth routes once Phase C routes exist; policy methods resolve.

## Phase C — HTTP layer, views, QR/PDF, routes

- [ ] 8. Build the applicant flow: DashboardController, ApplicationController (create/store/edit/update/summary/submit/show), RequirementController (upload/remove/file private serve), PaymentController (show/start/history), PermitController (show/download) + Form Requests (ApplicationRequest, UploadRequirementRequest, PaymentStartRequest). Tracking number issued on submit via the service.
      Files: `app/Http/Controllers/Applicant/*.php`, `app/Http/Requests/*.php`
      Verify: `php artisan route:list` shows all applicant routes; `php artisan test` green.

- [ ] 9. Build the BPLO flow: DashboardController (7 counters), ApplicationController (index search/filter/sort/paginate, show, beginReview/approve/returnForCorrection/reject/flagRequirement/file), PaymentController@confirm, PermitController (release/regenerate) + ReviewActionRequest.
      Files: `app/Http/Controllers/Bplo/*.php`, `app/Http/Requests/ReviewActionRequest.php`
      Verify: `php artisan route:list` shows BPLO routes; `php artisan test` green.

- [ ] 10. Build the admin flow: DashboardController (monitoring cards), UserController, RequirementTypeController, FeeTypeController, FeeRateController, SettingController (read-only config display), AuditLogController + Form Requests.
      Files: `app/Http/Controllers/Admin/*.php`, `app/Http/Requests/Admin/*.php`
      Verify: `php artisan route:list` shows admin routes; `php artisan test` green.

- [ ] 11. Build public pages + sandbox webhook: LandingController (title + 4 steps), TrackingController (show/lookup, throttle:6,1, no PII) + TrackingLookupRequest (regex from config prefix), VerificationController (`/verify/{token}`, throttle:30,1, classify VALID/EXPIRED/REVOKED/NOT_FOUND, data-minimized, logs qr_verifications), SandboxWebhookController (HMAC-SHA256 on raw body vs `X-Sandbox-Signature`, 503 if secret blank, 401 bad sig, drives PaymentService::verify).
      Files: `app/Http/Controllers/Public/*.php`, `app/Http/Requests/TrackingLookupRequest.php`
      Verify: `php artisan route:list` shows public + webhook routes with throttles; `php artisan test` green.

- [ ] 12. Wire `routes/web.php` with the full route map grouped by middleware (public, auth-guest, applicant, bplo, admin, sandbox webhook) exactly per the design Route Map.
      Files: `routes/web.php`
      Verify: `php artisan route:list` matches the design's route table.

- [ ] 13. Build all Blade views + static CSS + PDF template: layouts, auth, applicant (dashboard, create/edit multi-section form, summary, show/tracker, pay, payments history, permit view with inline SVG QR), bplo (dashboard, index table, review show), admin (dashboard + CRUD + settings + audit logs), public (landing, track, verify), `resources/views/permits/pdf.blade.php` with embedded base64 SVG QR data-URI, and `public/css/app.css` (committed, no Vite).
      Files: `resources/views/**/*.blade.php`, `public/css/app.css`
      Verify: `php artisan migrate:fresh --seed` then hit routes via feature tests (Phase F); PDF render test asserts non-empty + QR node.

## Phase D — Test suite (final)

- [ ] 14. Write unit tests: numbering format + race safety (gap-free increasing), FeeComputationService totals, ApplicationWorkflowService legal/illegal transitions, SandboxGateway never-PAID-on-initiate, LogSmsChannel returns logged.
      Files: `tests/Unit/**/*.php`
      Verify: `php artisan test --testsuite=Unit` green.

- [ ] 15. Write feature tests (RefreshDatabase, SQLite in-memory): full happy path register→draft→upload→submit→review→approve→pay→verify→release→download; FOR_CORRECTION resubmit keeps tracking number; role middleware cross-role 403; permit not releasable before PAYMENT_VERIFIED; Pay alone never releases; `/verify/{token}` VALID/EXPIRED/NOT_FOUND with no PII; private file 403 for non-owner; login throttle after 5; notifications fire with no worker (QUEUE_CONNECTION=database); role not mass-assignable; sandbox webhook 200/401/503 + BPLO confirm; PDF QR non-empty; QR URL regex; remove the two Example tests.
      Files: `tests/Feature/**/*.php`
      Verify: `php artisan test` fully green; `vendor\bin\pint --test` passes.

---

Assumptions / gaps noted: the design is complete and internally consistent (all review findings resolved in the doc); no open design decisions remain, so this plan only sequences. If any migration hits a SQLite limitation, prefer a forward-only new migration over an `->after()`/drop. If the first `php artisan test` run is slow, that is boot cost, not a failure.
