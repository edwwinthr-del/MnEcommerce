# MnEcommerce — Mora Store

A full-stack e-commerce application for a Montenegro store, with a Next.js storefront, Laravel REST API, Filament administration panel and PostgreSQL database.

The provisional store brand is **Mora**. The initial catalog contains demonstration products. Checkout supports Montenegro delivery, EUR pricing, cash on delivery and bank transfer.

## Project status

The core MVP is implemented and tested locally. Production deployment, merchant configuration and independent release reviews remain pending.

Local verification on October 7, 2026 passed:

| Check | Result |
| --- | --- |
| PostgreSQL backend unit and feature tests | 44 tests, 278 assertions |
| PostgreSQL checkout concurrency tests | 3 tests, 23 assertions |
| Frontend unit tests | 6 tests |
| Desktop and mobile Chromium browser tests | 12 tests |
| ESLint and TypeScript | Passed |
| Next.js production build | Passed |
| Native backend HTTP smoke checks | Passed |

These results cover the local application. They do not establish production readiness or successful container deployment.

## Features

### Storefront

- Product catalog, categories, search, sorting and featured products.
- Product detail pages with variants, images and stock availability.
- Scheduled promotional banners.
- Persistent guest cart with quantity controls.
- Checkout with server-calculated prices, shipping and coupon discounts.
- Cash-on-delivery and bank-transfer orders with receipts.
- Idempotent order retries to prevent duplicate orders after an interrupted response.
- Customer registration, login, logout and password-reset flows.
- Responsive layouts, product metadata, sitemap and policy pages.

### Administration

- Product, category, variant, image and banner management.
- Customer and coupon management.
- Order lifecycle controls and verified-payment actions.
- Suppliers and manually managed supplier orders.
- Audit records and low-margin alerts.
- Revenue and estimated gross-profit reports using historical order costs.

### Application controls

- Session authentication with Laravel Sanctum, CSRF protection and request throttling.
- Role and ownership checks for administrative and customer actions.
- Backend-owned prices and inventory; monetary amounts use integer EUR cents.
- Transactional checkout and PostgreSQL locking for stock and coupon limits.
- Restricted raster image uploads.
- Database readiness endpoint at `/api/v1/health`.

Card payments, automated supplier API integrations and customer order-history screens are not implemented. Business settings currently use environment configuration.

## Technology stack

| Layer | Technology |
| --- | --- |
| Storefront | Next.js 16, React, TypeScript, Tailwind CSS |
| Forms and validation | React Hook Form, Zod |
| API | Laravel 13, PHP 8.4+, Laravel Sanctum |
| Administration | Filament 5 |
| Database | PostgreSQL 18 |
| Testing | PHPUnit, Playwright, Node.js test runner |
| Quality checks | Laravel Pint, Larastan, ESLint, TypeScript |
| Deployment | Docker Compose, Nginx, PHP-FPM, standalone Next.js |
| CI | GitHub Actions |

## Repository structure

```text
backend/                  Laravel API, domain services, admin panel and tests
frontend/                 Next.js storefront and frontend tests
docker/                   Images, PHP settings and Nginx configuration
scripts/                  Development and verification utilities
.github/workflows/        CI quality gates
compose.yaml              Local container stack
compose.production.yaml   HTTPS production override
```

The backend is a modular monolith. Domain services handle pricing, checkout, inventory, order transitions, supplier workflows and analytics. The storefront submits product identifiers and quantities; the API calculates and validates order totals.

## Local setup

### Requirements

- PHP 8.4 or newer with `intl`, `pdo_pgsql`, `mbstring`, `fileinfo`, `openssl`, `bcmath`, `gd`, `zip` and DOM/XML extensions. The default backend test configuration also requires `pdo_sqlite`.
- Composer 2.
- Node.js 24 and npm.
- A running PostgreSQL 18 instance.

Docker is optional for native development. Run the following setup commands from the repository root unless a different directory is shown.

### 1. Configure the backend

```sh
cd backend
composer install
```

