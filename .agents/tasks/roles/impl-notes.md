# Roles: Treasurer + Inspector — Implementation Notes

First iteration (no `.agents/tasks/roles/review.json` existed).

## What changed

### New roles on User model (`app/Models/User.php`)
- Added `ROLE_TREASURER = 'treasurer'` and `ROLE_INSPECTOR = 'inspector'`.
- Added `isTreasurer()` / `isInspector()`.
- Extended `homePath()`: treasurer → `/treasurer/dashboard`, inspector → `/inspector/dashboard`.
- `role` is still excluded from `#[Fillable(...)]` — never mass-assignable.

### Treasurer (verifies GCash payments — moved OUT of BPLO)
- Route group `role:treasurer,admin`, prefix `treasurer`, name `treasurer.`:
  - `GET /treasurer/dashboard` → `Treasurer\DashboardController`
  - `GET /treasurer/payments` (`treasurer.payments.index`)
  - `GET /treasurer/payments/{payment}` (`treasurer.payments.show`)
  - `POST /treasurer/payments/{payment}/confirm` (`treasurer.payments.confirm`)
  - `POST /treasurer/payments/{payment}/reject` (`treasurer.payments.reject`)
  - `GET /treasurer/payments/{payment}/receipt` (`treasurer.payments.proof`)
- Dashboard cards: Awaiting Verification (PROCESSING + UNVERIFIED), Verified Today, Total Verified, Total Collected (sum of PAID amounts) + table of payments awaiting verification.
- `confirm`/`reject` REUSE `PaymentService::verify(..., ['source' => 'bplo_confirm'], actor)` and `PaymentService::reject(...)`. The `bplo_confirm` source string is UNCHANGED so `ManualGateway::verify` still works (it now means "staff-confirmed").
- Views in `resources/views/treasurer/` (dashboard, index, show) reuse existing theme classes.

### BPLO payment verification REMOVED
- Deleted routes `bplo.payments.confirm` and `bplo.payments.reject` from `routes/web.php`.
- Removed `confirm()` / `reject()` from `app/Http/Controllers/Bplo/PaymentController.php` (KEPT `proof` so BPLO can still view the receipt).
- `resources/views/bplo/show.blade.php`: removed the Confirm/Reject forms; kept read-only payment info + "View receipt" link. The Actions `@switch` for `PAYMENT_PENDING` now reads "The Treasurer is verifying this payment." (no BPLO action). `PAYMENT_VERIFIED` → "Release Permit" is unchanged.

### Inspector (QR scan + read-only compliance)
- Route group `role:inspector,admin`, prefix `inspector`, name `inspector.`:
  - `GET /inspector/dashboard` → `Inspector\DashboardController`
  - `GET /inspector/permits` (`inspector.permits.index`)
  - `GET /inspector/permits/{permit}` (`inspector.permits.show`)
- Dashboard cards: Active, Expired, Revoked permits, Scans Today (qr_verifications created today) + recent scans table + prominent link to the existing public `/verify` manual-entry page.
- Index: searchable/paginated released permits with a computed ACTIVE/EXPIRED/REVOKED indicator (valid_until vs today).
- Show: read-only permit detail + verification link + "Open Verification Page" button (public `/verify/{token}`, new tab) + that permit's scan history. No edit/revoke actions.
- Views in `resources/views/inspector/` (dashboard, index, show).

### Admin
- `UserController::roles()` and `UserRequest` `Rule::in([...])` now include `ROLE_TREASURER` and `ROLE_INSPECTOR`.

### Shared UI
- `resources/views/layouts/app.blade.php`: added `@elseif ($user->isTreasurer())` (Dashboard, Payments) and `@elseif ($user->isInspector())` (Dashboard, Permits, Verify) nav blocks.
- `/dashboard` redirect and login redirect already use `homePath()` via the match, so the two new roles land on their own dashboards.

### Seeders
- `UserSeeder`: seeds `treasurer@bansud.gov.ph` and `inspector@bansud.gov.ph` (password `Password123!`, is_active=true, role set in code).
- `DatabaseSeeder`: printed credentials list now includes Treasurer and Inspector.

### Test helpers
- `tests/Support/MakesDomainData.php`: added `makeTreasurer()` and `makeInspector()` (mirror `makeBplo()`).

## Tests

New test files:
- `tests/Feature/Roles/RoleAccessTest.php` — role access matrix (treasurer/inspector/applicant/admin), `/dashboard` + login redirects for the two new roles. (7 tests)
- `tests/Feature/Roles/SeparationOfDutiesTest.php` — bplo confirm/reject routes removed; bplo show page has no Confirm/Reject button but keeps payment info; bplo still releases after payment verified; inspector sees released permits + public verify link; inspector cannot hit payment/bplo routes and has no revoke/edit route. (5 tests)
- `tests/Feature/Treasurer/TreasurerPaymentTest.php` — treasurer confirms a manual payment (PAID/VERIFIED, app PAYMENT_VERIFIED), treasurer rejects (back to APPROVED_FOR_PAYMENT + notification), dashboard lists awaiting payment, show links to private receipt. (4 tests)

Updated existing tests to use the treasurer route/actor instead of BPLO (intent preserved):
- `tests/Feature/Payment/ManualPaymentTest.php` — `test_treasurer_confirms_after_checking_the_receipt`, `test_treasurer_rejects_and_applicant_can_pay_again`, `test_reject_requires_a_reason`.
- `tests/Feature/Payment/PaymentFlowTest.php` — `test_treasurer_confirm_payment_marks_payment_verified`.
- `tests/Feature/Bplo/ReviewFlowTest.php` — `test_pending_payment_shows_reference_but_no_confirm_button_for_bplo` (now asserts the Treasurer message + no Confirm/Reject button).

## Verification evidence

Commands run from `c:\Users\HP\BUSINESS PERMIT_SYSTEM\app` (Windows PowerShell):

- `php artisan route:clear; php artisan config:clear`
- `php artisan test` → **154 passed (519 assertions)**, exit 0.
  - 138 pre-existing + 16 new (7 RoleAccess + 5 SeparationOfDuties + 4 TreasurerPayment). The 5 existing payment/review tests were re-pointed to the treasurer, not added.
- `vendor\bin\pint` → exit 0; it formatted `tests/Feature/Treasurer/TreasurerPaymentTest.php` (and two pre-existing untracked files, `live_check.php` / `render_edit.php`, which are NOT part of this task and were left untracked).
- Re-ran `php artisan test --filter="Roles|Treasurer|ManualPayment|PaymentFlow|ReviewFlow"` after Pint → **38 passed (168 assertions)**, exit 0.

## Route changes summary
- Removed: `bplo.payments.confirm`, `bplo.payments.reject`.
- Kept: `bplo.payments.proof`.
- Added: `treasurer.dashboard`, `treasurer.payments.index`, `treasurer.payments.show`, `treasurer.payments.confirm`, `treasurer.payments.reject`, `treasurer.payments.proof`, `inspector.dashboard`, `inspector.permits.index`, `inspector.permits.show`.

## Constraints honored
- `role` never mass-assignable (set in code everywhere).
- No new CSS framework / CDN; reused existing `public/css/app.css` theme classes.
- Public verify pages, QR (SVG) generation, manual GCash applicant flow, timezone, and permit PDF untouched — only re-routed who verifies payments + added two dashboards.
- "Bansud, Oriental Mindoro" preserved; no "Bongabong" introduced.
