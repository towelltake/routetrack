-- 0005_phase3_plans.sql — Phase 3: optimizer output (plans + visits scoped to a dataset),
-- area constraint (customer.area + salesman.assigned_areas), and Directions polyline cache.

-- Plans are now scoped to the dataset they were built against, and remember the solver's
-- diagnostics so the Plan screen can show why a customer was skipped.
ALTER TABLE journey_plans ADD COLUMN dataset_id INTEGER REFERENCES customer_datasets(id) ON DELETE RESTRICT;
ALTER TABLE journey_plans ADD COLUMN status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'final'));
ALTER TABLE journey_plans ADD COLUMN solver_log TEXT;

CREATE INDEX idx_journey_plans_dataset ON journey_plans(dataset_id, created_at DESC);

-- Free-text territory tag per customer; populated from a column in the import file.
ALTER TABLE customers ADD COLUMN area TEXT;
CREATE INDEX idx_customers_area ON customers(dataset_id, area);

-- Comma-separated area names the salesman covers. Empty string = no restriction (covers all areas).
ALTER TABLE salesmen ADD COLUMN assigned_areas_csv TEXT NOT NULL DEFAULT '';

-- Cache for the encoded polyline + totals of a single day-route. Keyed by a hash of the
-- ordered (lat, lng) waypoint sequence so we never re-bill Directions for the same route.
CREATE TABLE directions_cache (
  waypoints_hash TEXT PRIMARY KEY,
  encoded_polyline TEXT NOT NULL,
  total_distance_meters INTEGER NOT NULL,
  total_duration_seconds INTEGER NOT NULL,
  fetched_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
