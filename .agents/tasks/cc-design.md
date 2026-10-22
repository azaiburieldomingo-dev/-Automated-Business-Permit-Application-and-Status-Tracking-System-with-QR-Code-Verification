# CC Design — Applicant Control-Center Portal (Scope C)

Municipality of Bansud Business Permit System · Laravel 13 / PHP 8.3 (Laragon) / SQLite · timezone Asia/Manila · manual GCash payment mode. Laravel root for ALL artisan/composer/test commands: `c:\Users\HP\BUSINESS PERMIT_SYSTEM\app` (the `app` subfolder IS the Laravel root; the parent path contains a SPACE — always quote paths, e.g. `php artisan test` run with cwd `"c:\Users\HP\BUSINESS PERMIT_SYSTEM\app"`).

This is **iteration 5** — a fourth design review (`cc-design-verdict.json`, verdict `CHANGES_REQUESTED`: 0 HIGH, 2 MEDIUM, 2 NIT, reviewDoc `cc-design-review.md`) was returned against the iteration-4 design. All four findings are resolved in-line below and itemized in §12 "Review response (iteration 5)". Earlier-iteration corrections (Business Profile prefill bridge, store-upsert ordering, Carbon float handling, split callouts, required `remarks` eager load, type-safe fee-visibility guard, optional env, config-driven Help contact, Settings rules pinned to the registration source of truth, `number_of_employees` coalesce-to-0, `settings` route-name freedom, nav active-state, the two mid-pipeline informational callouts, and the REJECTED/FOR_CORRECTION no-fee test branches) are all retained. Iteration 5 additionally: (a) adds the LIVE applicant-facing `Applicant\PaymentController@start` manual-mode flash to the Modify manifest and rewrites its "The BPLO will check your payment" copy to the Treasurer wording — the constraint-2 fix now covers real applicant copy, not just the docblock/config comment (finding 1); (b) pins the fee-summary "current payment" selection rule to `payments->sortByDesc('id')->first()` and makes APPROVED_FOR_PAYMENT ALWAYS link to `applications.pay` (even when a prior FAILED payment exists), surfacing the prior `rejection_reason` on re-pay (finding 2); (c) notes the two new `use` imports `ApplicationController` gains (`Illuminate\Http\Request`, `App\Models\Business`) and that `create()` keeps passing `requirementTypes` (finding 3); (d) adds `draft`/`rejected` counts to the `$stats` array so the §2.1 clean-partition invariant references only computed variables (finding 4).

Baseline to protect: **154 passed / 519 assertions, GREEN.** Every change below must keep that green and add new tests for the three new backends (Business Profile, Settings, Renewal Reminder logic).

---

## 0. Overview

The backend is already a complete, event-driven, 9-status permit workflow. Parts A, D, and E of this build are overwhelmingly **UI surfacing plus small controller/config additions** over data that already exists; Parts B and C add genuinely new (but small) backend: a Business Profile edit flow that wires the dormant `businesses` table to the applicant, and an Account/Security Settings flow for the logged-in user. No new tables are strictly required — `businesses` already exists — and no schema change is needed anywhere. The permit+QR gate, the Treasurer-verifies-payment separation of duties, the single-source-of-truth `applications.status`, and the fees-only-after-approval rule are all preserved unchanged; this design only *reads* those surfaces, it never reassigns a role or invents a status.

Technology stack (LOCKED once approved): Laravel 13, PHP 8.3, Blade server-rendered views (no SPA, no new JS framework), existing Blade components, existing CSS in `public/css/app.css` (light government-portal theme on `body.has-sidebar` only), SQLite in dev. No new Composer packages. No Livewire/Inertia/Vite additions. Carbon (bundled with Laravel, pinned `nesbot/carbon` 3.14.2) for date math on `valid_until`.

---

## 1. Hard-correctness constraints — how this design respects each

1. **Nine statuses only, via `<x-status-badge>`.** Every status reference uses the `Application::STATUS_*` constants (confirmed present in `app/Models/Application.php`). All status chips render through `<x-status-badge :status="...">`. No new label strings ("Paid"/"For Payment"/"Released") appear anywhere. The ordered lifecycle and the `<x-process-tracker viewer="applicant">` component are reused as-is.
2. **Payment verified by TREASURER, not BPLO.** The dashboard only *reads* `applications.status` and *links* to the applicant's own `applications.pay` / `applications.payments`. It never introduces a verify action and never relabels who verifies. Every applicant-facing "who verifies payment" string is driven by the Treasurer wording the `<x-process-tracker>` already encodes (`STATUS_PAYMENT_PENDING => 'Treasurer'`), NOT by the stale `Payment::isAwaitingVerification()` docblock or the `config/permit.php` `payment.mode` comment (both of which say "BPLO" and are inherited from before the Treasurer split). **One LIVE applicant-facing string violated this and is fixed in this iteration (finding 1):** `Applicant\PaymentController@start` shows, after a manual GCash submission, the success flash *"Payment details submitted. The BPLO will check your payment and confirm it. You will be notified once it is verified."* — shown on the exact event where the dashboard's new "Payment being verified by the **Treasurer**" callout appears, a direct user-visible contradiction. §6.3 rewrites that one line to the Treasurer wording; §8 lists the file as Modify; §10 adds a test asserting the start flash contains "Treasurer" and not "BPLO". See §2.3, §2.4, §6.1, and §6.3 for where the actor wording is enforced.
3. **Single source of truth = `applications.status`.** Applicant stat cards, detail cards, and callouts all derive from the same `applications.status` column staff update. Correction/rejection explanations are read from `ApplicationRemark` (`type` = `'CORRECTION'` / `'REJECTION'`, column `body` — these are plain string literals in the codebase, NOT model constants; match that exactly) and from `ApplicationStatusHistory.note`. No second status store is created.
4. **Fees only from `FeeComputationService::computeFor($application)`; never before APPROVED_FOR_PAYMENT.** The dashboard fee summary shows an amount ONLY when `status` is at or past `APPROVED_FOR_PAYMENT` in the lifecycle order, and the amount comes from the service (or, where a `Payment` row already exists, from its snapshotted `amount`/`breakdown_json`). No total is ever hard-coded, and no amount is shown for DRAFT/SUBMITTED/UNDER_REVIEW/FOR_CORRECTION/REJECTED.
5. **Action-Required derives from STATUS, not `read_at`.** `DashboardController@index` marks all unread notifications read on load (confirmed), so the red "Action Required" stat card and the Action-needed callout are computed from `applications.status ∈ {FOR_CORRECTION, APPROVED_FOR_PAYMENT}`, never from `Notification.read_at`.
6. **Permit + QR stay gated.** The permit card's Download/View-QR links only render when `status === PERMIT_RELEASED` AND the eager-loaded `permit` relation is non-null. The routes `applications.permit` / `applications.permit.download` already `abort_if($permit === null, 404)`; this design does not touch that guard.
7. **Theme scope unchanged.** Homepage/login/register/auth pages stay DARK and are NOT edited. All new/changed views extend `layouts.app` (`body.has-sidebar`, the light theme) and reuse existing components/classes (`<x-stat-card>`, `<x-empty-state>`, `<x-status-badge>`, `<x-process-tracker>`, `<x-flash-messages>`, `<x-pagination>`, `.welcome-banner`, `.card`, `.section`, `.btn`, `.table-wrap`/`.data`, `.alert*`, `.badge*`). No new global CSS is required; if any new rule is unavoidable it goes additively into `public/css/app.css` scoped under `.has-sidebar`.
8. **Nav + routing + role safety.** New applicant-only nav items (Business Profile, Settings, Help) are added ONLY inside the `$user?->isApplicant()` branch of the inline `@php $nav` block in `layouts/app.blade.php` (flat `[url,label]` pairs; icons are not consumed). New routes live in the existing `['auth','role:applicant']` group in `routes/web.php` at the root (no prefix). `User::homePath()` is unchanged (applicant → `/dashboard`). Role is NEVER mass-assignable — new controllers follow the `App\Http\Controllers\Auth\RegisterController` pattern (`$user->fill([...only safe fields...])` and never put `role` in a FormRequest or `fill()`).

---

## 2. PART A — Control-center dashboard overhaul

### 2.1 Controller: `DashboardController@index` (extend)

File: `app/Http/Controllers/Applicant/DashboardController.php`.

Keep the existing shape (redirect non-applicants via `homePath()`, keep the mark-all-read side effect). Change the applications query to eager-load and inject computed data.

Eager loads (prevents N+1 across the per-application cards):

