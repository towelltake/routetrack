-- 0003_customers_extend.sql — attach customers to a dataset and require a stable external code.
-- external_code is the upload's natural key (e.g. CCID). Combined with dataset_id it gives stable
-- identity within a dataset for re-upserts. ON DELETE RESTRICT on dataset_id forces archival
-- instead of deletion when visits reference a dataset's customers.

ALTER TABLE customers ADD COLUMN external_code TEXT NOT NULL DEFAULT '';
ALTER TABLE customers ADD COLUMN dataset_id INTEGER REFERENCES customer_datasets(id) ON DELETE RESTRICT;

CREATE UNIQUE INDEX idx_customers_dataset_external_code
  ON customers(dataset_id, external_code)
  WHERE dataset_id IS NOT NULL AND external_code != '';

CREATE INDEX idx_customers_dataset ON customers(dataset_id);
