# ORWeMS — Phase 0 Baseline Report

Phase 0 scope: inspect the workspace and development environment, then establish a
verified Laravel foundation. **No business modules were implemented.**

## 1. Environment as verified on this machine

| Tool | Version | Notes |
|------|---------|-------|
| PHP (CLI) | **8.2.30** (ZTS, VS16 x64) | `C:\laragon\bin\php\php-8.2.30-Win32-vs16-x64\php.exe` — used by `php`, `composer` and `php artisan` |
| PHP (Apache) | **8.3.16** | Laragon `mod_php.conf` loads `php8apache2_4.dll` from `php-8.3.16-Win32-vs16-x64` |
| Composer | 2.9.3 | running on PHP 8.2.30 |
| Node | 26.5.1 | |
| npm | 11.7.0 | |
| Git | 2.52.0.windows.1 | global identity configured |
| MySQL | 8.4.3 | Laragon, **port 3308**, not registered as a Windows service |
| Laragon Apache | httpd 2.4.62 | port **8080**, DocumentRoot `C:\laragon\www`, `AutoVirtualHosts=0` |

### PHP CLI vs Apache PHP — important

The CLI PHP and the Apache PHP are **not the same version** on this machine. The
machine `PATH` points at PHP 8.2.30 while the actively included Apache
`mod_php.conf` loads PHP 8.3.16. The machine `PATH` also contains a stale entry for
`C:\Program Files\php-8.5.2` which does not exist.

This mismatch is the reason Laravel 12 was selected (see section 3).

## 2. Workspace findings

- Project root: `C:\laragon\www\ORWeMS` — it was **completely empty** (0 files).
- No Laravel project, `composer.json`, `package.json`, `artisan`, Blade files,
  routes, migrations, models, controllers or `public` assets existed.
- No `ORWeMS`/`WICHOV` Git repository existed anywhere under `C:\laragon\www`.
- **The previously reported HTML/CSS/JavaScript landing-page prototype could not be
  found.** Name- and content-based searches (`*orwems*`, `*wichov*`, `*orphan*`, and
  the literal strings `WICHOV` / `Orphans Registration` inside `.html`, `.htm`,
  `.css` and `.js`) across `C:\laragon\www`, Desktop, Documents, Downloads and
  OneDrive returned no matches. Nothing was deleted — there was nothing to preserve
  in this workspace.
- Only planning material exists, and it lives outside the project:
  - `C:\Users\GREAT JOE COMPUTERS\Downloads\ORWeMS Development Plan.docx`
  - `C:\Users\GREAT JOE COMPUTERS\OneDrive\Documents\Concept Proposal for ORWeMS (1).pdf`

## 3. Architecture decision

**A new Laravel project was created in place, at the workspace root.** There was
nothing to retain, and creating the application directly in `C:\laragon\www\ORWeMS`
avoids a redundant wrapper directory.

**Laravel 12 (framework v12.69.2, skeleton `laravel/laravel` v12.0.0) was chosen.**

Reasoning:

1. Laravel 12 requires PHP `^8.2`; Laravel 13 requires PHP `^8.3`. The **verified
   CLI PHP here is 8.2.30**, so a Laravel 13 application would fail immediately:
   Composer would refuse to install it, and `vendor/composer/platform_check.php`
   would abort every `php artisan` invocation.
2. Laravel 12 runs correctly on **all** PHP runtimes present (CLI 8.2.30, Apache
   8.3.16, and 8.4/8.5), so the application no longer depends on which PHP the
   developer's terminal happens to resolve. The CLI/Apache split is neutralised
   instead of being a latent failure.
3. The production PHP version for WhoGoHost/cPanel has **not been verified**.
   Laravel 12's `^8.2` floor accepts PHP 8.2, 8.3, 8.4 and 8.5, which maximises
   compatibility across cPanel plans.
4. It avoids mutating the global `PATH` and therefore cannot disturb the other ~35
   projects under `C:\laragon\www`.

