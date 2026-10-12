# Treasurer and Inspector roles with clean separation of duties

Adds two staff roles to the Bansud business permit system. Payment verification moves off BPLO onto a new Treasurer role, and a read-only Inspector role is introduced for permit compliance that leans on the existing public `/verify` pages rather than rebuilding QR scanning. BPLO keeps requirement review and permit release but loses the ability to confirm or reject payments. The implementation reuses the existing `PaymentService` (confirm/reject) and public verification controllers rather than duplicating logic, keeps `role` out of mass assignment, and adds seeders, nav, admin dropdown entries, and a feature-test matrix. The commit also carries several UI/UX changes unrelated to roles (named rate limiters, a `/verify` manual-entry route, a bulk requirements upload endpoint, CSS cache-busting, and removal of the Tailwind CDN).

**Watch for:** nothing blocking. The main thing a reviewer should know is that this commit bundles work beyond the stated role task (bulk upload, rate-limiter rename, Tailwind CDN removal) — all wired up correctly, but worth a conscious decision about whether they belong in this change. **Verdict**: APPROVED

## High-level view

The role model is clean. `ROLE_TREASURER` and `ROLE_INSPECTOR` are added as constants with matching predicates and a `homePath()` match arm each, and `role` stays out of `#[Fillable]` so the new roles can't be mass-assigned any more than the old ones could. The admin dropdown and the `UserRequest` validation `Rule::in` both pick up the two new constants, so admins can assign the roles through the existing UI without a new code path.

Separation of duties lands as specified. The Treasurer owns payment verification through a `role:treasurer,admin` route group that reuses `PaymentService::verify(..., ['source' => 'bplo_confirm'], actor)` and `PaymentService::reject(...)` — the `bplo_confirm` source string is deliberately left unchanged so `ManualGateway` keeps accepting the verification, with a comment explaining it now means "staff-confirmed." BPLO's `confirm`/`reject` routes and controller methods are gone; BPLO keeps `proof` (view receipt), requirement review, and permit release. The BPLO show page drops the Confirm/Reject forms and its `PAYMENT_PENDING` branch now reads "The Treasurer is verifying this payment," while the Release Permit action on `PAYMENT_VERIFIED` is untouched.

The Inspector is genuinely read-only: a `role:inspector,admin` group exposes only a dashboard, a paginated permit index, and a permit show that links out to the existing public `/verify/{token}` page. The controllers have no mutating actions, so there's no edit or revoke surface to leak.

The commit mixes in changes outside the role task. Named rate limiters (`throttle:login|track|verify|webhook`) replace the inline `throttle:n,m` strings, a public `GET /verify` manual-entry route is added, applicants get a bulk `requirements.uploadMany` endpoint and a `payments.proof` receipt route, the layout gains CSS cache-busting plus a `theme-color` meta, and `cdn.tailwindcss.com` is removed from the layout. These are correctly wired (the limiters are registered in `AppServiceProvider`, `uploadMany` exists on the controller) and align with the broader product requests, but they aren't part of "add two roles" and widen the blast radius of this commit.

<details>
<summary>Issues (3)</summary>

1. **Bundled out-of-scope changes** (non-blocking) — the commit mixes role work with unrelated UI/flow changes (bulk upload, rate-limiter rename, `/verify` route, CSS cache-busting, Tailwind CDN removal). Confirm these are intended for this commit or split them out.
2. **Tailwind CDN removal is a visual-risk change** (non-blocking) — the layout no longer loads `cdn.tailwindcss.com`, so any view relying on Tailwind utility classes now renders only against `public/css/app.css`. Worth a visual smoke check that no page regressed.
3. **Treasurer `index` status filter lists PENDING but confirm only acts on PROCESSING/UNVERIFIED** (non-blocking) — the index can surface payments the Treasurer can't act on; the show-page guard (`isAwaitingVerification()`) handles it safely, so this is a UX note, not a bug.

</details>

<details>
<summary>Details</summary>

## Role model and mass-assignment posture

