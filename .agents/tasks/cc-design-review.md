# CC Design Review — Applicant Control-Center Portal (Scope C), iteration 5

Reviewer: design-review subagent (fresh read, no authoring context).
Design under review: `.agents/tasks/cc-design.md` (iteration 5).
Ground truth: `.agents/tasks/applicant-portal-investigation.md` + the live source files, all re-read this session.
Laravel root: `c:\Users\HP\BUSINESS PERMIT_SYSTEM\app` (parent path has a space — paths quoted throughout).

Verdict basis: count HIGH + MEDIUM findings. This review records **0 HIGH, 0 MEDIUM, 4 NIT** → **APPROVED**.

The design is mature. Every hard-correctness constraint the task enumerates (a–j) was checked against the actual source and holds. The two MEDIUMs from the prior iteration (PaymentController flash wording; fee-summary multi-payment selection/routing) are genuinely resolved and re-verified below. The four NITs are copy/precision issues that do not change behavior or block implementation.

---

## Findings

### 1. NIT — §6.3 / constraint-2 rationale misstates where the fixed flash is shown
`§6.3` and `§1 constraint 2` justify rewriting the `PaymentController@start` success flash by saying it appears "on the exact event where the dashboard's new PAYMENT_PENDING callout appears." Verified against `app/Http/Controllers/Applicant/PaymentController.php@start`: on success it `redirect()->route('applications.show', $application)` — it redirects to the **application show page**, not the dashboard. The user only sees the dashboard PAYMENT_PENDING callout on a later, separate visit to `/dashboard`.

Why it is only a NIT: the *fix itself* (BPLO → Treasurer wording) is correct and required regardless of which page the flash renders on — it is applicant-visible copy that must name the Treasurer. Only the rationale's "exact event / same page" phrasing is imprecise.

CONCRETE fix: reword the rationale to "…shown on the application-show redirect immediately after a manual GCash submission, and the same Treasurer wording is echoed by the dashboard PAYMENT_PENDING callout on the next dashboard visit — the two must agree." No change to the code edit in §6.3.

### 2. NIT — renewal-reminder loop source and permit-status filter are implied, not spelled out
`§5.1` gives the per-permit `daysLeft`/`expired` math and `§5.2` says to skip revoked permits and permits without `valid_until`, but the design never states the iteration source explicitly. Verified from source: `Permit` has **no `user()` relation** (only `application()`), so reminders must be derived by iterating the user's `$applications` and reading each `$application->permit` (already eager-loaded per §2.1), filtering to `permit !== null && permit->status === Permit::STATUS_ACTIVE && permit->valid_until !== null`.

CONCRETE fix: add one sentence to §5.1: "Build `renewalReminders` by iterating the eager-loaded `$applications`, taking `$app->permit` where it is non-null, `status === Permit::STATUS_ACTIVE`, and `valid_until !== null`; compute `daysLeft`/`expired` per the snippet and collect `['application','permit','daysLeft','expired']`." This matches the eager loads already in §2.1 and needs no extra query.

### 3. NIT — stale "BPLO verifies" comment in `config/permit.php` understated as optional-only
`§8` and `§6.3` list the `config/permit.php` `payment.mode` comment and the `Payment::isAwaitingVerification()` docblock as OPTIONAL cosmetic cleanup because they are not applicant-visible. Verified: `config/permit.php` line comment reads "the BPLO verifies it" and `Payment::isAwaitingVerification()` docblock reads "Awaiting a BPLO decision." That classification is defensible (neither string is rendered to applicants). This is flagged only so the implementer does not treat these as the constraint-2 fix — the **live** fix is the §6.3 flash, which is correctly separated.

CONCRETE fix: none required for correctness. If touched, change both comments to name the Treasurer so a future reader is not misled; keep it clearly optional. No behavior change.

### 4. NIT — `BusinessProfileRequest` should assert `nature`/`address`/`ownership_type` are non-nullable to match the DB
Verified against `database/migrations/2025_01_01_000002_create_businesses_table.php`: `name`, `address`, `barangay`, `nature`, `ownership_type` are all **NOT NULL** (no `->nullable()`); only `dti_sec_type` and `dti_sec_number` are nullable; `number_of_employees` is `unsignedInteger default(0)`. The design's `BusinessProfileRequest` rules (§3.2) already make `name/address/barangay/nature/ownership_type` `required` and `dti_sec_*` nullable and `number_of_employees required|integer|min:0` — i.e. they already match the schema. This is recorded as a NIT only to pin the mapping explicitly so an implementer does not loosen `nature`/`ownership_type` to `nullable` and then hit a NOT NULL insert error on first save.

