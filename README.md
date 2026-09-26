# ORWeMS — Orphans Registration and Welfare Management System

Humanitarian information-management and welfare decision-support platform for
**WICHOV** (Welfare Initiative for Children, Orphans and Vulnerable).

Pilot locations: Niger State, Nasarawa State and the Federal Capital Territory (FCT).
Locations are **data, not code** — no location is hard-coded in the application.

- **Stack:** Laravel 12 (Blade views) + MySQL/MariaDB + a lightweight CSS layer
- **Deployment target:** cPanel / shared hosting (WhoGoHost)
- **Status:** Phase 0 — project foundation only. No business modules implemented yet.

## Requirements

| Component | Version used |
|-----------|--------------|
| PHP       | 8.2 – 8.5 (verified here: CLI 8.2.30, Apache 8.3.16) |
| Composer  | 2.9.3 |
| MySQL     | 8.4.3 (Laragon, port 3308 on this machine) |
| Node.js   | 26.5.1 (only needed to build front-end assets) |
| npm       | 11.7.0 |

Required PHP extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`,
`mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip`.

## Local setup

```bash
composer install
copy .env.example .env       # macOS/Linux: cp .env.example .env
php artisan key:generate
```

Then edit `.env` and point it at the development database. On this machine
Laragon's MySQL listens on **3308**, not the default 3306:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3308
DB_DATABASE=orwems
DB_USERNAME=root
DB_PASSWORD=
```

Create the database (once), then migrate the framework tables:

```bash
mysql -h 127.0.0.1 -P 3308 -u root -e "CREATE DATABASE IF NOT EXISTS orwems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
```

Run the application:

```bash
php artisan serve            # http://localhost:8000
npm install && npm run dev   # only when working on front-end assets
```

## Running the tests

```bash
php artisan test
```

Tests run against an in-memory SQLite database (`phpunit.xml`), so they never
touch the development MySQL database.

## Database

- Development database: `orwems` (utf8mb4 / utf8mb4_unicode_ci)
- Only Laravel's framework tables exist so far: `migrations`, `users`,
  `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
  `job_batches`, `failed_jobs`.
- **No business-domain schema exists yet.** Beneficiary, welfare, health,
  education, intervention, reporting and IWAE tables belong to later phases.

## Deployment notes (cPanel / shared hosting)

- The web root must point at `public/`. On cPanel either set the document root to
  `public/`, or keep the application above the web root and forward requests into
  `public/index.php`.
- Run `npm run build` locally and upload `public/build`; Node.js is not required on
  the shared host.
- Never commit `.env`. Set production values through the hosting panel.

## Project documentation

See [`docs/PHASE-0-BASELINE.md`](docs/PHASE-0-BASELINE.md) for the Phase 0
environment audit, the Laravel version decision and the open items for the next phase.
