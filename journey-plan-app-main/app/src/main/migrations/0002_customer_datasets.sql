-- 0002_customer_datasets.sql — track each upload as a named, archivable dataset.
-- Each import creates a new dataset; the previous dataset is archived (is_active=0) but kept
-- so journey plans built against it stay linkable. Exactly one dataset is active at a time,
-- enforced by the partial unique index below.

CREATE TABLE customer_datasets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  source_filename TEXT NOT NULL,
  imported_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active INTEGER NOT NULL DEFAULT 0 CHECK (is_active IN (0, 1)),
  row_count INTEGER NOT NULL DEFAULT 0
);

CREATE UNIQUE INDEX idx_one_active_dataset
  ON customer_datasets(is_active)
  WHERE is_active = 1;
