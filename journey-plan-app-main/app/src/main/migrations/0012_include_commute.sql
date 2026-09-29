-- 0012_include_commute.sql — opt-in commute legs per salesman.
-- When 1 AND the salesman has a start location, the optimizer prices and
-- times the home→first-customer and last-customer→home legs. 0 (default)
-- keeps the legacy behavior: the day starts at the first customer.

ALTER TABLE salesmen ADD COLUMN include_commute INTEGER NOT NULL DEFAULT 0;
