# Moving routeTrack to another laptop

## What carries project memory

Keep `AGENTS.md`, `PROJECT_MEMORY.md`, this file and the existing documentation
with the project. Codex reads repository instructions through `AGENTS.md`; it tells
the next session to read the detailed project memory. Official reference:
https://learn.chatgpt.com/docs/agent-configuration/agents-md

Use the same account, but treat these files as the portable source of project
context. Account sign-in is not a substitute for transferring source, configuration,
database access and assets. These notes do not claim to reproduce all previous chats.

## Before leaving the old laptop

- Copy the complete project folder including these new files and hidden `.git`,
  or commit and push the documentation and any other wanted changes, then clone
  on the new laptop. This handoff task does not itself commit or push anything.
- Transfer `.env` privately, including the existing `APP_KEY`, if continuing the
  same environment. Do not commit it. Transfer the local `.env.example` separately
  if wanted; it currently is not tracked.
- Back up local SFA MySQL/MariaDB and PostgreSQL GPS databases with their database
  tools, or preserve the connection/VPN/firewall access needed for remote databases.
  Database files/data are not recreated by copying this source folder.
- Preserve needed uploads and images under `upload/`, `storage/app/` and legacy
  folders in `public/` such as `customerimage`, `visualimages`, `visualcaptureimages`,
  `posimages` and `surveyimages`, where present. Check for files outside Git.
- Check `DEVICE_BACKUP_UPLOAD_PATH`; its default points to a sibling
  `device-backups` directory outside the repository. Copy it separately if used.
- Preserve access to the OSRM service. Its default URL is `http://localhost:5001`;
  a service running on the old laptop will need an equivalent on the new machine.
  No OSRM provisioning workflow has been verified in this repository.
- Record any editor/PHP configuration, certificates and external services you
  depend on. Review needed personal Codex settings/skills/plugins on the new
  machine and reauthenticate integrations; do not put account tokens in this repo.

`vendor/`, `node_modules/` and `public/build/` are ignored generated dependencies
or output and can be recreated. Local secrets and business data cannot.

## New laptop setup

1. Install Git, Composer 2, PHP compatible with the lockfile (old machine uses
   8.4.15), and Node compatible with Vite (`^20.19.0 || >=22.12.0`). Enable required
   PHP extensions, including `pdo_mysql`, `pdo_pgsql`, and `pdo_sqlite` for tests,
   plus normal Laravel extensions listed in README. Match local database servers
   if you are restoring local copies.
2. Open the transferred/cloned project root. Run `composer install` and `npm ci`.
   Do not use `npm run update` for installation.
3. Restore private `.env`, or create one from the minimal template below. Set
   addresses and credentials for the new environment. For an existing environment
   preserve its APP_KEY; only for a new independent environment with no existing
   key run `php artisan key:generate`.
4. Restore/access both databases. Check their legacy schema and existing migration
   history before deciding whether migrations are needed. Do not run fresh/reset
   migrations or the old README's seeding recipe to restore business data.
5. Run `php artisan config:clear`, `php artisan route:clear`, and
   `php artisan view:clear` to remove copied machine-specific metadata.
6. Run `npm run build`, then `php artisan serve`. Open `http://127.0.0.1:8000`.
   For active frontend development, run `npm run dev` in a second terminal.
   With Apache/WAMP, point the site's document root at `public/`.
7. Sign in with an existing SFA user. Verify dashboard filters, maps, a known route
   and date, customer visits, transaction drilldowns and Customer Location.
   Check OSRM connectivity if road geometry is unavailable.

### Minimal private `.env` template

These are placeholders, not recovered credentials. Replace all angle-bracket
values. Use the old private configuration when available. File sessions/cache
below simplify local startup; they are not a claim about the old environment.

```dotenv
APP_NAME=TRAC
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sfa_mysql
SFA_DB_HOST=<business-database-host>
SFA_DB_PORT=3306
SFA_DB_DATABASE=<business-database-name>
SFA_DB_USERNAME=<business-database-user>
SFA_DB_PASSWORD=<business-database-password>
SFA_DB_TABLE_PREFIX=

TRACKING_DB_HOST=<gps-database-host>
TRACKING_DB_PORT=5432
TRACKING_DB_DATABASE=<gps-database-name>
TRACKING_DB_USERNAME=<gps-database-user>
TRACKING_DB_PASSWORD=<gps-database-password>
TRACKING_DB_SCHEMA=public
TRACKING_DB_SSLMODE=prefer

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
FILESYSTEM_DISK=local
OSRM_URL=http://localhost:5001

TRACKING_STATIONARY_MINUTES=5
TRACKING_STATIONARY_RADIUS_M=30
TRACKING_STATIONARY_MAX_GAP_SECONDS=120
TRACKING_MAX_ACCURACY_M=50
```

Additional mail, maps or storage settings should be transferred according to the
features actually in use. Never paste real passwords into project memory or chats.

## Checks and troubleshooting

```powershell
php vendor/bin/pest tests/Unit
node --test tests/dashboard-filters.test.mjs tests/dashboard-analytics.test.mjs
npm run build
php artisan route:list
```

- Driver errors: check PHP CLI's loaded extensions and the PHP used by the web server.
- Missing legacy tables: verify the selected database/restore; migrations alone are insufficient.
- Login works but routes are missing: inspect user access data and route/company
  associations; do not remove authorization filtering as a workaround.
- Map has no points: confirm journey dates, route access and GPS connection/data.
- OSRM fails: verify service address/network. Existing fallback paths may still render.
- Frontend asset errors: rebuild assets; stop an obsolete Vite process and remove
  a stale `public/hot` reference only when no development server is intended.

Validation on the old laptop is recorded below; it does not prove the new laptop's
database/network setup or browser behavior.

2026-09-16 results: 81 PHP unit tests passed (558 assertions), all 10 JavaScript
tests passed, and `php artisan route:list --except-vendor` completed with 29 routes.
PHP emitted an Xdebug log-path warning for `c:/wamp64/logs/xdebug.log`; it did not
fail the checks. No frontend build or live browser/database integration check was
performed for this documentation-only change.

## First message to Codex on the new laptop

> This is my existing TRAC / routeTrack project, moved from another laptop.
> Read AGENTS.md, PROJECT_MEMORY.md and LAPTOP_HANDOFF.md first. Inspect the current
> Git state and verify the local setup. Preserve the existing SFA/GPS database
> behavior and access restrictions. Tell me what is ready and what setup is
> missing; then we will continue with my next customization.
