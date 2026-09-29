import type { PlanDistance, Visit } from '@journey/shared';
import { getDb } from './db';
import { lookupDistanceCache, type DistancePair } from './cache';
import { listVisitsForPlan } from './repo/visits';
import { listPlanSalesmen } from './repo/planSalesmen';

interface CoordRow {
  id: number;
  lat: number;
  lng: number;
}

// Road distance driven per salesman, reconstructed from the persisted route
// legs + the distance_cache. No distance column is stored on visits, so this
// works on every existing plan without a regenerate.
//
// We deliberately mirror the drive-TIME semantics in computeMetrics: distance
// covers exactly the legs that carry a drive_minutes_to — every
// customer→customer hop, plus the home→first commute leg when the salesman had
// commute enabled. The final leg home is excluded (no visit records it), same
// as the drive-time total. So "Distance driven" is the spatial companion to
// "Drive time", measured over the same legs.
export function distanceForPlan(planId: number): PlanDistance {
  const visits = listVisitsForPlan(planId); // ordered by salesman, date, sequence
  const salesmen = listPlanSalesmen(planId);
  const salesmanById = new Map(salesmen.map((s) => [s.id, s]));

  // Resolve customer coords by id — authoritative DB values, the same source
  // the matrix used, so roundCoord in lookupDistanceCache hits the cached rows.
  const ids = [...new Set(visits.map((v) => v.customerId))];
  const coords = new Map<number, { lat: number; lng: number }>();
  if (ids.length > 0) {
    const placeholders = ids.map(() => '?').join(',');
    const rows = getDb()
      .prepare(`SELECT id, lat, lng FROM customers WHERE id IN (${placeholders})`)
      .all(...ids) as CoordRow[];
    for (const r of rows) coords.set(r.id, { lat: r.lat, lng: r.lng });
  }

  // Group visits into (salesman, date) routes, preserving the sequence order
  // listVisitsForPlan already returns.
  const routes = new Map<string, Visit[]>();
  for (const v of visits) {
    const key = `${v.salesmanId}:${v.scheduledDate}`;
    let arr = routes.get(key);
    if (!arr) {
      arr = [];
      routes.set(key, arr);
    }
    arr.push(v);
  }

  // Build leg pairs with a parallel salesman back-reference. Each pair gets a
  // unique originIndex so the returned cells map back unambiguously.
  const pairs: DistancePair[] = [];
  const pairSalesman: number[] = [];
  let idx = 0;
  for (const route of routes.values()) {
    const salesmanId = route[0]!.salesmanId;
    const s = salesmanById.get(salesmanId);
    for (let i = 0; i < route.length; i++) {
      const cur = coords.get(route[i]!.customerId);
      if (!cur) continue;
      if (i === 0) {
        if (
          s?.includeCommute &&
          s.startLocationLat != null &&
          s.startLocationLng != null
        ) {
          pairs.push({
            origin: { lat: s.startLocationLat, lng: s.startLocationLng },
            dest: cur,
            originIndex: idx,
            destIndex: idx + 1,
          });
          pairSalesman.push(salesmanId);
          idx += 2;
        }
        continue;
      }
      const prev = coords.get(route[i - 1]!.customerId);
      if (!prev) continue;
      pairs.push({ origin: prev, dest: cur, originIndex: idx, destIndex: idx + 1 });
      pairSalesman.push(salesmanId);
      idx += 2;
    }
  }

  const cells = lookupDistanceCache(pairs);
  const distByOrigin = new Map<number, number>();
  for (const c of cells) {
    if (c.distanceMeters != null) distByOrigin.set(c.originIndex, c.distanceMeters);
  }

  const perSalesmanMeters = new Map<number, number>();
  let legsFound = 0;
  let legsMissing = 0;
  for (let p = 0; p < pairs.length; p++) {
    const d = distByOrigin.get(pairs[p]!.originIndex);
    if (d == null) {
      legsMissing += 1;
      continue;
    }
    legsFound += 1;
    const sid = pairSalesman[p]!;
    perSalesmanMeters.set(sid, (perSalesmanMeters.get(sid) ?? 0) + d);
  }

  let totalMeters = 0;
  const perSalesman: PlanDistance['perSalesman'] = [];
  for (const [salesmanId, meters] of perSalesmanMeters) {
    totalMeters += meters;
    perSalesman.push({ salesmanId, meters });
  }

  return { totalMeters, perSalesman, legsFound, legsMissing };
}
