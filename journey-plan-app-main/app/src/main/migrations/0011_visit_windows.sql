-- 0011_visit_windows.sql — optional per-customer visit time-of-day window.
-- "HH:MM" wall clock, both set or both NULL. Used by the solver as a SOFT
-- constraint (visits outside the window are penalised like working-window
-- overflow, never dropped). NULL = any time of day; existing rows unaffected.

ALTER TABLE customers ADD COLUMN visit_window_start TEXT;
ALTER TABLE customers ADD COLUMN visit_window_end TEXT;