Copy `backend/.env.example` to `backend/.env` if the file does not already exist. Set the connection to a database and role you have created in PostgreSQL:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mora
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:8000,127.0.0.1:3000,127.0.0.1:8000
SESSION_SECURE_COOKIE=false
```

Use your actual server port and credentials. Native Laravel reads **`backend/.env`**; the root `.env` is used by Docker Compose. The databases and roles must exist before migrations run.

Generate an application key for a new installation, then create the schema and storage link:

```sh
php artisan key:generate
php artisan migrate
php artisan storage:link
```

For an optional demonstration catalog:

```sh
php artisan db:seed
```

Seeding adds eight demo products, categories, sample variants, a banner and a coupon. It does not create a default administrator. Use `migrate:fresh` only for a disposable database: it drops all existing tables.

### 2. Configure the storefront

```sh
cd ../frontend
npm ci
```

Optionally copy `frontend/.env.example` to `frontend/.env.local`. Its default API and storefront URLs are:

```dotenv
API_INTERNAL_URL=http://127.0.0.1:8000/api/v1
NEXT_PUBLIC_SITE_URL=http://localhost:3000
NEXT_PUBLIC_STORE_LIVE=false
```

### 3. Start the application

With the required PHP extensions enabled, start the backend in one terminal:

```sh
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

Start the storefront in another terminal:

```sh
cd frontend
npm run dev
```

Keep PostgreSQL and both servers running. Open:

- Storefront: [http://localhost:3000](http://localhost:3000)
- Administration: [http://localhost:8000/admin](http://localhost:8000/admin)
- API readiness: [http://localhost:8000/api/v1/health](http://localhost:8000/api/v1/health)

### Windows / PowerShell

If XAMPP has `intl` installed but disabled, run Artisan commands with explicit PHP settings:

```powershell
cd backend
php -d extension=intl -d xdebug.mode=off artisan migrate
```

For manual backend startup with these settings, run from `backend/public`:

```powershell
cd backend/public
php -d extension=intl -d xdebug.mode=off -S 127.0.0.1:8000 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

In a separate terminal, run `npm.cmd run dev` from `frontend` if PowerShell blocks the `npm.ps1` wrapper. Alternatively, after PostgreSQL is running, launch both application servers from the repository root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/dev.ps1
```

This script handles the local PHP extension flags and starts the API and storefront in the background. It prints their process IDs and log location. It does not start PostgreSQL. Press Ctrl+C to stop manually launched servers; for the script, stop the reported processes and their child processes.

An existing development workspace may have an isolated PostgreSQL cluster in `.runtime/postgres` on port `55432`. This directory is ignored by Git and is not supplied with a fresh clone. If you use that existing cluster, start it from the repository root:

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe' -D "$PWD\.runtime\postgres" -l "$PWD\.runtime\postgres.log" -o '-h 127.0.0.1 -p 55432' -w start
```

Use `DB_PORT=55432` only for that cluster. For an ordinary PostgreSQL installation, use its configured port, commonly `5432`.

### Create an administrator

From `backend`:

```sh
php artisan store:create-admin
```

The command prompts for name, email and a private password of at least 14 characters with uppercase/lowercase letters, a number and a symbol. On XAMPP, use the same PHP extension flags shown above.

Password-reset emails use the log mailer by default in native development. Configure SMTP for actual email delivery. Database-backed queued jobs require `php artisan queue:work`; scheduled tasks can be run locally with `php artisan schedule:work`.

## Testing and quality checks

### Backend

Run from `backend` with the required PHP extensions enabled:

```sh
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
composer audit --locked
```

The default PHPUnit configuration uses in-memory SQLite. For PostgreSQL tests, use a separate disposable database such as `mora_test` and override the connection in a dedicated test terminal. Never use your store database for these tests.

Example in PowerShell, after creating `mora_test` and configuring access:

```powershell
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '5432'
$env:DB_DATABASE = 'mora_test'
$env:DB_USERNAME = 'your_test_database_user'
$env:DB_URL = ''
# Set DB_PASSWORD in this test terminal if it differs from backend/.env.
php -d extension=intl -d xdebug.mode=off artisan test
php -d extension=intl -d xdebug.mode=off artisan test tests/Integration
```

Use the actual test server port. Concurrency tests require PostgreSQL and a database name ending in `_test`; they recreate that database's tables. Close the test terminal afterward to discard its connection overrides.

### Frontend

Run from `frontend`:

```sh
npm run lint
npm run typecheck
npm test
npm run build
npm audit --audit-level=high
```

### Browser tests on Windows

Create a dedicated database named `mora_e2e_test`, accessible to the test role. Install Chromium from `frontend`:

```sh
npx playwright install chromium
```

Then run from the repository root, adapting the database port and user:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-e2e.ps1 -Production -DatabasePort 5432 -DatabaseUser your_test_database_user -ApiPort 8001 -StorePort 3001
```

If authentication requires a password, set `DB_PASSWORD` in the calling test terminal. The runner resets only its guarded disposable `_test` database, builds the standalone storefront, creates temporary admin credentials and stops its own servers after testing. Its default database port is `55432`, so pass `-DatabasePort 5432` when using a standard local installation.

Coverage includes catalog navigation, guest checkout, coupons, interrupted-order retries, customer sessions, CSRF rejection and admin pages. Chromium mobile emulation does not replace physical-device, Safari or Firefox testing.

## Docker Compose

The stack includes PostgreSQL, PHP-FPM, Next.js, Nginx, a queue worker and a scheduler.

Copy the root `.env.example` to `.env`. Set a strong `DB_PASSWORD` and a unique `APP_KEY`. With Node.js installed, generate a key and copy the output into the root `.env`:

```sh
node -e "console.log('base64:'+require('node:crypto').randomBytes(32).toString('base64'))"
```

Run from the repository root:

```sh
docker compose build
docker compose up -d postgres
docker compose run --rm backend php artisan migrate --force
# Optional demo data:
docker compose run --rm backend php artisan db:seed --force
docker compose up -d
docker compose exec backend php artisan store:create-admin
```

Open [http://localhost:8080](http://localhost:8080) and [http://localhost:8080/admin](http://localhost:8080/admin). The database and uploads use persistent named volumes. `docker compose down` stops the stack; adding `--volumes` removes its stored data.

To exercise a disposable container stack on a Docker host with Bash, curl and Node.js:

```sh
bash scripts/smoke-containers.sh
```

GitHub Actions includes backend, frontend, browser and container smoke jobs. Container validation has not been executed in the local verification reported above.

## Production configuration

Before accepting real orders:

- Configure the merchant identity, support contact, bank-transfer instructions, shipping, taxes, actual inventory, product media and policy text.
- Configure a deployment domain, SMTP, unique application/database secrets and an administrator.
- Validate HTTPS, session cookies, headers, queues and uploads at the real deployment origin.
- Establish monitoring, database/upload backups and a tested restoration procedure.
- Complete container validation and the pending architecture, security and frontend release reviews.

`compose.production.yaml` provides the HTTPS override. Configure `TLS_CERT_DIRECTORY` with `fullchain.pem` and `privkey.pem`, and align the public HTTPS URLs and Sanctum hostname before building with both Compose files.

Frontend merchant values are `NEXT_PUBLIC_MERCHANT_NAME`, `NEXT_PUBLIC_MERCHANT_ADDRESS` and `NEXT_PUBLIC_SUPPORT_EMAIL`. Rebuild the storefront after changing public build settings. `NEXT_PUBLIC_STORE_LIVE=true` enables indexing; it does not control checkout access.

Gross-profit reports estimate revenue minus recorded historical costs; they are not accounting net-profit statements.

## Troubleshooting

| Problem | Check |
| --- | --- |
| PostgreSQL connection refused | Start the intended PostgreSQL instance and verify `DB_HOST` and `DB_PORT` in `backend/.env`. |
| Laravel still uses the old database | Save `backend/.env`, run `php artisan config:clear` and check terminal-level `DB_*` overrides. |
| PHP `intl` error | Enable `intl` in the active PHP configuration or use the Windows PHP flags above. |
| Xdebug connection timeout | Add `-d xdebug.mode=off` to local PHP commands. |
| PowerShell blocks npm | Use `npm.cmd` instead of `npm`. |
| Frontend cannot fetch products | Check backend readiness and `API_INTERNAL_URL`; restart Next.js after environment changes. |
| Port already in use | Stop the existing service or configure matching backend, frontend and authentication URLs for new ports. |

## Files kept out of Git

Environment secrets, dependencies, runtime databases, build artifacts and test reports are ignored. Markdown files are also ignored except for this root `README.md`, which is intended for the repository homepage.