`User` gains `ROLE_TREASURER = 'treasurer'` and `ROLE_INSPECTOR = 'inspector'`, the `isTreasurer()`/`isInspector()` predicates, and two new arms in `homePath()` (`treasurer → /treasurer/dashboard`, `inspector → /inspector/dashboard`). The `#[Fillable(['name','email','password','contact_number','address','is_active'])]` attribute still omits `role`, so neither new role widens the mass-assignment surface — the seeder sets `role` in code after `create()`, matching the existing pattern. **confirmed** by reading `app/Models/User.php` and `database/seeders/UserSeeder.php`.

The admin role dropdown (`UserController::roles()`) and the `UserRequest` `Rule::in([...])` both include the two new constants, so an admin can assign Treasurer/Inspector through the existing form and the request validation accepts them. **confirmed**.

## Payment verification moved from BPLO to Treasurer

`Treasurer\PaymentController` exposes `index/show/confirm/reject/proof` under `role:treasurer,admin` (prefix `treasurer`, name `treasurer.`). `confirm()` guards on `$payment->isAwaitingVerification()` before calling `PaymentService::verify($payment, ['source' => 'bplo_confirm'], request()->user())`, and `reject()` validates a required `reason` (max 500) before `PaymentService::reject(...)`. The `bplo_confirm` source string is unchanged, with an inline comment noting it now means "staff-confirmed" so `ManualGateway::verify` still accepts it. No verification logic is duplicated — the controller is a thin HTTP layer over the existing service. **confirmed** by reading `app/Http/Controllers/Treasurer/PaymentController.php`.

On the BPLO side, `Bplo\PaymentController` is reduced to a single `proof()` streamed-download action; `confirm()`/`reject()` are gone, and `routes/web.php` no longer declares `bplo.payments.confirm`/`bplo.payments.reject` (only `bplo.payments.proof` remains). **confirmed** from the controller and route diffs.

## BPLO show page: read-only payment status

`resources/views/bplo/show.blade.php` no longer renders Confirm/Reject forms. The payment card shows status/verification badges, reference number, sender, "Paid to," a "View receipt" link to `bplo.payments.proof`, and verified/rejection details — all read-only. The Actions `@switch` arm for `PAYMENT_PENDING` reads "The Treasurer is verifying this payment. No action is needed from BPLO," and the `PAYMENT_VERIFIED` arm still offers Release Permit. The release form posts to `bplo.applications.release` unchanged. **confirmed** by reading the full blade.

## Inspector is read-only

`Inspector\DashboardController` computes active/expired/revoked permit counts plus scans-today from `QrVerification`, and a recent-scans list — all read queries. `Inspector\PermitController` exposes only `index` (searchable, paginated released permits) and `show` (permit detail with its scan history, limited to 50). Neither controller has an edit, revoke, or any mutating action, and the route group declares only the two GET routes. The show view links to the existing public `/verify/{token}` page rather than rebuilding scanning. **confirmed** by reading both controllers and the route group.

## Bundled changes beyond the role task

The diff carries several changes that aren't part of "add two roles," all of which match earlier product requests in the broader backlog (single-file bulk requirement upload, payment UX, UI color/theme):

- Inline throttles (`throttle:5,1`, `throttle:6,1`, `throttle:30,1`) are replaced by named limiters `throttle:login|track|verify|webhook`. The limiters are registered in `AppServiceProvider::configureRateLimiting()` with the same per-minute values, so behavior is preserved and the routes resolve. **confirmed** by reading `AppServiceProvider.php`.
- A public `GET /verify` manual-entry route (`verify.lookup`) is added; the Inspector nav and show page link to it. It only renders a form that redirects to the throttled `verify.show`, so it doesn't need its own limiter.
- Applicants gain `applications.requirements.uploadMany` (bulk upload) and `payments.proof` (receipt download). `uploadMany` is backed by a real `RequirementController::uploadMany` method with a dedicated form request. **confirmed**.
- The layout removes `cdn.tailwindcss.com`, adds a `theme-color` meta, and appends `?v={filemtime(...)}` cache-busting to `app.css`.

None of these are broken, but they widen the commit's scope past the role task. A reviewer should consciously decide whether to keep them here or split them. The Tailwind CDN removal in particular is a visual-risk change: any view that depended on Tailwind utility classes now renders only against `public/css/app.css`, so a quick visual pass across the BPLO/admin/applicant pages is worth doing before shipping. **non-blocking**, flagged for awareness.

