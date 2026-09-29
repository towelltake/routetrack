-- 0007_customer_assignments.sql — Phase 7a: upfront salesman assignment.
-- Adds the algorithm-computed assignment column on customers, distinct from the
-- manual pin. Effective salesman for the solver (later, 7b) =
-- COALESCE(pinned_salesman_id, assigned_salesman_id).

ALTER TABLE customers
  ADD COLUMN assigned_salesman_id INTEGER
    REFERENCES salesmen(id) ON DELETE SET NULL;

CREATE INDEX idx_customers_assigned_salesman ON customers(assigned_salesman_id);

-- Per-dataset metadata on the last assignment run. Drives the "stale" banner
-- in the Assignments screen by recording when assignments were last computed
-- against this dataset.
CREATE TABLE customer_assignment_runs (
  dataset_id INTEGER PRIMARY KEY REFERENCES customer_datasets(id) ON DELETE CASCADE,
  computed_at TEXT NOT NULL,
  customer_count INTEGER NOT NULL,
  salesman_count INTEGER NOT NULL,
  unassignable_count INTEGER NOT NULL,
  runtime_seconds REAL NOT NULL,
  solver_status TEXT NOT NULL
);