```php
$applications = $user->applications()
    ->with([
        'requirements.requirementType',
        'payments',
        'permit',
        'remarks', // REQUIRED: the Action-needed callout reads the latest CORRECTION remark and
                   // the REJECTED detail card reads the REJECTION remark — avoids N+1 (prior finding 5).
    ])
    ->latest()
    ->get();
```

Computed stat counts (status-derived; lifecycle order helper is in §2.4):

```php
use App\Models\Application;

$stats = [
    'total'    => $applications->count(),
    'pending'  => $applications->whereIn('status', [
        Application::STATUS_SUBMITTED,
        Application::STATUS_UNDER_REVIEW,
    ])->count(),
    'approved' => $applications->where('status', Application::STATUS_PERMIT_RELEASED)->count(),
    'action'   => $applications->whereIn('status', [
        Application::STATUS_FOR_CORRECTION,
        Application::STATUS_APPROVED_FOR_PAYMENT,
    ])->count(),

    // Counts for the two informational callouts that keep the mid-pipeline statuses
    // visible at a glance without changing the four brief-mandated stat cards.
    'released'       => $applications->where('status', Application::STATUS_PERMIT_RELEASED)->count(), // Good-news group count (== 'approved')
    'paymentPending' => $applications->where('status', Application::STATUS_PAYMENT_PENDING)->count(),  // "Treasurer is verifying" callout
    'paymentVerified'=> $applications->where('status', Application::STATUS_PAYMENT_VERIFIED)->count(), // "Permit is being prepared" callout

    // Computed so the clean-partition invariant below references only real variables
    // (finding 4). DRAFT and REJECTED have no stat card of their own; they appear on
    // their per-application detail cards. They are counted here purely to make the
    // partition assertable/testable, not to add a fifth/sixth visible card.
    'draft'    => $applications->where('status', Application::STATUS_DRAFT)->count(),
    'rejected' => $applications->where('status', Application::STATUS_REJECTED)->count(),
];
```

**Stat-card mapping is fixed by the brief and is NOT changed:** Total Applications (default/navy) = all; Pending Review (amber) = `SUBMITTED + UNDER_REVIEW`; Approved/Issued (green) = `PERMIT_RELEASED`; Action Required (red) = `FOR_CORRECTION + APPROVED_FOR_PAYMENT`. `PERMIT_RELEASED` is NOT counted in the red Action Required stat (nothing is *required* — the permit is ready; it is counted under Approved/Issued).

**Decision on `PAYMENT_PENDING` / `PAYMENT_VERIFIED` (resolves review finding 1):** the brief explicitly locks the four stat-card buckets to the status sets above, so we do NOT broaden the amber card into a nine-status "In Progress" partition (that would contradict the brief's "Pending Review amber = SUBMITTED+UNDER_REVIEW"). Instead we make the decision explicit and keep both mid-pipeline statuses visible without touching the stat semantics: `PAYMENT_PENDING` and `PAYMENT_VERIFIED` are **intentionally outside the four stat cards** (they are transient "waiting on staff" states the applicant cannot act on), and each is surfaced at the top of the page as a short **informational callout** (§2.3) plus, as always, on the per-application detail card + `<x-process-tracker>`. Net effect: every one of the nine statuses is reflected somewhere above the fold — four in stat cards, two in the Action-needed callout set they share, one in the Good-news callout, `PAYMENT_PENDING`/`PAYMENT_VERIFIED` in their own informational callouts, and `REJECTED` on its per-application card (process-tracker's rejected alert). Nothing is silently dropped, and the stat-card math still partitions cleanly across the nine statuses, using only variables the `$stats` array above actually computes (finding 4):

```
$stats['total'] === $stats['pending']          // SUBMITTED + UNDER_REVIEW
                  + $stats['action']            // FOR_CORRECTION + APPROVED_FOR_PAYMENT
                  + $stats['approved']          // PERMIT_RELEASED (== $stats['released'])
                  + $stats['paymentPending']    // PAYMENT_PENDING
                  + $stats['paymentVerified']   // PAYMENT_VERIFIED
                  + $stats['draft']             // DRAFT
                  + $stats['rejected'];         // REJECTED
```

(`$stats['approved']` and `$stats['released']` are the same `PERMIT_RELEASED` count under two names — `approved` is the stat-card label, `released` the callout label — so the partition counts `PERMIT_RELEASED` exactly once.) DRAFT and REJECTED have no stat card of their own; they are surfaced only on their per-application detail cards, but they are now counted so the invariant above can be asserted in a test.

Fee amount gating is handled in the view by comparing lifecycle position (see §2.4); the controller does NOT call `FeeComputationService` eagerly for every application (that would compute amounts even when not yet approved). The dashboard fee summary shows the amount from the application's existing `Payment` row when one exists (`payments` is eager-loaded); for an APPROVED_FOR_PAYMENT application with no payment row yet, the summary shows "Assessment ready — view fees" and links to `applications.pay` (which is the guarded page that calls `FeeComputationService`). This keeps `FeeComputationService` the single amount authority and avoids showing a number before approval.

Renewal reminder data (Part D) is computed here too — see §5.

Pass to the view: `applications`, `notifications`, `unreadCount` (unchanged), plus `stats` and `renewalReminders` (a collection of `['application','permit','daysLeft','expired']`).

### 2.2 View: rebuild `resources/views/applicant/dashboard.blade.php`

Keeps `@extends('layouts.app')` and the existing `.welcome-banner` block verbatim. Information architecture, top to bottom:

1. **Welcome banner** (unchanged copy).
2. **Renewal Reminder banner(s)** (Part D) — only rendered when `renewalReminders` is non-empty. Amber `.alert` style, one per near-expiry/expired permit, each with expiry date + guidance + link to `applications.create`. Placed high so it is seen first.
3. **Callouts** (§2.3) — four separately-labeled, independently-rendered blocks near the top, in this order: **Action needed** (FOR_CORRECTION + APPROVED_FOR_PAYMENT), **Payment being verified** (PAYMENT_PENDING, informational), **Permit being prepared** (PAYMENT_VERIFIED, informational), and **Good news** (PERMIT_RELEASED). Each block renders only when its status set is non-empty.
4. **Stat cards row** (§2.3) — 4 `<x-stat-card>` in a `.stat-grid`/`.grid` row.
5. **Per-application detail cards** (§2.4) — one `.card` per application (most recent first), each containing tracker + requirements summary + fee summary + permit card as applicable.
6. **My Applications history table** (§2.5) — the existing table, kept, with Tracking No. made prominent.
7. **Notifications list** (§2.6) — latest 20, kept.

### 2.3 Stat cards + callouts

Four stat cards, using the existing `accent` values (locked by the brief):

| Card | value | accent |
|---|---|---|
| Total Applications | `$stats['total']` | `default` (navy) |
| Pending Review | `$stats['pending']` | `amber` |
| Approved / Issued | `$stats['approved']` | `green` |
| Action Required | `$stats['action']` | `red` |

`<x-stat-card>` renders `value` with `{!! !!}` (HTML allowed) — pass integers only, no HTML injection of user data.

Four callout blocks, each over a locked status set (resolves findings 1 and 3):

**Action needed** block (`.alert`, iterating only applications whose `status ∈ {FOR_CORRECTION, APPROVED_FOR_PAYMENT}` — the exact set behind the red stat, so the item count equals `$stats['action']`):

