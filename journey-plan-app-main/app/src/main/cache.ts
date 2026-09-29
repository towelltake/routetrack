import type { MatrixCell, MatrixPoint } from '@journey/shared';
import { getDb } from './db';

const COORD_PRECISION = 6;

function roundCoord(n: number): number {
  const factor = 10 ** COORD_PRECISION;
  return Math.round(n * factor) / factor;
}

export interface DistancePair {
  origin: MatrixPoint;
  dest: MatrixPoint;
  originIndex: number;
  destIndex: number;
}

interface CachedCellRow {
  origin_lat: number;
  origin_lng: number;
  dest_lat: number;
  dest_lng: number;
  duration_seconds: number;
  distance_meters: number;
}

/**
 * Lookup cached cells for a list of OD pairs. Returns one MatrixCell per pair found;
 * pairs without a cache hit are omitted (caller knows which to ask OSRM for).
 */
export function lookupDistanceCache(pairs: DistancePair[]): MatrixCell[] {
  if (pairs.length === 0) return [];
  const stmt = getDb().prepare(
    `SELECT origin_lat, origin_lng, dest_lat, dest_lng, duration_seconds, distance_meters
     FROM distance_cache
     WHERE origin_lat = ? AND origin_lng = ? AND dest_lat = ? AND dest_lng = ?`,
  );
  const cells: MatrixCell[] = [];
  for (const p of pairs) {
    const oLat = roundCoord(p.origin.lat);
    const oLng = roundCoord(p.origin.lng);
    const dLat = roundCoord(p.dest.lat);
    const dLng = roundCoord(p.dest.lng);
    const row = stmt.get(oLat, oLng, dLat, dLng) as CachedCellRow | undefined;
    if (row) {
      cells.push({
        originIndex: p.originIndex,
        destIndex: p.destIndex,
        durationSeconds: row.duration_seconds,
        // OSRM returns distance -1 for OD pairs that snap to the same road
        // node (customers a few metres apart). Clamp on read too, not just on
        // write, so the two such rows already poisoned in existing DBs don't
        // leak a negative into the analytics distance total.
        distanceMeters: Math.max(0, row.distance_meters),
        status: 'ok',
      });
    }
  }
  return cells;
}

/**
 * Persist 'ok' matrix cells to the cache. Bad cells are skipped — we don't want to
 * cache 'NOT_FOUND' or 'ZERO_RESULTS' since they might just be transient input issues.
 */
export function putDistanceCache(
  cells: MatrixCell[],
  index: Map<number, MatrixPoint>,
): void {
  const ok = cells.filter(
    (c) => c.status === 'ok' && c.durationSeconds !== null && c.distanceMeters !== null,
  );
  if (ok.length === 0) return;
  const db = getDb();
  const stmt = db.prepare(
    `INSERT OR REPLACE INTO distance_cache
       (origin_lat, origin_lng, dest_lat, dest_lng, duration_seconds, distance_meters)
     VALUES (?, ?, ?, ?, ?, ?)`,
  );
  const tx = db.transaction(() => {
    for (const c of ok) {
      const origin = index.get(c.originIndex);
      const dest = index.get(c.destIndex);
      if (!origin || !dest) continue;
      stmt.run(
        roundCoord(origin.lat),
        roundCoord(origin.lng),
        roundCoord(dest.lat),
        roundCoord(dest.lng),
        c.durationSeconds,
        // Clamp the OSRM same-node sentinel (-1) to 0 at write so it never
        // enters the cache; the solver uses duration, but the analytics
        // distance total sums these directly. (filter above guarantees non-null)
        Math.max(0, c.distanceMeters as number),
      );
    }
  });
  tx();
}

