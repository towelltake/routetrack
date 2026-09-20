# routeTrack: instructions for Codex

Read `PROJECT_MEMORY.md` at the start of a new session. For laptop setup, read
`LAPTOP_HANDOFF.md`. For detailed tracking behavior, consult
`ROUTE_TRACKING_README.md`, then verify against current code and tests; some older
documentation describes superseded behavior.

## Project

TRAC is a Laravel 12 / PHP application with Inertia, Vue 3, Vite, Bootstrap/OneUI,
Leaflet maps and Chart.js. Active screens are Dashboard, Route Tracking and
Customer Location. Business data comes from legacy SFA MySQL/MariaDB; GPS data
comes from the separate `tracking_pgsql` connection. This is not a fresh Laravel
database that migrations alone can recreate.

## Working rules

- Inspect the current branch, changes and relevant source before editing. Preserve user work.
- Implement the requested customization using existing components and conventions.
  Do not infer a feature backlog from the handoff; ask for the next customization
  when none has been specified.
- Preserve session-based route/company/geographic access restrictions on every
  data endpoint. UI filtering alone is not authorization.
- Preserve legacy names and linking keys, including `schelduledflag`, `routekey`,
  `customercode`, and `customeroperationscontrol.log_id`.
- Keep dashboard journey-range semantics distinct from Route Tracking's selected
  operation date. Check current timing/CFT/OTP tests before changing formulas.
- Do not run migrations, seeders, SQL imports or destructive database commands as
  a routine setup/verification step against an existing business database.
- Never put `.env` contents, credentials, customer records or database dumps in
  project memory. Use placeholder configuration in documentation.
- Use lockfiles for dependency installation. `npm run update` upgrades dependencies
  and is not a setup command.
- Run checks appropriate to changes; report what passed and what could not be
  verified. Useful existing checks: `php vendor/bin/pest tests/Unit`,
  `node --test tests/dashboard-filters.test.mjs tests/dashboard-analytics.test.mjs`,
  and `npm run build` for frontend changes.
- Keep `PROJECT_MEMORY.md` current after substantive work: record behavior,
  decisions, relevant files, validation results and unfinished work. Clearly
  distinguish implemented behavior, suspected issues and proposed changes.

These files provide portable project context, not a transcript of all past chats.