- **FOR_CORRECTION** → `alert-warning`: "Application {tracking} needs corrections." Show the latest `ApplicationRemark` of `type === 'CORRECTION'` `body` (the staff's reason). Link → `applications.edit` (edit is allowed in this status).
- **APPROVED_FOR_PAYMENT** → `alert-info`: "Application {tracking} is approved for payment." Link → `applications.pay`.

**Payment being verified** block (informational, rendered only when `$stats['paymentPending'] > 0`; iterates applications with `status === PAYMENT_PENDING`):

- `alert-info`, no action link needed: "Application {tracking} — payment submitted. The **Treasurer** is verifying your GCash payment." The actor word "Treasurer" is taken from the process-tracker's `STATUS_PAYMENT_PENDING => 'Treasurer'` mapping (NOT from the Payment-model docblock), resolving finding 4. Optional link → `applications.payments` to view the submitted proof.

**Permit being prepared** block (informational, rendered only when `$stats['paymentVerified'] > 0`; iterates applications with `status === PAYMENT_VERIFIED`):

- `alert-info`, no action link needed: "Application {tracking} — payment verified. The **BPLO** is preparing your permit for release." The actor word "BPLO" matches the process-tracker's Permit-Release step actor (`STATUS_PAYMENT_VERIFIED => 'BPLO'`). This is a waiting state; the applicant cannot act.

**Good news** block (rendered only when `$stats['released'] > 0`; its item count equals `$stats['released']`):

- **PERMIT_RELEASED** → `alert-success`: "Your permit for {tracking} is ready to download." Link → `applications.permit`.

The CORRECTION remark is read from the **required** `remarks` eager load (locked in §2.1): the Action-needed block picks `$application->remarks->where('type','CORRECTION')->sortByDesc('created_at')->first()?->body`. (`ApplicationRemark.type` is a plain string literal `'CORRECTION'`/`'REJECTION'` with the text in column `body` — no model constants; match the literals.)

Why the two informational callouts and not an expanded stat card: the brief enumerates exactly four stat cards with fixed status sets, and reassigning the amber card to a nine-status partition would violate that wording. Surfacing the two transient statuses as top-of-page informational callouts satisfies the control-center "one glance" goal (the reviewer's second accepted option) without changing any stat-card semantics. The actor names in both callouts are sourced from the process-tracker's step table, so they can never drift to the stale "BPLO verifies payment" wording.

### 2.4 Per-application detail card

For each application render a `.card` containing:

- **Header**: business name + prominent Tracking No. (`BANSUD-YYYY-NNNNNN`, or "—" if still DRAFT/unassigned) + `<x-status-badge :status="$application->status" />` + a "View details" link → `applications.show`.
- **Process tracker**: `<x-process-tracker :application="$application" viewer="applicant" />` (ready-made; renders the 6-step progress + "What Happens Next", and already labels the Payment Verification actor as Treasurer and the Permit Release actor as BPLO).
- **Requirements checklist summary**: iterate `$application->requirements` (eager-loaded with `requirementType`). Compute counts: COMPLETE / MISSING / FOR_CORRECTION from `checklist_status`. Show e.g. "Requirements: 3 complete · 1 missing · 1 needs correction". For any requirement with `checklist_status === 'FOR_CORRECTION'`, show its `correction_note`. Link → `applications.show` (or `applications.edit` when editable) to upload/replace. Use the `ApplicationRequirement` constants `CHECK_COMPLETE`/`CHECK_MISSING`/`CHECK_FOR_CORRECTION` for comparisons.
- **Fees / payment summary** (selection + routing rule is LOCKED — resolves finding 2): render ONLY when the application is at/after APPROVED_FOR_PAYMENT in lifecycle order (see helper). `Application::payments()` is a **`hasMany`** and the system genuinely produces **multiple** `Payment` rows per application (a Treasurer rejection leaves a `STATUS_FAILED` row, bounces the status `PAYMENT_PENDING → APPROVED_FOR_PAYMENT`, and the applicant pays again, creating a second row — `Applicant\PaymentController@show` already reads a `$lastRejected = payments()->where('status', STATUS_FAILED)...` for exactly this case). So the view must pick ONE current row and must not assume a single payment:

  - **"Current payment" selection rule:** `$current = $application->payments->sortByDesc('id')->first()` (latest row by id; the eager-loaded collection is sorted in memory, no extra query). The amount and the status chip, when shown, are BOTH driven by this one `$current` row — never by a mix of rows, and never by the FAILED row when a newer one exists.
  - **Branch by status (routing is explicit so re-payment never links to the wrong page):**
    - **`status === APPROVED_FOR_PAYMENT`** → ALWAYS show "Assessment ready — view fees" and link → **`applications.pay`** (the guarded page that re-computes via `FeeComputationService`), **regardless of whether a prior FAILED `Payment` row exists.** This is the re-payment case: an application the Treasurer rejected is back at APPROVED_FOR_PAYMENT *with* a FAILED row, and the applicant needs to pay again — so it must NOT link to the read-only `applications.payments` history. If `$current` exists and is a rejected/FAILED row (`$current->status === Payment::STATUS_FAILED` with a non-null `rejection_reason`), ADDITIONALLY surface that `rejection_reason` in an `alert-warning` ("Your previous payment was not accepted: …") so the applicant knows why they are paying again. No ₱ amount is shown in this branch (the amount authority is the `applications.pay` page).
    - **`status ∈ {PAYMENT_PENDING, PAYMENT_VERIFIED, PERMIT_RELEASED}`** → show `$current->amount` (formatted ₱) and a status chip derived from `$current` (PENDING/PROCESSING/PAID), link → **`applications.payments`** (read-only history). These statuses are only reachable after a successful submit, so `$current` is the active (non-failed) row.
  - Never render this block for DRAFT/SUBMITTED/UNDER_REVIEW/FOR_CORRECTION/REJECTED. Any "awaiting verification" wording in this block uses the **Treasurer** actor (constraint 2 / finding 1), never copy derived from `Payment::isAwaitingVerification()`'s stale docblock.
- **Permit card**: render ONLY when `status === PERMIT_RELEASED` AND `$application->permit` is non-null. Show permit number, issue date, validity (`valid_until`), and two buttons: Download → `applications.permit.download`, View QR → `applications.permit`.

Lifecycle-order helper for the "at/after APPROVED_FOR_PAYMENT" test — reuse the same ordered array the process-tracker uses (8 statuses, REJECTED deliberately absent): `[DRAFT, SUBMITTED, UNDER_REVIEW, FOR_CORRECTION, APPROVED_FOR_PAYMENT, PAYMENT_PENDING, PAYMENT_VERIFIED, PERMIT_RELEASED]`. The comparison MUST be type-safe and membership-guarded so REJECTED (and any unknown status) can never pass the "at/after" test and show a fee block:

```php
$order = [
    Application::STATUS_DRAFT, Application::STATUS_SUBMITTED, Application::STATUS_UNDER_REVIEW,
    Application::STATUS_FOR_CORRECTION, Application::STATUS_APPROVED_FOR_PAYMENT,
    Application::STATUS_PAYMENT_PENDING, Application::STATUS_PAYMENT_VERIFIED, Application::STATUS_PERMIT_RELEASED,
]; // REJECTED intentionally not listed
$idx = array_search($application->status, $order, true);              // strict; false if REJECTED/unknown
$payIdx = array_search(Application::STATUS_APPROVED_FOR_PAYMENT, $order, true);
$showFees = $idx !== false && $idx >= $payIdx;                        // membership guard BEFORE index compare
```

Because `$idx` is checked against `false` first, a REJECTED application (where `array_search` returns `false`) never reaches the index comparison and never shows fees. `FOR_CORRECTION` has a lower index than APPROVED_FOR_PAYMENT, so it correctly fails too. Both branches are now exercised by the test plan (§10, resolves finding 2). **Decision:** keep this in the Blade `@php` block (no model change) to minimize surface area and avoid touching a heavily-tested model; the ordering already lives in the process-tracker component, so duplicating an 8-element array in one view is acceptable and lower-risk than a model method the tests might not expect.

### 2.5 My Applications history table (kept)

Keep the existing table. Columns: Tracking No. (prominent — wrap in `<strong>`), Business, Status (`<x-status-badge>`), Last Updated, Actions. Edit button shown only when `status ∈ {DRAFT, FOR_CORRECTION}` (unchanged rule); View always. This duplicates some info from the detail cards intentionally — the cards are the "control center" per-application view, the table is the compact history/at-a-glance list the user already has.

### 2.6 Notifications list (kept)

Latest 20, unchanged. `<x-empty-state>` when empty.

### 2.7 Edge cases (Part A)

- **No applications**: stat cards all show 0; show `<x-empty-state>` with a New Application CTA instead of the per-application cards; no callout; no renewal banner.
- **DRAFT with no tracking number**: show "—" for tracking; no fee/permit block; tracker shows DRAFT step.
- **REJECTED**: process-tracker already renders the rejected alert; show the REJECTION remark body in the detail card; no fee/permit block; not counted in any stat card or callout (verified by §10 tests).
- **PAYMENT_PENDING / PAYMENT_VERIFIED**: not in any stat card; surfaced via their informational callouts (§2.3) and the per-application tracker; fee block renders (status is at/after APPROVED_FOR_PAYMENT) showing the current Payment row's amount/chip and linking to `applications.payments` (§2.4 selection rule).
- **APPROVED_FOR_PAYMENT re-payment (prior rejected payment)**: a FAILED `Payment` row exists but the status is back at APPROVED_FOR_PAYMENT — the fee block links to `applications.pay` (NOT the history page) and shows the prior `rejection_reason` in an `alert-warning` (§2.4; asserted by a §10 test).
- **Multiple near-expiry permits**: one renewal banner each.
- **Empty fee_rates table**: `FeeComputationService` returns total 0 with a logged warning — the `applications.pay` page handles that; the dashboard only links there, so no special handling needed.

---

## 3. PART B — Business Profile (option B1)

### 3.1 Data-flow decisions (LOCKED, with rationale)

The dormant `businesses` table (`user_id`, `name`, `address`, `barangay`, `nature`, `ownership_type`, `dti_sec_type`, `dti_sec_number`, `number_of_employees`) and `Business` model already exist, as do `Application.business_id` (nullable FK) and `User->businesses()`. Decisions:

1. **One business per user (effective).** The schema allows `hasMany`, but the UX is one profile. Rule: a user edits their **first/earliest** business (`$user->businesses()->oldest()->first()`); if none exists, the form creates one. We do NOT add a DB unique constraint on `user_id` (that would be a schema change and could break if staff/admin ever create multiples); we enforce "one" at the controller/UX layer by always targeting the first row. Rationale: smallest change, backward compatible, matches the brief's "edit latest/first if multiple".
2. **Prefill on new application (field-name bridge spelled out).** The create form renders through the shared partial `resources/views/applicant/_form-fields.blade.php`, which is also used by `edit.blade.php` and binds **every** field to an **Application-shaped** object (`old('business_name', $app?->business_name)`, `old('business_address', $app?->business_address)`, `old('business_nature', $app?->business_nature)`, etc.). A `Business` has DIFFERENT column names (`name`/`address`/`nature`), so handing a raw `Business` in as `$application` would prefill NOTHING. We therefore do NOT pass a `Business` to the view. Instead, `ApplicationController@create` builds a **plain prefill object keyed with Application field names**, mapped from the user's `Business` (and personal fields from `User`), and passes it as the partial's `$application`. The partial and the edit path stay untouched, and `old()` still wins on validation redisplay because the partial already reads `old('x', $app?->x)`.

   **New imports (finding 3):** `ApplicationController` currently imports neither of the classes the redesigned `create()`/`store()` use, so the implementer MUST add `use Illuminate\Http\Request;` and `use App\Models\Business;` to the file's `use` block (it already imports `Application`, `ApplicationRequest`, `ApplicationRequirement`, `RequirementType`, `ApplicationWorkflowService`, `RedirectResponse`, `View`). Without these the `create(Request $request)` signature and the `new Business()` call below are undefined-class errors.

   `create()` is currently `create(): View` with no args and passes only `['requirementTypes' => ...]` (no `application` key today — the view's include hard-codes `['application' => null]`); it changes to inject the request/user and the prefill while **keeping `requirementTypes` in the returned array exactly as before**:

   ```php
   public function create(Request $request): View
   {
       $u = $request->user();
       $b = $u->businesses()->oldest()->first(); // one-per-user: oldest row

       // Prefill uses APPLICATION field names so _form-fields binds correctly.
       $prefill = $b ? (object) [
           'applicant_name'      => $u->name,
           'contact_number'      => $u->contact_number,
           'email'               => $u->email,
           'business_name'       => $b->name,
           'business_address'    => $b->address,
           'barangay'            => $b->barangay,
           'business_nature'     => $b->nature,
           'ownership_type'      => $b->ownership_type,
           'dti_sec_type'        => $b->dti_sec_type,
           'dti_sec_number'      => $b->dti_sec_number,
           'number_of_employees' => $b->number_of_employees ?? 0, // coalesce to 0 — matches DB default + partial '?? 0'
       ] : null;

       return view('applicant.create', [
           'requirementTypes' => RequirementType::where('is_active', true)->orderBy('sort_order')->get(),
           'application'      => $prefill, // the partial already reads $application
       ]);
   }
   ```

   The view change is one line: `create.blade.php`'s include becomes `@include('applicant._form-fields', ['application' => $application ?? null])` (today it hard-codes `['application' => null]`). When the user has no business, `$prefill` is `null` and the form renders empty exactly as it does today. The explicit Application↔Business name map used to build the object (and reused for the store upsert in item 3):

   | Application-named prefill key | Source |
   |---|---|
   | `applicant_name` | `User.name` |
   | `contact_number` | `User.contact_number` |
   | `email` | `User.email` |
   | `business_name` | `Business.name` |
   | `business_address` | `Business.address` |
   | `barangay` | `Business.barangay` |
   | `business_nature` | `Business.nature` |
   | `ownership_type` | `Business.ownership_type` |
   | `dti_sec_type` | `Business.dti_sec_type` |
   | `dti_sec_number` | `Business.dti_sec_number` |
   | `number_of_employees` | `Business.number_of_employees` |

   Prefill is purely default form values; the applicant can still edit per-application.
3. **Link on store, with best-effort upsert — exact order + null-safety.** `ApplicationController@store` creates the application (unchanged happy path), and additionally links `business_id` to the user's business, upserting one from the first application when the user has none. The **critical invariant**: `$application->save()` runs LAST and is never blocked by the business link, so an existing `business_id = null` flow (and `ApplicationFlowTest`) is unaffected. No DB transaction is used because the two writes are independent and the business link is explicitly best-effort; wrapping the business work in `try/catch` and saving the application last guarantees the application is created even if the business write throws. When the user already has a business, we LINK to it but never overwrite it from an application (the Business Profile page is the single place to edit the profile, so an application edit never silently mutates the saved profile). Exact sequence:

   ```php
   public function store(ApplicationRequest $request): RedirectResponse
   {
       $user = $request->user();
       $data = $request->validated();

       $application = new Application($data);
       $application->user()->associate($user);
       $application->status = Application::STATUS_DRAFT;

       try {
           $business = $user->businesses()->oldest()->first();
           if (! $business) {
               $business = new Business();
               $business->fill([ // Business column names, mapped from validated Application fields
                   'name'                => $data['business_name'],
                   'address'             => $data['business_address'],
                   'barangay'            => $data['barangay'],
                   'nature'              => $data['business_nature'],
                   'ownership_type'      => $data['ownership_type'],
                   'dti_sec_type'        => $data['dti_sec_type'] ?? null,
                   'dti_sec_number'      => $data['dti_sec_number'] ?? null,
                   'number_of_employees' => $data['number_of_employees'] ?? 0, // coalesce to 0
               ]);
               $business->user()->associate($user); // user_id via associate, never mass-assigned
               $business->save();
           }
           $application->business()->associate($business); // sets business_id on the in-memory model
       } catch (\Throwable $e) {
           report($e); // best-effort; a failed business link must not block application creation
       }

       $application->save(); // ALWAYS runs last; business_id stays null if the upsert threw

       return redirect()
           ->route('applications.edit', $application)
           ->with('success', 'Draft application created. Upload your requirements, then review and submit.');
   }
   ```

   Rationale: upsert-on-first-create gives the user a profile for free without surprising edits later; saving the application last makes the whole change backward compatible and null-safe. `ApplicationRequest` validation is unchanged (no new required inputs). We run `ApplicationFlowTest` specifically after this change to confirm green.
4. **Backward compatibility.** `business_id` stays nullable. Existing `business_id = null` applications keep working — every read path already tolerates null (`business()` is a nullable `belongsTo`, nothing dereferences it required). The only writes are the new link/upsert, which only run on the new-application path going forward. `ApplicationFlowTest` creates applications without a business and must stay green: the store change is null-safe (the business fields are always present because they are required by `ApplicationRequest`), adds no new required input, and is wrapped so a throw never aborts the application save.

### 3.2 Files

- **Route** (in the `['auth','role:applicant']` group, `routes/web.php`):
  - `GET /business-profile` → `BusinessProfileController@edit` · name `business-profile.edit` (shows the form; also serves as "show" — one combined view).
  - `PUT /business-profile` → `BusinessProfileController@update` · name `business-profile.update` (creates on first save, updates thereafter — single idempotent endpoint; simpler than separate store/update and matches one-per-user).
  - (No separate POST create route needed — the single PUT handles create-or-update. Rationale: one business per user makes a RESTful collection endpoint unnecessary.)
- **Controller**: `app/Http/Controllers/Applicant/BusinessProfileController.php`.
  - `edit(Request $request): View` — `$business = $request->user()->businesses()->oldest()->first();` pass to view (nullable).
  - `update(BusinessProfileRequest $request): RedirectResponse` — `$business = $user->businesses()->oldest()->first() ?? new Business; $business->fill($request->validated()); $business->user()->associate($user); $business->save();` redirect back with success flash. `user_id` is set via `associate`, never mass-assigned.
- **FormRequest**: `app/Http/Requests/BusinessProfileRequest.php` (under `App\Http\Requests\`, non-Auth) — `authorize()` returns true (route middleware already enforces applicant); rules mirror `Business` fillable:
  - `name` required string max 255
  - `address` required string max 255
  - `barangay` required string max 255
  - `nature` required string max 255
  - `ownership_type` required string max 255
  - `dti_sec_type` nullable `Rule::in(['DTI','SEC'])`
  - `dti_sec_number` nullable, `required_with:dti_sec_type`, string max 255
  - `number_of_employees` → `['required', 'integer', 'min:0']`. The column is `unsignedInteger ... default(0)` with **no model cast**, and the shared `_form-fields` partial renders it with a `?? 0` fallback. To keep the DB default, the partial, and this rule consistent, the design does NOT make the field nullable; instead the prefill (§3.1 item 2) and the store-upsert `fill()` (§3.1 item 3) both coalesce the source value to `0`, so the form always renders a concrete `0` rather than an empty string that would fail `required`. The dedicated Business Profile form always submits the rendered value (0 by default), so `required` never trips on a normal round-trip.
  - (No `user_id`, no `role` — not in the request, so not mass-assignable.)
- **View**: `resources/views/applicant/business-profile.blade.php` — extends `layouts.app`, portal-styled `.card` form, `<x-flash-messages>` (already in layout), method `@method('PUT')` + `@csrf`, fields pre-filled from `$business` (or old() on validation error). Empty-state copy when no profile yet ("You haven't set up a business profile — fill this in to speed up future applications").
- **Nav**: add `['/business-profile', 'Business Profile']` to the `isApplicant()` branch.
- **ApplicationController@create / @store**: edits per §3.1 (prefill + link/upsert).

### 3.3 Edge cases (Part B)

- User with multiple businesses (e.g. seeded by admin): always edit the oldest; others are untouched.
- Validation failure: redisplay with `old()` values; no partial write.
- `dti_sec_number` without `dti_sec_type`: allowed (both nullable); `dti_sec_type` without number: rejected by `required_with`.
- Upsert on store when business already exists: link only, never overwrite the saved profile.

---

## 4. PART C — Account / Security Settings

Source of truth for the two sensitive rules is the registration FormRequest, whose fully-qualified class is **`App\Http\Requests\Auth\RegisterRequest`** (verified at `app/Http/Requests/Auth/RegisterRequest.php` — resolves finding 3). The three NEW requests introduced below live under **`App\Http\Requests\`** (non-`Auth`), matching `ApplicationRequest`'s location; only the *rule values* are copied from `Auth\RegisterRequest`, not its namespace.

### 4.1 Decisions

- **Controller name:** `app/Http/Controllers/Applicant/SettingsController.php` (keep it inside the applicant namespace since routes are applicant-only; avoids a new `Settings\` namespace and matches how the rest of the applicant portal is organized). One controller, three actions: `edit`, `updateProfile`, `updatePassword`.
- **Role never mass-assignable:** profile update uses an explicit `$user->fill([...])` of only `name`, `contact_number`, `address` (and `email` if allowed), exactly like `App\Http\Controllers\Auth\RegisterController`. `role` and `is_active` are never in the FormRequest and never filled here.
- **Email change:** optional per brief — ALLOW it, validated `unique:users,email` except the current user (`Rule::unique('users','email')->ignore($user->id)`). Email is a normal account field, not a security boundary here; including it is low-risk with the ignore rule.
- **Password change:** requires `current_password` (validated with Laravel's `current_password` rule against the authed guard), plus `password` with `confirmed` and the **exact** registration complexity rule `Password::min(8)->mixedCase()->numbers()` — the single rule `App\Http\Requests\Auth\RegisterRequest` uses; there is no `Password::defaults()` in this app, and a bare `min:8` would diverge from the register policy. On success, `$user->password = $data['password']` (the model casts `password => 'hashed'`, so assignment hashes it) and `$user->save()`; then regenerate the session to keep the user logged in (`$request->session()->regenerate()`), optionally `Auth::logoutOtherDevices()` if the app supports it (skip if not configured — do not add new infra).

### 4.2 Files

- **Routes** (applicant group):
  - `GET /settings` → `SettingsController@edit` · name `settings` (one page, two forms: profile + password).
  - `PUT /settings/profile` → `SettingsController@updateProfile` · name `settings.profile.update`.
  - `PUT /settings/password` → `SettingsController@updatePassword` · name `settings.password.update`.
  - (Brief says "POST or PUT"; chose PUT for both updates for REST consistency with Business Profile. Blade forms use `@method('PUT')`.)
  - Naming note: `routes/web.php` has **no existing top-level route named `settings`** — the admin one is declared `Route::get('/settings', …)->name('settings')` *inside* the `->prefix('admin')->name('admin.')` group, so it resolves to `admin.settings`, and `route('settings')` is currently undefined. The new applicant `settings` name is free.
- **Controller**: `Applicant\SettingsController` with `edit`, `updateProfile(UpdateProfileRequest)`, `updatePassword(UpdatePasswordRequest)`.
- **FormRequests** (both under `App\Http\Requests\`):
  - `app/Http/Requests/UpdateProfileRequest.php`:
    - `name` → `['required', 'string', 'max:255']`
    - `contact_number` → `['nullable', 'string', 'max:32']` — **matched to the User/registration source of truth `App\Http\Requests\Auth\RegisterRequest`, NOT the strict `ApplicationRequest` regex.** `Auth\RegisterRequest` uses `['nullable','string','max:32']` with no regex and no custom message, while only `ApplicationRequest` uses `regex:/^(09|\+639)\d{9}$/` with the message "The contact number must be a valid PH mobile number…". Because existing users were created through the registration convention, they may already hold values the strict regex would reject (landlines, `+63 2 …`, spaces); applying the regex here would make a trivial name/address edit fail on a resubmitted-but-unchanged number. We reuse the registration rule exactly and add **no** `messages()` override (there is nothing to reuse — `Auth\RegisterRequest` defines no message for this field). A regression test (§10) asserts that a profile save preserving an existing `max:32`-style number succeeds.
    - `address` → `['nullable', 'string', 'max:255']`
    - `email` → `['required', 'email', 'max:255', Rule::unique('users','email')->ignore($this->user()->id)]`
    - No `role` / `is_active` keys.
  - `app/Http/Requests/UpdatePasswordRequest.php`:
    - `current_password` → `['required', 'current_password']` (validates against the authed guard).
    - `password` → `['required', 'confirmed', Password::min(8)->mixedCase()->numbers()]` — **pinned to the exact `App\Http\Requests\Auth\RegisterRequest` rule.** There is NO `Password::defaults()` call anywhere in the app, and a bare `min:8` would accept passwords that registration rejects. Import `use Illuminate\Validation\Rules\Password;`. The form supplies `password_confirmation`. A test (§10) asserts the change-password policy matches registration (a value accepted at register is accepted here; one rejected at register is rejected here).
- **View**: `resources/views/applicant/settings.blade.php` — extends `layouts.app`, two `.card` sections ("Profile information" and "Change password"), each its own form with `@csrf` + `@method('PUT')`, `<x-flash-messages>` + per-field `@error`. Show logout affordance note (logout already in the sidebar footer).
- **Nav**: add `['/settings', 'Settings']` to the `isApplicant()` branch.

### 4.3 Edge cases (Part C)

- Wrong current password → `current_password` rule fails → redisplay with error; password unchanged (verified by a test).
- Email change to an email owned by another user → unique validation fails.
- Password confirmation mismatch → `confirmed` fails.
- Successful password change keeps the user logged in (session regenerated), does not change `role`.

---

## 5. PART D — Renewal Reminder (option R1, reminder only)

### 5.1 Decisions

- **No `application_type` column, no renewal application flow.** Read-only logic driven entirely by `permit.valid_until` (a `date` cast on `Permit`, confirmed). `valid_until` is already set at release to `issued_at + config('permit.valid_months')` (default 12).
- **Config keys** (added to `config/permit.php` under a new `renewal` block, with env fallbacks):
  - `config('permit.renewal.reminder_days', 30)` — window (days before expiry) to start reminding.
  - `config('permit.valid_months')` already exists (default 12) — referenced for copy only; not recomputed here.
  Add:
  ```php
  'renewal' => [
      'reminder_days' => (int) env('PERMIT_RENEWAL_REMINDER_DAYS', 30),
  ],
  ```
  Referenced as `config('permit.renewal.reminder_days', 30)`. (Brief wrote `config('permit.renewal_reminder_days', 30)`; nesting it under `renewal` keeps config tidy and the default guards either way. Document the exact key as `permit.renewal.reminder_days`.)

  **No `.env` edit is required for the feature to run.** `config/permit.php` reads `env('PERMIT_RENEWAL_REMINDER_DAYS', 30)` and every caller also passes a `30` default to `config()`, so the 30-day window applies everywhere even on an existing deployed `.env` that lacks the key. Adding `PERMIT_RENEWAL_REMINDER_DAYS` to `.env`/`.env.example` is **optional tuning**, not a prerequisite.
- **Dashboard computation** (in `DashboardController@index`): the installed Carbon is **3.14.2**, where `diffInDays()` returns a signed **float** (not an int). The design floors and casts explicitly and computes from start-of-day on both sides so `daysLeft` is a clean integer count of whole days:

  ```php
  use Carbon\Carbon;

  $daysLeft = (int) floor(
      Carbon::now()->startOfDay()->diffInDays($permit->valid_until->copy()->startOfDay(), false)
  );
  $expired = $daysLeft < 0; // negative = already past valid_until
  ```

  `daysLeft` is documented as an **int** after the floor+cast. Flag a reminder when `$daysLeft <= config('permit.renewal.reminder_days', 30)` (covers both near-expiry and already-past). Build `renewalReminders` as a collection of `['application' => ..., 'permit' => ..., 'daysLeft' => $daysLeft, 'expired' => $expired]`. (`$permit->valid_until` is `date`-cast to midnight, so the `->copy()->startOfDay()` is defensive and the result has no fractional component.)
- **Banner** (dashboard view, §2.2 item 2): amber `.alert` per reminder: expiry date (`valid_until->format('M d, Y')`), and copy driven by the int `daysLeft` — "expires in {{ $daysLeft }} day(s)" when `! $expired`, or "expired {{ abs($daysLeft) }} day(s) ago" when `$expired` (print `abs($daysLeft)` so the expired copy never shows a minus sign); plus guidance — "Contact the BPLO or start a new application to renew." with a link to `applications.create` (New Application). No new status, no renewal-specific route.
- **Optional in-app Notification for upcoming expiry:** the brief makes the banner the minimum and the notification optional. **Decision: defer the scheduled notification** (it needs a scheduler/cron + a command + de-dup logic to avoid spamming daily) and ship the dashboard banner only in this iteration. Rationale: the banner fully satisfies the requirement and is testable without time-travel infra; a scheduled notification is a backlog item. If later wanted: an artisan command `permits:remind-expiring` run daily, guarded by a "last reminded" marker to send once per window.

### 5.2 Edge cases (Part D)

- Permit valid far in the future (daysLeft > reminder window) → NO banner (asserted by a test).
- Permit within window → banner with "expires in N days".
- Permit already expired (`valid_until` past) → banner with "expired" wording.
- Application without a permit → no reminder (only released permits have `valid_until`).
- Revoked permit (`status === REVOKED`): **decision:** skip reminders for revoked permits (a revoked permit is not a candidate for routine renewal). Filter to `Permit::STATUS_ACTIVE` when selecting reminder candidates.

---

## 6. PART E — Help / FAQ page

### 6.1 Files

- **Route** (applicant group): `GET /help` → `HelpController@index` · name `help`. (A controller returning a view is cleaner than an inline `Route::view` because it lets us inject config-driven payment details.)
- **Controller**: `app/Http/Controllers/Applicant/HelpController.php` — `index(): View` passing payment methods from the single source `App\Support\PaymentMethods::all()` (so GCash number/name come from config, not hard-coded), plus `config('permit.municipality')`, `config('permit.province')`, `config('permit.valid_months')`, `config('permit.renewal.reminder_days')`, and the new `config('permit.support')` block (below).
- **Config decision for municipal contact details.** `config/permit.php` currently has NO support/contact block (confirmed). Rather than hard-coding office hours/phone/email as Blade prose, add a nullable `support` block to `config/permit.php` with env fallbacks defaulting to `null`:

  ```php
  'support' => [
      'office_hours'  => env('BPLO_OFFICE_HOURS'),   // e.g. "Mon–Fri, 8:00 AM – 5:00 PM"
      'office_address'=> env('BPLO_OFFICE_ADDRESS'),
      'contact_phone' => env('BPLO_CONTACT_PHONE'),
      'contact_email' => env('BPLO_CONTACT_EMAIL'),
  ],
  ```

  The Help view renders each value when present and shows "Not yet provided" (muted) when `null`, so no phone/email is ever fabricated and a future change is a config/`.env` edit, not a Blade code edit. These env keys are OPTIONAL — absent keys simply render "Not yet provided". Headings use the existing `config('permit.municipality')` / `config('permit.province')`.
- **View**: `resources/views/applicant/help.blade.php` — extends `layouts.app`, portal `.card` sections:
  - **BPLO office hours & contact** — render from `config('permit.support')` (office hours, office address, phone, email); each null value shows the muted "Not yet provided" placeholder. Heading uses `config('permit.municipality')` + `config('permit.province')`. Never fabricate a value.
  - **GCash manual-payment instructions** — pull the GCash number/name from `PaymentMethods::all()['gcash']` (`number` = `09670829993`, `name` = `Aiza Domingo` come from `.env`/config, surfaced via the support class — NOT hard-coded in the Blade). Explain: pay to that account, then on the Pay Fees page submit the reference number + payer name/number + receipt proof; **the Treasurer verifies it** (actor wording consistent with the process-tracker and the dashboard callouts — never "BPLO verifies", resolves finding 4).
  - **Short FAQ** — the flow in plain language: application → BPLO review → (corrections if needed) → approved for payment → pay via GCash → **Treasurer verifies** → BPLO releases permit → download permit + QR. Uses the real nine-status language via `<x-status-badge>` where helpful, no invented labels.
  - **Tracking & verification** — explain the `BANSUD-YYYY-NNNNNN` tracking number, the public `/track` page (tracking number + email or last name), and QR verification at `/verify` / `/verify/{token}`.
- **Nav**: add `['/help', 'Help']` to the `isApplicant()` branch.

### 6.2 Edge cases (Part E)

- A payment method with no configured number shows as "not yet available" (already handled by `PaymentMethods::all()` `available` flag) — the Help page respects that.
- Purely read-only page; no inputs, no auth beyond the applicant middleware.

### 6.3 Live payment-flash copy fix (constraint 2 / finding 1)

File: `app/Http/Controllers/Applicant/PaymentController.php`, method `start()`. This is the ONLY behavioral change outside Parts A–E's additions, and it is a one-line string edit (no logic change). The current manual-mode flash names the BPLO as verifier, contradicting constraint 2 and the dashboard's new PAYMENT_PENDING callout. Change:

```php
// BEFORE
$message = PaymentMethods::isManual()
    ? 'Payment details submitted. The BPLO will check your payment and confirm it. You will be notified once it is verified.'
    : 'Payment initiated. Your payment is being processed and will be confirmed shortly.';

// AFTER
$message = PaymentMethods::isManual()
    ? 'Payment details submitted. The Treasurer will verify your GCash payment and confirm it. You will be notified once it is verified.'
    : 'Payment initiated. Your payment is being processed and will be confirmed shortly.';
```

The non-manual branch is unchanged (it names no actor). The "Treasurer" actor matches the `<x-process-tracker>` step table (`STATUS_PAYMENT_PENDING => 'Treasurer'`) — the single codebase source for the correct verifier — so the redirect flash and the dashboard callout now agree. A §10 test (`ManualPaymentTest` or a new assertion) checks the manual-mode start flash contains "Treasurer" and does not contain "BPLO". The stale `Payment::isAwaitingVerification()` docblock and the `config/permit.php` `payment.mode` comment are still only an OPTIONAL cosmetic cleanup (§8) because they are not applicant-visible copy; the live flash above is the one that mattered.

---

## 7. Nav changes (summary)

In `layouts/app.blade.php`, the `isApplicant()` branch becomes:

```php
$nav = [
    ['/dashboard', 'Dashboard'],
    ['/applications/create', 'New Application'],
    ['/business-profile', 'Business Profile'],
    ['/settings', 'Settings'],
    ['/help', 'Help'],
];
```

Only this branch changes; BPLO/treasurer/inspector/admin branches are untouched. Active-link detection already works by path prefix — the layout computes `$current = '/'.ltrim(request()->path(),'/')` and marks `$active = $current === $url || str_starts_with($current, rtrim($url,'/').'/')`.

Active-state note: only `GET /settings`, `GET /business-profile`, and `GET /help` are navigable. The Settings sub-endpoints `PUT /settings/profile` and `PUT /settings/password` and `PUT /business-profile` are `PUT`-only form targets, not navigable GET pages, so they never appear as links and there is no GET sub-page to mis-highlight. Validation redisplays redirect back to the GET page, which highlights cleanly.

---

## 8. Complete file manifest

**Modify:**
- `routes/web.php` — add 6 applicant routes (business-profile edit/update, settings edit/profile-update/password-update, help).
- `app/Http/Controllers/Applicant/DashboardController.php` — eager loads, stat counts (incl. `released`/`paymentPending`/`paymentVerified`), renewal reminder computation.
- `app/Http/Controllers/Applicant/ApplicationController.php` — add `use Illuminate\Http\Request;` and `use App\Models\Business;` (finding 3); `create` prefill from Business/User (keeps passing `requirementTypes`); `store` link + upsert `business_id`.
- `app/Http/Controllers/Applicant/PaymentController.php` — change ONLY the manual-mode success flash in `start()` from "The BPLO will check your payment and confirm it." to the Treasurer wording (finding 1). No other logic changes; this is the live applicant-facing string that must agree with the dashboard's "Treasurer is verifying" callout (constraint 2).
- `resources/views/applicant/dashboard.blade.php` — full IA rebuild (stat cards, four callout blocks, renewal banner, per-application cards, kept table + notifications).
- `resources/views/applicant/create.blade.php` — one-line include change to pass `$application` prefill.
- `resources/views/layouts/app.blade.php` — add 3 applicant nav items.
- `config/permit.php` — add `renewal.reminder_days` (Part D) and a nullable `support` block (office_hours/office_address/contact_phone/contact_email, env-backed, default null — Part E).
- `.env.example` — OPTIONAL: add `PERMIT_RENEWAL_REMINDER_DAYS=30` and the four `BPLO_*` support keys. Not required; the `config()` defaults guard when absent.
- (Optional, NIT #4) `app/Models/Payment.php` docblock + `config/permit.php` `payment.mode` comment — fix stale "BPLO verifies" wording to "Treasurer verifies" while nearby. Cosmetic; no behavior change. Not required for correctness since no applicant copy is derived from these.

**Add:**
- `app/Http/Controllers/Applicant/BusinessProfileController.php`
- `app/Http/Controllers/Applicant/SettingsController.php`
- `app/Http/Controllers/Applicant/HelpController.php`
- `app/Http/Requests/BusinessProfileRequest.php`
- `app/Http/Requests/UpdateProfileRequest.php`
- `app/Http/Requests/UpdatePasswordRequest.php`
- `resources/views/applicant/business-profile.blade.php`
- `resources/views/applicant/settings.blade.php`
- `resources/views/applicant/help.blade.php`

**No new migrations** (businesses table already exists; no `application_type`; no schema change). **No new Composer packages.**

---

## 9. Error handling & validation (per operation)

- **Business Profile update** — all inputs validated by `BusinessProfileRequest`; failure → 422/redirect-back with errors (recoverable, not logged as error, standard Laravel validation). `user_id` set via `associate`, never from input. DB write is a single `save()`; a DB exception is a fatal 500 (standard framework handling) — not expected under normal input.
- **New-application store upsert** — runs only after `ApplicationRequest` validation passes; uses the already-validated fields, **mapped to Business column names** (`business_name`→`name`, `business_address`→`address`, `business_nature`→`nature`; the rest are same-named — see §3.1 item 3). Order is fixed: build the application in memory → inside `try/catch` find-or-create+save the Business and `associate()` it onto the application → `report($e)` and swallow on any throw → `$application->save()` LAST, unconditionally. No transaction (the two writes are independent and the business link is best-effort). Because the application is saved last and the business link never aborts it, a thrown upsert leaves `business_id` null and the application still created — so `ApplicationFlowTest` (no prior business) stays green. `user_id` on the new Business is set via `associate`, never mass-assigned.
- **Settings profile update** — `UpdateProfileRequest`; email uniqueness enforced with `ignore(current)`; failure → redirect-back with errors. Role/`is_active` never writable.
- **Settings password update** — `current_password` rule rejects a wrong current password (recoverable, redirect-back error); `confirmed` enforces match; on success the model hashes via the `hashed` cast and session is regenerated. No plaintext password is ever logged.
- **Renewal reminder computation** — pure read; a null `valid_until` (shouldn't happen for a released permit, but guard anyway) → skip that permit (no reminder, no error); revoked permit → skipped.
- **Help page** — read-only; missing config values degrade to labeled placeholder copy, never a crash.
- **Dashboard** — unchanged redirect for non-applicants; eager loads are null-safe; no new failure modes. Fee amounts only ever come from `FeeComputationService` (via the `applications.pay` page) or a persisted `Payment` row.

Invariant ownership:
- "One business per user" — enforced at the controller/UX layer (always target oldest business), not the DB, to avoid a risky schema constraint on an existing table. Documented so a future dev doesn't assume a DB guarantee.
- "Role never changes" — owned by the FormRequests (role absent) + controllers (explicit `fill` of safe fields), mirroring `Auth\RegisterController`.
- "Fee amount authority" — owned solely by `FeeComputationService` / persisted `Payment`; the dashboard never computes or hard-codes amounts.
- "Payment verified by Treasurer" — the applicant portal never asserts an actor of its own; all "who verifies" copy is sourced from the `<x-process-tracker>` step table (`PAYMENT_PENDING => 'Treasurer'`), the one place in the codebase that already encodes the correct actor.
- "Permit gate" — owned by the existing `PermitService::release()` + route `abort_if` + `PermitPolicy`; the dashboard only conditionally renders links and never bypasses the gate.

---

## 10. Test plan

Run from `"c:\Users\HP\BUSINESS PERMIT_SYSTEM\app"` with `php artisan test`. Keep the 154/519 baseline green; add new feature tests.

**Regression guard (must stay green):**
- `tests/Feature/Applicant/ApplicationFlowTest.php` — especially the happy path and resubmit-keeps-tracking-number. The store upsert/link must not break these (applications without a prior business still submit).
- `tests/Feature/Roles/MassAssignmentTest.php` and role middleware tests — new controllers must not introduce a mass-assignable role.

**New tests to add:**

- `tests/Feature/Applicant/BusinessProfileTest.php`
  - creates a business on first save (asserts DB row with `user_id` = authed user).
  - updates the existing business on second save (one row, values changed — confirms one-per-user by editing oldest).
  - `role` is NOT mass-assignable: POST/PUT the business-profile (and settings/profile) endpoints with a `role` field in the payload and assert the user's role is unchanged (`applicant`). (Covers the brief's "role-not-mass-assignable" requirement.)
  - new-application store links `business_id` and (when no prior business) upserts one; existing `business_id = null` applications still work.
- `tests/Feature/Applicant/SettingsTest.php`
  - profile update changes `name`/`contact_number`/`address`/`email`; role unchanged.
  - email uniqueness: cannot take another user's email.
  - password change with correct current password succeeds and the new password authenticates.
  - **password change with WRONG current password is rejected** (asserts validation error + password unchanged).
  - profile save preserving an existing `max:32`-style `contact_number` succeeds (guards the registration-rule choice over the strict `ApplicationRequest` regex).
  - change-password policy parity: a password accepted at registration is accepted here; one rejected at registration is rejected here.
- `tests/Feature/Applicant/DashboardTest.php` (or extend an existing dashboard test)
  - stat counts: given applications in known statuses, assert the four counts (Pending = SUBMITTED+UNDER_REVIEW, Approved = PERMIT_RELEASED, Action = FOR_CORRECTION+APPROVED_FOR_PAYMENT).
  - Action-Required callout shows the CORRECTION remark body for a FOR_CORRECTION application.
  - **Fee-visibility guard branches (resolves finding 2):**
    - fee amount is NOT shown for an UNDER_REVIEW application.
    - fee amount / "Assessment ready" copy IS reachable (link) once APPROVED_FOR_PAYMENT.
    - **a REJECTED application renders NO fee/payment block and NO permit block** — `assertDontSee` the ₱ amount and the "Assessment ready" copy for that application (this is the exact `array_search(...) === false` branch the §2.4 guard exists to protect).
    - **a FOR_CORRECTION application renders NO fee block** (lower index than APPROVED_FOR_PAYMENT — the other direction the index compare could go wrong).
    - **re-payment routing (finding 2):** an `APPROVED_FOR_PAYMENT` application that already has a `STATUS_FAILED` `Payment` row (with a `rejection_reason`) links the fee summary to **`applications.pay`** (NOT `applications.payments`) and surfaces the rejection reason. Assert the rendered link/route is the pay page and the reason text is shown.
    - **current-payment selection (finding 2):** a `PAYMENT_PENDING`/`PAYMENT_VERIFIED` application that has an older `STATUS_FAILED` row plus a newer active row shows the **newer** row's amount/chip (latest-by-id), not the failed row's — `assertSee` the active amount and that no failed/red chip is shown for it.
  - mid-pipeline visibility: a PAYMENT_PENDING application shows the "Treasurer is verifying" informational callout (and is not counted in any of the four stat cards); a PAYMENT_VERIFIED application shows the "permit being prepared" callout.
  - **stat partition (finding 4):** with a fixture spanning all nine statuses, assert `total === pending + action + approved + paymentPending + paymentVerified + draft + rejected` holds on the `$stats` array the controller returns (every status counted exactly once; PERMIT_RELEASED counted once because `approved === released`).
  - **Renewal Reminder:** a permit with `valid_until` within the reminder window shows the banner; a permit with `valid_until` far in the future does NOT (assert presence/absence of the banner copy/expiry date). Use a released application + permit with controlled `valid_until`. **Implementer note:** confirm a Permit factory (or explicit `Permit::create`) can set `valid_until` inside/outside the window before writing this test; the investigation references `tests/Feature/Permit/PermitTest.php` which creates permits.
- `tests/Feature/Applicant/HelpPageTest.php` (light)
  - page renders for an applicant; GCash number from config is present; guest/other-role access blocked by middleware.
- `tests/Feature/Payment/ManualPaymentTest.php` (extend existing, finding 1)
  - after a manual GCash `start()` submission, the redirect/flash message contains **"Treasurer"** and does **not** contain "BPLO". Guards the constraint-2 copy fix so it can't silently regress.

Unit-testable vs integration-testable: the renewal `daysLeft`/window decision is pure and could be a tiny unit test if extracted to a helper; as designed it lives in the controller and is covered by the dashboard feature test (integration). Business Profile field-mapping (Business↔Application) is best covered by the application-store feature test. Validation rules are covered by the FormRequest-driven feature tests above. The REJECTED/FOR_CORRECTION no-fee branches are covered by the dashboard feature test above.

After implementation: run the full suite, then the targeted `ApplicationFlowTest` and `MassAssignmentTest` to confirm no regression, and clean up any temporary artifacts.

---

## 11. Ambiguities resolved & assumptions to confirm

Resolved in-design (reasonable defaults, flagged for the review):
1. **Mid-pipeline statuses in the summary**: the four stat cards are left exactly as the brief dictates (amber = SUBMITTED+UNDER_REVIEW); `PAYMENT_PENDING`/`PAYMENT_VERIFIED` are surfaced via two top-of-page informational callouts rather than by redefining the amber card. Confirm this is preferred over collapsing them into an "In Progress" card (which would contradict the brief's card definitions).
2. **Upsert-on-store**: chosen to create a Business from the first application's fields but never overwrite an existing profile from an application edit. (Alt: no upsert, profile only via the dedicated page — rejected as less helpful.)
3. **One-business-per-user** enforced at controller/UX layer (edit oldest), not a DB unique constraint — avoids a schema change on an existing table. Confirm acceptable vs. a hard DB constraint.
4. **Email change allowed** in Settings (with unique-ignore-self). Confirm the municipality wants self-service email change; if not, drop `email` from `UpdateProfileRequest` (trivial).
5. **Renewal = banner only this iteration**; scheduled in-app notification deferred to backlog (needs scheduler + de-dup). Confirm the banner-only scope is sufficient for R1 (the brief says it is the minimum).
6. **Settings controller placed in `Applicant\` namespace** (not a new `Settings\` namespace) since the routes are applicant-only. Confirm acceptable.
7. **Config key** for the reminder window documented as `permit.renewal.reminder_days` (nested) rather than the flat `permit.renewal_reminder_days` the brief sketched; the default `30` guards either spelling. Confirm the nested key is fine.

Assumptions (believed safe from ground truth, not re-confirmed by running): the `password` cast `hashed` on `User` hashes on assignment (confirmed in `User::casts()`); `current_password` validation rule is available (bundled Laravel); `PaymentMethods::all()` returns the gcash account with number/name from config (confirmed by the investigation + review); `App\Http\Requests\Auth\RegisterRequest` uses `contact_number => ['nullable','string','max:32']` and `password => ['required','confirmed', Password::min(8)->mixedCase()->numbers()]` (confirmed by the iteration-3 review). Help-page municipal office hours/address/phone/email are NOT currently in config — a nullable `config('permit.support')` block is added (§6.1) and the view renders "Not yet provided" for any unset value, so nothing is fabricated.

---

## 12. Review response (iteration 5)

Disposition against the current `cc-design-verdict.json` (verdict `CHANGES_REQUESTED`: 0 HIGH, 2 MEDIUM, 2 NIT; reviewDoc `cc-design-review.md`, iteration-4 review). All four findings **ADDRESSED** — none backlogged or ignored. Each change was verified against the live source re-read this iteration (`Applicant\PaymentController.php`, `Applicant\ApplicationController.php`, `Application.php`, `Business.php`, `process-tracker.blade.php`) and aligns with the original requirements (nine statuses only; Treasurer verifies payment; single source of truth on `applications.status`; fees only from `FeeComputationService` and only ≥ APPROVED_FOR_PAYMENT; Action-Required from status not `read_at`; permit gate intact; role never mass-assignable; theme scope unchanged). No earlier-iteration resolution (including the iteration-4 mid-pipeline callouts and REJECTED/FOR_CORRECTION no-fee tests) was reverted.

1. **MEDIUM — live applicant-facing `PaymentController@start` flash still says "The BPLO will check your payment", contradicting constraint 2.** ADDRESSED. Verified against live source: `app/Http/Controllers/Applicant/PaymentController.php@start` sets, for manual GCash, the success flash *"Payment details submitted. The BPLO will check your payment and confirm it. …"*. This is shown on the exact event where the dashboard's new PAYMENT_PENDING callout says "the **Treasurer** is verifying" — a user-visible contradiction. §6.3 (new) specifies the one-line change to the Treasurer wording; §8 adds the file to the Modify manifest; §1 constraint 2 now names the string explicitly; §10 adds a `ManualPaymentTest` assertion that the start flash contains "Treasurer" and not "BPLO". The non-manual branch (which names no actor) is untouched. The docblock/config comment remain an OPTIONAL cosmetic cleanup since they are not applicant-visible.

2. **MEDIUM — fee-summary rule underspecified with multiple `Payment` rows and links to the wrong page on re-payment.** ADDRESSED in §2.4 (rewritten) and §2.7. `Application::payments()` is a `hasMany` and a Treasurer rejection genuinely produces multiple rows (confirmed: `@show` already reads a `$lastRejected` FAILED-row query). The design now pins the "current payment" to `$application->payments->sortByDesc('id')->first()` (latest by id, in-memory on the eager-loaded collection) and drives BOTH the amount and the status chip from that one row. Routing is now explicit by status: **APPROVED_FOR_PAYMENT always links to `applications.pay`** (even when a prior FAILED row exists — the re-payment case) and surfaces the prior `rejection_reason` in an `alert-warning`; PAYMENT_PENDING/PAYMENT_VERIFIED/PERMIT_RELEASED show the current row's amount/chip linking to `applications.payments`. §10 adds two tests: the re-pay case links to `applications.pay` and shows the reason, and the latest-row selection shows the active row (not the failed one).

3. **NIT — `ApplicationController` create/store snippets need new imports not in the manifest.** ADDRESSED in §3.1 (new imports paragraph) and §8. The design now states `ApplicationController` gains `use Illuminate\Http\Request;` and `use App\Models\Business;` (verified absent from the current file), and that the redesigned `create()` must keep passing `requirementTypes` alongside the `application` prefill (it already does in the snippet; the point is now called out so an implementer following verbatim does not drop it).

4. **NIT — §2.1 partition invariant referenced `draftCount`/`rejectedCount` the controller never computes.** ADDRESSED in §2.1. The `$stats` array now also computes `'draft'` and `'rejected'` counts, and the clean-partition invariant is rewritten to reference only those real `$stats` keys (`total === pending + action + approved + paymentPending + paymentVerified + draft + rejected`), with an explicit note that `approved` and `released` are the same PERMIT_RELEASED count under two labels so it is counted once. §10 adds a test asserting the partition holds across a nine-status fixture.

The reviewer's "Verified Assumptions" (1–20) and "Unverified" items remain implementation-time checks: `Auth::logoutOtherDevices()` is optional (skip if `AuthenticateSession`/session driver is not configured — the design does not depend on it); `current_password` is a bundled Laravel rule; the `password => 'hashed'` cast is confirmed present; a Permit factory/`Permit::create` must be confirmed able to set `valid_until` before the renewal test is written (noted in §10); and the 154/519 baseline must be re-run by the implementer after the change (plus targeted `ApplicationFlowTest`, `MassAssignmentTest`, and `ManualPaymentTest`). None of these are blockers or alter the locked decisions above.
