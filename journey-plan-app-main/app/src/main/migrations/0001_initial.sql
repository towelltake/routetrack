-- 0001_initial.sql — initial schema for Journey Plan App.
-- Working day codes: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat (Oman default work week = 0..4).

CREATE TABLE salesmen (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  start_location_lat REAL NOT NULL,
  start_location_lng REAL NOT NULL,
  working_days_csv TEXT NOT NULL DEFAULT '0,1,2,3,4',
  working_hours_start TEXT NOT NULL DEFAULT '08:00',
  working_hours_end TEXT NOT NULL DEFAULT '17:00',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  address TEXT,
  lat REAL,
  lng REAL,
  facetime_minutes INTEGER NOT NULL DEFAULT 15,
  monthly_frequency INTEGER NOT NULL DEFAULT 1,
  allowed_days_csv TEXT,
  pinned_salesman_id INTEGER REFERENCES salesmen(id) ON DELETE SET NULL,
  geocode_status TEXT NOT NULL DEFAULT 'pending',  -- pending | ok | failed
  geocode_confidence REAL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_customers_pinned_salesman ON customers(pinned_salesman_id);
CREATE INDEX idx_customers_geocode_status ON customers(geocode_status);

CREATE TABLE journey_plans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  period_start TEXT NOT NULL,  -- ISO date
  period_end TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE visits (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  journey_plan_id INTEGER NOT NULL REFERENCES journey_plans(id) ON DELETE CASCADE,
  salesman_id INTEGER NOT NULL REFERENCES salesmen(id) ON DELETE CASCADE,
  customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
  scheduled_date TEXT NOT NULL,
  scheduled_start_time TEXT NOT NULL,
  sequence INTEGER NOT NULL,
  drive_minutes_to INTEGER,
  facetime_minutes INTEGER,
  UNIQUE (journey_plan_id, salesman_id, scheduled_date, sequence)
);

CREATE INDEX idx_visits_plan_date ON visits(journey_plan_id, scheduled_date);
CREATE INDEX idx_visits_salesman_date ON visits(salesman_id, scheduled_date);

-- Cache for Google Distance Matrix results so we never pay for the same OD pair twice.
CREATE TABLE distance_cache (
  origin_lat REAL NOT NULL,
  origin_lng REAL NOT NULL,
  dest_lat REAL NOT NULL,
  dest_lng REAL NOT NULL,
  duration_seconds INTEGER NOT NULL,
  distance_meters INTEGER NOT NULL,
  fetched_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (origin_lat, origin_lng, dest_lat, dest_lng)
);

-- Cache for Geocoding results.
CREATE TABLE geocode_cache (
  query TEXT PRIMARY KEY,
  lat REAL NOT NULL,
  lng REAL NOT NULL,
  confidence REAL,
  formatted_address TEXT,
  fetched_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
