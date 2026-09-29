-- 0013_plan_salesmen.sql — plan-scoped salesman snapshots.
--
-- Until now `visits.salesman_id` referenced the live roster with ON DELETE
-- CASCADE, so deleting a salesman silently wiped his visits out of EVERY
-- plan (and the analytics built on them). Plans are point-in-time documents:
-- each plan now snapshots the salesmen it was generated with into
-- `plan_salesmen`, and visits keep a plain salesman_id that resolves against
-- that snapshot. Roster edits/deletes no longer touch existing plans.

CREATE TABLE plan_salesmen (
  journey_plan_id INTEGER NOT NULL REFERENCES journey_plans(id) ON DELETE CASCADE,
  salesman_id INTEGER NOT NULL,  -- roster id at snapshot time; not a live FK
  name TEXT NOT NULL,
  start_location_lat REAL NOT NULL,
  start_location_lng REAL NOT NULL,
  working_days_csv TEXT NOT NULL DEFAULT '0,1,2,3,4',
  working_hours_start TEXT NOT NULL DEFAULT '08:00',
  working_hours_end TEXT NOT NULL DEFAULT '17:00',
  assigned_areas_csv TEXT NOT NULL DEFAULT '',
  assigned_regions_csv TEXT NOT NULL DEFAULT '',
  channel_skills_csv TEXT NOT NULL DEFAULT '',
  include_commute INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (journey_plan_id, salesman_id)
);

-- Backfill: existing plans get a snapshot of the current roster row for every
-- salesman that still owns visits in them. Salesmen already deleted can't be
-- recovered (their visits were cascade-deleted under the old schema).
INSERT INTO plan_salesmen (
  journey_plan_id, salesman_id, name, start_location_lat, start_location_lng,
  working_days_csv, working_hours_start, working_hours_end,
  assigned_areas_csv, assigned_regions_csv, channel_skills_csv, include_commute
)
SELECT pv.journey_plan_id, s.id, s.name, s.start_location_lat, s.start_location_lng,
       s.working_days_csv, s.working_hours_start, s.working_hours_end,
       COALESCE(s.assigned_areas_csv, ''), COALESCE(s.assigned_regions_csv, ''),
       COALESCE(s.channel_skills_csv, ''), COALESCE(s.include_commute, 0)
FROM (SELECT DISTINCT journey_plan_id, salesman_id FROM visits) pv
JOIN salesmen s ON s.id = pv.salesman_id;

-- Rebuild visits without the salesmen FK (SQLite can't drop a single FK in
-- place). salesman_id becomes a plain snapshot key. Nothing references the
-- visits table, so drop+rename inside the migration transaction is safe.
CREATE TABLE visits_new (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  journey_plan_id INTEGER NOT NULL REFERENCES journey_plans(id) ON DELETE CASCADE,
  salesman_id INTEGER NOT NULL,
  customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
  scheduled_date TEXT NOT NULL,
  scheduled_start_time TEXT NOT NULL,
  sequence INTEGER NOT NULL,
  drive_minutes_to INTEGER,
  facetime_minutes INTEGER,
  UNIQUE (journey_plan_id, salesman_id, scheduled_date, sequence)
);

INSERT INTO visits_new (
  id, journey_plan_id, salesman_id, customer_id, scheduled_date,
  scheduled_start_time, sequence, drive_minutes_to, facetime_minutes
)
SELECT id, journey_plan_id, salesman_id, customer_id, scheduled_date,
       scheduled_start_time, sequence, drive_minutes_to, facetime_minutes
FROM visits;

DROP TABLE visits;
ALTER TABLE visits_new RENAME TO visits;

CREATE INDEX idx_visits_plan_date ON visits(journey_plan_id, scheduled_date);
CREATE INDEX idx_visits_salesman_date ON visits(salesman_id, scheduled_date);
