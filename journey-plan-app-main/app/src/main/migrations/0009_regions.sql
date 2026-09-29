-- 0009_regions.sql — Phase 9: region as a second eligibility dimension.
-- Customer carries an optional `region` (free-text, e.g. "Nizwa", "Salalah", "Muscat")
-- typically broader than `area` (a sub-wilayat like "Dhank", "Ibri", "Mirbat").
-- Salesman carries `assigned_regions_csv` mirroring `assigned_areas_csv` shape.
-- Eligibility: customer matches a salesman if EITHER the area constraint OR the
-- region constraint is satisfied; both empty on the salesman = catch-all.

ALTER TABLE customers ADD COLUMN region TEXT;
CREATE INDEX idx_customers_region ON customers(dataset_id, region);

-- Comma-separated region names the salesman covers. Empty = no region restriction
-- (the area list — also possibly empty — is what's left to constrain coverage).
ALTER TABLE salesmen ADD COLUMN assigned_regions_csv TEXT NOT NULL DEFAULT '';
