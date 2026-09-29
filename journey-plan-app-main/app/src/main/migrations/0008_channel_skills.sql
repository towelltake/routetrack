-- 0008_channel_skills.sql — Phase 8: Modern Trade / Traditional Trade channel as a
-- salesman skill. Orthogonal to `area` — a Muscat-MT salesman serves only Muscat
-- customers tagged "MT". Empty channel_skills_csv = no skill restriction (mirror
-- of the empty assigned_areas_csv "catch-all" semantics).

ALTER TABLE customers
  ADD COLUMN channel TEXT;  -- "MT", "TT", or NULL (no channel constraint)

ALTER TABLE salesmen
  ADD COLUMN channel_skills_csv TEXT NOT NULL DEFAULT '';
