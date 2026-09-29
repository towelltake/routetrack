-- Track whether a cached Directions polyline was computed against road-snapped
-- waypoints (Google ZERO_RESULTS fallback). Lets the renderer flag approximate
-- routes correctly on cache hits.

ALTER TABLE directions_cache ADD COLUMN snapped INTEGER NOT NULL DEFAULT 0;