CONCRETE fix: add a one-line note in §3.2 next to the rules: "These `required` rules are load-bearing — `name/address/barangay/nature/ownership_type` are NOT NULL in the businesses migration, so they must stay `required`." No rule change needed.

---

## Verified Assumptions (checked against live source this session)

1. **Nine status constants only** — `app/Models/Application.php` lines 12–30 define exactly the nine `STATUS_*` constants listed in the investigation. The design references only these; no invented "Paid"/"For Payment"/"Released" labels appear. `components/status-badge.blade.php` maps all nine to badge+label and nothing else. **(a) satisfied.**
2. **Payment verified by Treasurer, not BPLO** — `routes/web.php` puts `payments.confirm`/`payments.reject` under `role:treasurer,admin`; `components/process-tracker.blade.php` labels the `STATUS_PAYMENT_PENDING` step actor "Treasurer" and `actorNow` maps `PAYMENT_PENDING => 'Treasurer'`. The design sources all "who verifies" copy from the process-tracker table and rewrites the one live applicant flash (§6.3) to "Treasurer". **(b) satisfied.**
3. **Single source of truth = `applications.status`** — `DashboardController@index` reads `$user->applications()`; all stats/callouts/cards derive from `application->status`. Correction/rejection reasons come from `ApplicationRemark` (`type`/`body`) and `ApplicationStatusHistory.note`. Confirmed `ApplicationWorkflowService::returnForCorrection/reject` write `addRemark(..., 'CORRECTION'/'REJECTION', $remark)` as plain string literals into `type`, with the reason in `body`. `ApplicationRemark` has no constants — literals are correct. **(c) satisfied.**
4. **Action Required from status, not `read_at`** — confirmed `DashboardController@index` marks all unread notifications read on load (`->update(['read_at' => now()])`), so the design computes `$stats['action']` from `status ∈ {FOR_CORRECTION, APPROVED_FOR_PAYMENT}`. **(d) satisfied.**
5. **Fees only from `FeeComputationService`, never before APPROVED_FOR_PAYMENT** — `Applicant\PaymentController@show` (`applications.pay`) has `abort_unless(status === APPROVED_FOR_PAYMENT, 403)` and computes via `FeeComputationService::computeFor()`. The design's `$showFees` guard uses a strict `array_search(..., true)` membership check against an 8-element order array that deliberately omits REJECTED; `$idx !== false` is tested before the index compare, so REJECTED and FOR_CORRECTION cannot show a fee block. No hard-coded amounts anywhere. **(e) satisfied.**
6. **Permit + QR gated on a Permit row** — `Applicant\PermitController@show` and `@download` both `abort_if($permit === null, 404)` and `authorize('download', $permit)`; `Permit` row exists only after `PermitService::release()`. The design renders Download/View-QR only when `status === PERMIT_RELEASED && permit !== null`. **(f) satisfied.**
7. **Theme scope** — `layouts/app.blade.php` is `body class="has-sidebar"` (light portal). The design edits only authenticated applicant views that extend `layouts.app` and reuses existing components (`<x-stat-card>`, `<x-empty-state>`, `<x-status-badge>`, `<x-process-tracker>`, `<x-flash-messages>`). Homepage/login/register untouched. **(g) satisfied.**
8. **Role never mass-assignable** — `User` `#[Fillable(['name','email','password','contact_number','address','is_active'])]` excludes `role`; `RegisterController` sets `$user->role` explicitly after `fill()`. The design's new controllers follow this exact pattern and keep `role`/`is_active` out of every new FormRequest. **(h) satisfied.**
9. **Business Profile backward compatibility** — `Application.business_id` is a nullable `belongsTo`; `business()` is never dereferenced required anywhere. The design's `store()` saves the application LAST, wraps the business upsert in try/catch, and uses `associate()` for both `business_id` and the Business's `user_id` (never mass-assigned). Existing `business_id = null` flows and `ApplicationFlowTest` (which creates applications without a business) stay valid. **(i) satisfied.**
10. **Renewal Reminder read-only (R1)** — confirmed no `application_type` column on `applications` (not in model fillable, not in migrations); `Permit.valid_until` is `date`-cast; `STATUS_ACTIVE`/`STATUS_REVOKED` exist. The design adds no column and no renewal application flow — only a dashboard banner from `valid_until`. **(j) satisfied.**
11. **`_form-fields` partial binds Application field names** — confirmed it reads `old('business_name', $app?->business_name)`, `old('business_nature', ...)`, etc. A raw `Business` (columns `name`/`nature`) would prefill nothing; the design's plain prefill object keyed with Application names is the correct bridge.
12. **`create.blade.php` currently hard-codes `['application' => null]`** — confirmed; the design's one-line change to `['application' => $application ?? null]` is accurate.
13. **`ApplicationController` imports** — confirmed it currently imports neither `Illuminate\Http\Request` nor `App\Models\Business`; the design's §3.1/§8 instruction to add both is correct. `create()` is currently `create(): View` with no args returning only `requirementTypes` — the design preserves `requirementTypes`.
14. **businesses migration column names** — confirmed `name/address/barangay/nature/ownership_type/dti_sec_type/dti_sec_number/number_of_employees`; the Application↔Business map in §3.1 matches exactly.
15. **`number_of_employees`** — `unsignedInteger default(0)`, no model cast; `ApplicationRequest` already uses `required|integer|min:0`; `_form-fields` renders `?? 0`. The design's coalesce-to-0 in prefill and upsert is consistent with all three.
16. **`RegisterRequest` rule values** — confirmed `contact_number => ['nullable','string','max:32']` (no regex, no message) and `password => ['required','confirmed', Password::min(8)->mixedCase()->numbers()]`. The design copies these exactly for Settings and documents the deliberate choice of the `max:32` rule over `ApplicationRequest`'s strict regex.
17. **`User` casts `password => 'hashed'`** — confirmed; assignment hashes. The password-change flow relying on this is valid.
18. **`PaymentMethods::all()`** — confirmed it returns `['gcash' => ['number'=>…, 'name'=>…, 'available'=>…], …]` from config; the Help page sourcing `all()['gcash']` is valid and respects the `available` flag.
19. **`config/permit.php`** — confirmed `valid_months` (default 12) exists and there is **no** `renewal` or `support` block today; the design correctly proposes adding both with env fallbacks and no `.env` prerequisite.
20. **`applications.pay` vs `applications.payments` routes** — confirmed `applications.pay` (GET, guarded to APPROVED_FOR_PAYMENT) and `applications.payments` (GET, read-only history) both exist; `@show` already reads a `$lastRejected` FAILED-row query, confirming multiple Payment rows per application are real. The design's §2.4 selection rule (`payments->sortByDesc('id')->first()`) and APPROVED_FOR_PAYMENT-always-links-to-`applications.pay` routing are sound.
21. **PaymentController@start flash** — confirmed the live manual-mode string is exactly "Payment details submitted. The BPLO will check your payment and confirm it. You will be notified once it is verified." The design's §6.3 before/after matches the source verbatim.
22. **No existing top-level `settings` route name** — confirmed the admin `settings` is inside `->name('admin.')`, resolving to `admin.settings`; the new applicant `settings` name is free.
23. **Applicant route group** — confirmed `['auth','role:applicant']` group at the root (no prefix); new routes fit there. `/dashboard` is `['auth']`-only with controller-side redirect — unchanged by the design.

