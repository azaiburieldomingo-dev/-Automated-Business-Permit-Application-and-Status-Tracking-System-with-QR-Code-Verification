# Bansud Business Permit System — Setup

Automated Business Permit Application and Status Tracking System with QR Code
Verification for the Municipality of Bansud, Oriental Mindoro. Built on Laravel 13.

## Prerequisites

- PHP 8.3+ with `pdo_sqlite` (or `pdo_mysql`), `mbstring`, `fileinfo`, `gd`, `zip`
- Composer
- No Node/npm needed. Styles are a static `public/css/app.css` plus Tailwind from a CDN.

## First-time setup

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
```

The default database is SQLite (`database/database.sqlite`). Seeding loads the users,
requirement types, fee types, and fee rates.

## Run

```powershell
php artisan serve
```

Open http://localhost:8000

**To scan permit QR codes with a phone**, start the server with `serve-lan.bat`
(double-click it) or `php artisan serve --host=0.0.0.0 --port=8000`, and connect the phone
to the same Wi-Fi. See [QR codes](#qr-codes).

To start over with a clean database: `php artisan migrate:fresh --seed`

## Default accounts

| Role      | Email                     | Password     |
| --------- | ------------------------- | ------------ |
| Admin     | admin@bansud.gov.ph       | Password123! |
| BPLO      | bplo@bansud.gov.ph        | Password123! |
| Treasurer | treasurer@bansud.gov.ph   | Password123! |
| Inspector | inspector@bansud.gov.ph   | Password123! |
| Applicant | applicant@example.com     | Password123! |

Change these passwords before any real deployment.

## Roles & workflow

Each role owns one clean step, so no single person both verifies a payment and
releases the permit (separation of duties):

**Applicant → BPLO review → Treasurer verifies payment → BPLO releases permit → Inspector monitors; Admin manages all.**

- **Applicant** submits the application and uploads requirements, then pays the fees via GCash.
- **BPLO** reviews and approves/returns/rejects the application, and — after payment is
  verified — releases the Mayor's Permit.
- **Treasurer** checks each GCash receipt against the transaction history and confirms or
  rejects the payment. BPLO no longer touches payment confirmation.
- **Inspector** monitors released permits and verifies them in the field by scanning the QR code.
- **Admin** manages users, fee types, fee rates, requirement types, and can reach every area.

## Workflow

1. **Apply Online** (applicant): register or log in, fill in the application form,
   upload requirements (PDF/JPG/PNG, max 4 MB), review the summary, and submit.
   The system issues a tracking number such as `BANSUD-2026-000001`.
2. **Review** (BPLO): open the application from the BPLO dashboard, start the review, then:
   - **Approve**: status becomes *Approved for Payment* and the fees are computed.
   - **Return**: status becomes *For Correction*. A remark is required. The applicant
     re-uploads only the flagged requirements and resubmits.
   - **Reject**: status becomes *Rejected*. A remark is required.
3. **Track Status**: applicants see progress on their dashboard. Anyone with the tracking
   number plus the applicant's email or last name can use the public **Track Application** page.
4. **Pay Fees** (applicant): the Pay Fees page shows the receiving GCash number and
   account name and the exact amount. The applicant sends the money in the GCash app,
   then submits the **Ref No.** and a screenshot of the receipt. Status becomes
   *Payment Pending*. Submitting never releases a permit by itself.
5. **Verify Payment** (Treasurer): check the GCash transaction history for that Ref No. and
   amount, then click **Confirm Payment** (status becomes *Payment Verified*) or
   **Reject Payment** with a reason (the applicant is notified and pays again).
6. **Release Permit** (BPLO): generates the Mayor's Permit (`BP-2026-000001`) with a QR code.
   The applicant can view, download (PDF), and print it.
7. **Verify Permit** (anyone): scan the QR code, or enter the verification link on the
   **Verify Permit** page (`/verify`). The result shows **VALID**, **EXPIRED**,
   **REVOKED**, or **INVALID / NOT FOUND**. Only the permit number, business name,
   business address, status, and dates are shown, never contact details (RA 10173).

Fees, fee rates, requirement types, and user accounts are managed from the Admin dashboard.

## Payments: manual GCash (default)

`PAYMENT_MODE=manual`. The receiving accounts are set in `.env`:

```
PAYMENT_GCASH_NUMBER=09670829993
PAYMENT_GCASH_NAME="Aiza Domingo"
PAYMENT_MAYA_NUMBER=
PAYMENT_MAYA_NAME=
PAYMENT_QRPH_NUMBER=
PAYMENT_QRPH_NAME=
```

A method with no number appears as "Not yet available". Run `php artisan config:clear`
after editing. Use the municipality's official account for real collections.

Reference numbers are stored without spaces, and a reference number can't be reused
while another payment with it is pending or paid. Receipts are stored privately
(`storage/app/private/payments`) and only the applicant and treasurer/BPLO/admin can open them.

## Payments: sandbox mode

`PAYMENT_MODE=sandbox` uses the clearly labeled `SandboxGateway`. It never marks a
payment as paid on its own. Verification happens only through:

- the Treasurer **Confirm Payment** button, or
- the HMAC-signed webhook `POST /sandbox/payments/{payment}/webhook`, which uses
  the `X-Sandbox-Signature` header (HMAC-SHA256 of the raw body with `PAYMENT_WEBHOOK_SECRET`).

To go live, implement `App\Contracts\PaymentGateway` for the real provider (GCash, Maya,
or a QR Ph aggregator), bind it in `App\Providers\AppServiceProvider`, and put the
credentials in `PAYMENT_GATEWAY_KEY`, `PAYMENT_GATEWAY_SECRET`, and `PAYMENT_WEBHOOK_SECRET`.

## SMS notifications

In-app (dashboard) notifications always work. SMS uses `SMS_DRIVER=log`, which writes
messages to `storage/logs/laravel.log` instead of sending them. To connect a provider,
implement `App\Contracts\SmsChannel`, bind it in `AppServiceProvider`, and set the
`SMS_*` variables in `.env`.

## QR codes

QR codes are generated as SVG, so the `imagick` extension isn't needed. Each QR encodes
`PERMIT_PUBLIC_URL/verify/{64-character random token}`. On the permit page, the QR code
and the link are clickable, with **Open Verification Page** and **Copy Link** buttons.

`PERMIT_PUBLIC_URL` must be an address the inspector's phone can open:

- **Never `localhost`.** On a phone, `localhost` means the phone itself. The permit page
  shows a warning if the QR points to localhost.
- **Local testing:** use this PC's Wi-Fi IP, e.g. `PERMIT_PUBLIC_URL=http://10.251.134.242:8000`,
  start the server with `serve-lan.bat`, and connect the phone to the same Wi-Fi.
  The Wi-Fi IP can change when you reconnect; `serve-lan.bat` prints the current one.
- **Deployment:** the real domain, e.g. `https://permits.bansud.gov.ph`.

Downloaded permit PDFs are re-generated each time, so they always carry the current address.
The token never changes, so permits already printed keep working as long as the address
in their QR stays reachable.

## Time zone

The system runs on Philippine time (`APP_TIMEZONE=Asia/Manila`, UTC+8). Every recorded
date and time, the tracking and permit number year, and permit issue/expiry dates use
local time. Times are shown in 12-hour format, for example `Oct 07, 2026 3:15 AM`.

## Switching to MySQL

Create a database (for example `bansud_permit`, utf8mb4), then set in `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bansud_permit
DB_USERNAME=root
DB_PASSWORD=your-password
```

Then run `php artisan config:clear` and `php artisan migrate --seed`.

## Tests

```powershell
php artisan test
```

## Deployment checklist

- `APP_ENV=production`, `APP_DEBUG=false`, and a real `APP_URL` served over HTTPS
- New `APP_KEY`, a long random `QR_HASH_SALT`, and a real `PAYMENT_WEBHOOK_SECRET`
- Change all seeded passwords
- `php artisan config:cache` and `php artisan route:cache`
- Keep `storage/app/private` out of the public web root (it holds uploaded documents and permit PDFs)
