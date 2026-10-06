# Bansud Business Permit System — Setup

Laravel application for processing municipal business permit applications for the
Municipality of Bansud, Oriental Mindoro.

## Prerequisites

- PHP 8.3+
- Composer
- SQLite (the default `pdo_sqlite` PHP extension; no separate database server needed)

## Setup

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate --seed
```

The default database is SQLite. `migrate --seed` creates the schema and loads the
seed data (users, requirement types, fee types, and fee rates).

## Default credentials

Seeded by `UserSeeder`:

| Role      | Email                   | Password       |
| --------- | ----------------------- | -------------- |
| Admin     | admin@bansud.gov.ph     | Password123!   |
| BPLO      | bplo@bansud.gov.ph      | Password123!   |
| Applicant | applicant@example.com   | Password123!   |

## Run

```bash
php artisan serve
```

Then open http://localhost:8000

## Test

```bash
php artisan test
```