## Test coverage

The evidence in `impl-notes.md` reports `php artisan test` → 154 passed (519 assertions), exit 0, and a post-Pint targeted re-run of `Roles|Treasurer|ManualPayment|PaymentFlow|ReviewFlow` → 38 passed, exit 0, with Pint clean. New files cover the role-access matrix and login/`/dashboard` redirects (`RoleAccessTest`), separation of duties — BPLO confirm/reject routes removed, no Confirm/Reject buttons on the BPLO page, BPLO still releasing, inspector read-only (`SeparationOfDutiesTest`), and treasurer confirm/reject/dashboard/receipt (`TreasurerPaymentTest`). The previously BPLO-oriented payment tests are re-pointed to the treasurer route and actor with intent preserved — e.g. `PaymentFlowTest::test_treasurer_confirm_payment_marks_payment_verified` now posts to `/treasurer/payments/{id}/confirm` and asserts `verified_by === $treasurer->getKey()`, and `ReviewFlowTest::test_pending_payment_shows_reference_but_no_confirm_button_for_bplo` asserts the Treasurer message and absence of Confirm/Reject buttons. `makeTreasurer()`/`makeInspector()` mirror `makeBplo()`. **confirmed** by reading the test diffs and the evidence file. Per instruction, the full suite and Pint were not re-run; the recorded evidence is complete and consistent with the code read.

Not independently exercised here: the Inspector dashboard/index/show views and the Treasurer dashboard aggregate queries have thin direct view-render assertions — `SeparationOfDutiesTest` confirms inspector access and the public verify link, but the expired/revoked count logic in the inspector dashboard isn't asserted against seeded edge cases. Non-blocking given the read-only nature.

## Constraints honored

No "Bongabong" appears in application source or views (grep hits are confined to `.agents` planning docs, which are out of scope). "Bansud, Oriental Mindoro" remains the municipality via config. The public verify pages, QR SVG generation, applicant manual GCash flow, timezone handling, and permit PDF are untouched by this diff. Existing services are reused rather than duplicated. No new CSS framework or CDN is added — the only CSS-related change removes the previously present Tailwind CDN. **confirmed** by grep and diff inspection.

</details>

<details>
<summary>File map</summary>

- `app/Models/User.php` — two role constants, predicates, `homePath()` arms; `role` stays out of `#[Fillable]`.
- `app/Http/Controllers/Treasurer/PaymentController.php` — new; index/show/confirm/reject/proof over `PaymentService`.
- `app/Http/Controllers/Treasurer/DashboardController.php` — new; payment-verification aggregates.
- `app/Http/Controllers/Inspector/PermitController.php` — new; read-only index/show.
- `app/Http/Controllers/Inspector/DashboardController.php` — new; read-only permit/scan counts.
- `app/Http/Controllers/Bplo/PaymentController.php` — confirm/reject removed; only `proof` remains.
- `app/Http/Controllers/Admin/UserController.php` — role dropdown gains Treasurer/Inspector.
- `app/Http/Requests/Admin/UserRequest.php` — `Rule::in` gains the two constants.
- `routes/web.php` — treasurer/inspector groups added; bplo confirm/reject removed; named limiters; `/verify` lookup; applicant bulk upload + receipt routes.
- `resources/views/bplo/show.blade.php` — Confirm/Reject forms removed; read-only payment status; Treasurer-verifying message.
- `resources/views/treasurer/*.blade.php`, `resources/views/inspector/*.blade.php` — new views.
- `resources/views/layouts/app.blade.php` — treasurer/inspector nav blocks; Tailwind CDN removed; CSS cache-busting.
- `database/seeders/UserSeeder.php`, `DatabaseSeeder.php` — seed + print treasurer/inspector credentials.
- `tests/Support/MakesDomainData.php` — `makeTreasurer()`/`makeInspector()`.
- `tests/Feature/Roles/*`, `tests/Feature/Treasurer/*` — new; `tests/Feature/{Bplo/ReviewFlowTest,Payment/*}` — re-pointed to treasurer.

Full diff: `git diff f6f4859 29665df`

</details>
