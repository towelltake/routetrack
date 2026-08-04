Application TRAC Version 1.0

Author: Jyothish Thyagarajan

## Stack

- PHP 8.2+
- Laravel 12
- Inertia.js + Vue 3
- Vite for frontend assets
- MySQL or MariaDB for fresh installs

## Production assumptions

This project is not a clean greenfield Laravel schema. It still depends on a legacy TRAC/NMWC schema for many business tables.

The application does not read or import any SQL dump file during migrations.

The required legacy tables must already exist in the target database before you run production migrations.

## Server requirements

- PHP 8.2 or newer
- Composer 2
- Node.js 20+ and npm
- MySQL 8+ or MariaDB 10.6+
- Web server pointed to the `public/` directory

Recommended PHP extensions:

- `bcmath`
- `ctype`
- `fileinfo`
- `json`
- `mbstring`
- `openssl`
- `pdo`
- `pdo_mysql`
- `tokenizer`
- `xml`

## First-time production setup

### 1. Deploy code

Clone or copy the project to the server.

### 2. Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Install and build frontend assets

```bash
npm install
npm run build
```

Use `npm run build` in production, not `npm run dev`.

### 4. Create the database

Example MySQL setup:

```sql
CREATE DATABASE trac CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'trac_user'@'localhost' IDENTIFIED BY 'StrongPasswordHere';
GRANT ALL PRIVILEGES ON trac.* TO 'trac_user'@'localhost';
FLUSH PRIVILEGES;
```

### 5. Configure environment

Create `.env` from `.env.example` and update at least these values:

```env
APP_NAME=TRAC
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trac
DB_USERNAME=trac_user
DB_PASSWORD=StrongPasswordHere
DB_TABLE_PREFIX=

SESSION_DRIVER=database
CACHE_STORE=database

# Recommended until queue tables are added to the project
QUEUE_CONNECTION=sync

BOOTSTRAP_ADMIN_USERNAME=admin
BOOTSTRAP_ADMIN_PASSWORD=change-this-password
```

Important notes:

- `SESSION_DRIVER=database` is supported by existing migrations.
- `CACHE_STORE=database` is supported by existing migrations.
- The required legacy TRAC tables must already exist before `php artisan migrate --force` is run.
- The repo does not currently include migrations for `jobs`, `failed_jobs`, or `job_batches`, so `QUEUE_CONNECTION=database` is not production-safe unless you add those migrations yourself.

### 6. Generate the app key

```bash
php artisan key:generate
```

### 7. Run database migrations

```bash
php artisan migrate --force
```

This will:

- create Laravel support tables such as `sessions` and `cache`
- validate that required legacy schema tables already exist
- apply all TRAC feature migrations

### 8. Seed required bootstrap data

```bash
php artisan db:seed --force
```

This seeds required baseline data, including:

- currency
- company
- national sales manager
- country
- module catalog
- administrator role, permissions, and bootstrap admin user

### 9. Cache framework metadata

```bash
php artisan optimize
```

If you prefer the explicit commands:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 10. Set writable directories

The web server user must be able to write to:

- `storage/`
- `bootstrap/cache/`
- `public/visualimages/`

The application also reads legacy image folders directly from `public/` when present:

- `public/visualcaptureimages/`
- `public/posimages/`
- `public/surveyimages/`

If your deployment depends on those legacy image flows, make sure those folders exist and contain the expected files.

### 11. Point the web server to `public/`

Do not serve the repository root directly. The document root must be:

- `.../trac-v2/public`

## Subsequent deployments

For normal releases on an existing production database:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan optimize
```

Do not run `migrate:fresh` in production.

Do not run `db:seed` again in production unless you intentionally want to re-apply bootstrap seed logic.

## Scheduler and queue notes

- No application scheduler is currently defined in `routes/console.php`, so no cron entry is required for Laravel scheduling at this time.
- No active queue worker setup is required if `QUEUE_CONNECTION=sync`.
- If you later switch to database queues, add queue table migrations first and then run a worker such as:

```bash
php artisan queue:work --tries=1
```

## Legacy schema requirement

The project still requires the legacy TRAC/NMWC tables used by the application models and business flows.

Those tables must be provisioned in the database outside this repository's migration flow.

If required legacy tables are missing, the validation migrations will fail fast with a clear error listing the missing tables.



GET http://127.0.0.1:8000/api/index/companyidbydevice/deviceid/TEST123
GET http://127.0.0.1:8000/api/index/salesmanlogin/username/demo/password/123/deviceid/TEST123
GET http://127.0.0.1:8000/api/customer/customermaster/routecode/101/customercode/1001
GET http://127.0.0.1:8000/api/transaction/trandata/routekey/5001
GET http://127.0.0.1:8000/api/ws/checkload/userid/1/routeid/101
GET http://127.0.0.1:8000/api/ws/getwarehousestock/userid/1/routeid/101