## Unverified / Wrong Assumptions

- **None wrong.** Every ground-truth claim the design makes was confirmed against source this session.
- **Deferred to implementation time (not blocking, correctly flagged by the design itself):**
  - Whether a `Permit` factory or explicit `Permit::create` can set `valid_until` inside/outside the reminder window for the renewal test — the design notes this as an implementer check in §10. Not verified here; it is a test-authoring detail, not a design correctness claim.
  - `Auth::logoutOtherDevices()` availability — the design makes it optional ("skip if not configured") and does not depend on it. Not verified; non-blocking by construction.
  - The 154/519 green baseline — a runtime fact to be re-confirmed by the implementer after changes (the review did not run the suite, per instruction).
  - `current_password` validation rule availability — bundled with Laravel; assumed, not independently exercised. Non-blocking.

---

## Conclusion

0 HIGH, 0 MEDIUM, 4 NIT. All hard-correctness constraints (a–j) verified against live source. The Business Profile data flow (Application↔Business field map, prefill bridge, save-last/try-catch upsert) and the dashboard information architecture (four locked stat cards + four status-scoped callouts + per-application cards + kept table/notifications) are unambiguous and testable. The test plan covers the required cases: stat counts and partition, CORRECTION-remark surfacing, the REJECTED and FOR_CORRECTION no-fee branches, re-payment routing to `applications.pay`, latest-row payment selection, mid-pipeline callouts, renewal banner presence/absence, role-not-mass-assignable, wrong-current-password rejection, and the Treasurer-flash assertion.

Verdict: **APPROVED.**
