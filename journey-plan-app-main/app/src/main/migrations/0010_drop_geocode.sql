-- 0010_drop_geocode.sql — drop vestigial geocoding schema.
--
-- Phase 10 (2026-05-22) removed every Google API including the Geocoding
-- client. Since then `customers.geocode_status` has always been written as
-- 'ok', `geocode_confidence` has always been NULL, and the `geocode_cache` +
-- `directions_cache` tables have been write-once-then-ignore. This migration
-- drops them so a fresh `SELECT *` no longer surfaces dead columns and the
-- TypeScript model can be slimmed.
--
-- Requires SQLite >= 3.35 (DROP COLUMN). better-sqlite3 ships a modern
-- SQLite; the only risk is on installations whose system SQLite is older,
-- but better-sqlite3 statically links its own copy, so version drift cannot
-- happen in this codebase.

DROP INDEX IF EXISTS idx_customers_geocode_status;
ALTER TABLE customers DROP COLUMN geocode_status;
ALTER TABLE customers DROP COLUMN geocode_confidence;

DROP TABLE IF EXISTS geocode_cache;
DROP TABLE IF EXISTS directions_cache;

ALTER TABLE app_settings DROP COLUMN geocode_batch_size;