**Known trade-off requiring an architect decision:** Laravel 12's bug-fix window
ended on 13 August 2026 and its security-fix window ends on 24 February 2027.
Laravel 13 is in full support until Q3 2027 (bug fixes) and March 2028 (security).
Recommended plan: complete the Laravel 12 → 13 upgrade before February 2027, once
(a) the hosting PHP version is confirmed to be 8.3 or newer and (b) the local CLI
PHP is aligned to 8.3.16 or newer using Laragon's PHP version selector.

## 4. Database

Before: Laravel's scaffold defaulted `DB_CONNECTION` to SQLite and ran the framework
migrations into `database/database.sqlite`.

After:

- Connection: `mysql`, host `127.0.0.1`, port `3308`, database `orwems`. The
  username and password are supplied through the Git-ignored `.env` and are
  deliberately not documented or committed here.
- The `orwems` database was **created new**, with `utf8mb4` /
  `utf8mb4_unicode_ci` to match Laravel's `config/database.php` MySQL defaults
  (the server default is `utf8mb4_0900_ai_ci`).
- `php artisan migrate` succeeded against MySQL.
- Only Laravel framework tables were created: `migrations`, `users`,
  `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
  `job_batches`, `failed_jobs`. **No business tables exist.**
- **No existing database was dropped or modified.** The 24 pre-existing schemas on
  this MySQL instance (other projects) are all still present — the schema count went
  from 24 to 25 with the addition of `orwems`.
- No `migrate:fresh`, `db:wipe` or database reset command was ever run.
- `database/database.sqlite` is an unused scaffold artifact. It was deliberately
  **kept** (nothing was deleted) and is now Git-ignored. It can be removed safely at
  any time.
- Credentials live only in `.env`, which is Git-ignored. `.env.example` contains
  placeholders and no secrets.

## 5. Git

- No repository existed. `git init` was run with `main` as the default branch.
- `.gitignore` was left exactly as Laravel ships it — it already excludes `.env`,
  `/vendor` and `node_modules`. The scaffold's `database/.gitignore` already
  excludes `*.sqlite*`, which covers the unused `database/database.sqlite` artifact.
- A Phase 0 baseline commit contains the framework, configuration and documentation
  only. `.env` and `vendor/` are excluded.

## 6. Security posture

- `.env` is Git-ignored, so `APP_KEY` and database credentials never enter the
  repository.
- No credentials are hard-coded in application source; database settings come from
  environment variables through `config/database.php`.
- No location (Niger, Nasarawa, FCT) is referenced anywhere in application code.

## 7. Verification performed

| Command | Result |
|---------|--------|
| `php artisan about` | passes |
| `php artisan route:list` | passes (default routes only) |
| `php artisan migrate:status` | passes — 3 framework migrations ran against MySQL |
| `php artisan migrate` | passes against MySQL `orwems` |
| `php artisan test` | passes |
| `composer validate` | passes |

## 8. Out of scope / deliberately not implemented

Beneficiary management, beneficiary tables, health, education, care/placement, needs
assessment, intervention management, reporting, analytics, IWAE, donor management,
sponsorship, mobile API, GIS, partner portal, authentication and RBAC. No mock
beneficiary data was created.

## 9. Open items for the next phase

1. Confirm the PHP version available on the WhoGoHost cPanel account — this blocks
   the Laravel 12 → 13 decision.
2. Align Laragon's CLI PHP with Apache (select PHP 8.3.16 in Laragon) to remove the
   CLI/Apache version split.
3. Decide the UI layer: keep the shipped Vite + Tailwind toolchain, or add Bootstrap.
   **No front-end packages have been installed yet, and `npm run build` has not been
   run** — `resources/views/welcome.blade.php` therefore renders unstyled.
4. Decide whether feature tests should run against MySQL (`orwems_test`) instead of
   in-memory SQLite, so MySQL-specific behaviour is genuinely exercised.
5. Model locations (states, LGAs, wards) as reference **data** — not constants —
   before any beneficiary work begins, so additional states can be added without code
   changes.
