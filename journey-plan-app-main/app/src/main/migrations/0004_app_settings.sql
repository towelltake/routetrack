-- 0004_app_settings.sql — single-row settings table. id is pinned to 1 so there's only ever
-- one row; UPDATE always targets WHERE id = 1. Defaults match CLAUDE.md §6 (Oman working week,
-- 15 min facetime, monthly visit).

CREATE TABLE app_settings (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  default_facetime_minutes INTEGER NOT NULL DEFAULT 15,
  default_monthly_frequency INTEGER NOT NULL DEFAULT 1,
  default_working_days_csv TEXT NOT NULL DEFAULT '0,1,2,3,4',
  default_working_hours_start TEXT NOT NULL DEFAULT '08:00',
  default_working_hours_end TEXT NOT NULL DEFAULT '17:00',
  geocode_batch_size INTEGER NOT NULL DEFAULT 25,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO app_settings (id) VALUES (1);
