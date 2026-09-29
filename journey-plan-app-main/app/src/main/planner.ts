import type {
  AssignSalesmenRequest,
  AssignSalesmenResponse,
  AssignmentComputeResult,
  Customer,
  MatrixBatchResponse,
  MatrixCell,
  MatrixPoint,
  OptimizeRequest,
  OptimizeResponse,
  OptimizeSalesman,
  OsrmRouteResponse,
  ResequenceDayRequest,
  ResequenceDayResponse,
  SalesChannel,
  Salesman,
  SolverLog,
  SuggestTeamSizeRequest,
  SuggestTeamSizeResponse,
  TeamSizeTemplate,
  UnassignedCustomer,
  Visit,
} from '@journey/shared';
import {
  lookupDistanceCache,
  putDistanceCache,
  type DistancePair,
} from './cache';
import { getDb } from './db';
import {
  createPlan,
  setSolverLog,
} from './repo/journeyPlans';
import {
  bulkApplyAssignments,
  clearAssignmentsForDataset,
  getCustomer,
  listGeocodedCustomersForDataset,
} from './repo/customers';
import { recordRun } from './repo/assignmentRuns';
import {
  addSalesmanToPlanSnapshot,
  getPlanSalesman,
  snapshotSalesmenForPlan,
} from './repo/planSalesmen';
import { getSalesman, listSalesmen } from './repo/salesmen';
import {
  getVisit,
  insertVisitsBulk,
  listVisitsForCustomerInPlan,
  listVisitsForSalesmanDay,
  replaceVisitsForDay,
  type VisitInsert,
} from './repo/visits';
import { getSidecarUrl } from './sidecar';
import { request as httpRequest } from 'node:http';

const KNN_PER_CUSTOMER = 20;

// Replaces the global `fetch` for sidecar IPC. fetch (undici) imposes a
// 5-minute headersTimeout we can't disable without bundling undici. The
// sidecar is localhost, can run multi-minute solves with no timeout cap,
// and benefits from raw http.request with timeout = 0 (no limit).
async function sidecarFetch<T>(path: string, body: unknown): Promise<T> {
  const url = getSidecarUrl();
  if (!url) throw new Error('sidecar not ready');
  const u = new URL(`${url}${path}`);
  const payload = JSON.stringify(body);
  return new Promise<T>((resolve, reject) => {
    const req = httpRequest(
      {
        hostname: u.hostname,
        port: u.port,
        path: u.pathname + u.search,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': Buffer.byteLength(payload).toString(),
        },
        timeout: 0,
      },
      (res) => {
        const chunks: Buffer[] = [];
        res.on('data', (c: Buffer) => chunks.push(c));
        res.on('end', () => {
          const text = Buffer.concat(chunks).toString('utf-8');
          const status = res.statusCode ?? 0;
          if (status < 200 || status >= 300) {
            reject(new Error(`${path} returned HTTP ${status}: ${text}`));
            return;
          }
          try {
            resolve(JSON.parse(text) as T);
          } catch (err) {
            reject(err instanceof Error ? err : new Error(String(err)));
          }
        });
        res.on('error', reject);
      },
    );
    req.on('error', reject);
    req.setTimeout(0);
    req.write(payload);
    req.end();
  });
}

function customerToOptimize(c: Customer): {
  id: number;
  lat: number;
  lng: number;
  facetimeMinutes: number;
  monthlyFrequency: number;
  allowedDays: number[] | null;
  pinnedSalesmanId: number | null;
  area: string | null;
  region: string | null;
  channel: SalesChannel | null;
  visitWindowStart: string | null;
  visitWindowEnd: string | null;
} {
  return {
    id: c.id,
    lat: c.lat as number,
    lng: c.lng as number,
    facetimeMinutes: c.facetimeMinutes,
    monthlyFrequency: c.monthlyFrequency,
    allowedDays: c.allowedDays,
    pinnedSalesmanId: c.pinnedSalesmanId,
    area: c.area,
    region: c.region,
    channel: c.channel,
    visitWindowStart: c.visitWindowStart,
    visitWindowEnd: c.visitWindowEnd,
  };
}

// Sticky-salesman pin for the /optimize family. User-set `pinnedSalesmanId`
// wins; falls back to the Phase 7a assignment so every customer's monthly
// visits go to one salesman. /assign-salesmen MUST NOT use this — it consumes
// the raw pin and produces the assignment, so coalescing there would lock the
// algorithm's own output back in as if it were a user pin.
function effectivePinnedSalesmanId(c: Customer): number | null {
  return c.pinnedSalesmanId ?? c.assignedSalesmanId;
}

function salesmanToOptimize(s: Salesman): OptimizeSalesman {
  return {
    id: s.id,
    workingDays: s.workingDays,
    workingHoursStart: s.workingHoursStart,
    workingHoursEnd: s.workingHoursEnd,
    assignedAreas: s.assignedAreas,
    assignedRegions: s.assignedRegions,
    channelSkills: s.channelSkills,
    startLocationLat: s.startLocationLat,
    startLocationLng: s.startLocationLng,
    includeCommute: s.includeCommute,
  };
}

/**
 * Haversine distance in km — cheap, only used to rank k-nearest-neighbours so we
 * fetch the matrix for plausible same-day pairs only.
 */
function haversineKm(a: { lat: number; lng: number }, b: { lat: number; lng: number }): number {
  const R = 6371;
  const dLat = ((b.lat - a.lat) * Math.PI) / 180;
  const dLng = ((b.lng - a.lng) * Math.PI) / 180;
  const lat1 = (a.lat * Math.PI) / 180;
  const lat2 = (b.lat * Math.PI) / 180;
  const x =
    Math.sin(dLat / 2) ** 2 + Math.sin(dLng / 2) ** 2 * Math.cos(lat1) * Math.cos(lat2);
  return 2 * R * Math.asin(Math.sqrt(x));
}

/**
 * Build the matrix-fetch spec the solver needs distances for.
 *
 * Grouping order (each full-graph group needs every pairwise drive time):
 *   1. EFFECTIVE salesman = `pinnedSalesmanId ?? assignedSalesmanId`. All
 *      customers serviced by the same salesman get a complete graph so the
 *      solver sees real drive times for every intra-salesman pair, regardless
 *      of which area each customer is tagged with. Without this, a Rumais
 *      salesman serving customers across "Rumais", "Nakhal", and "Awabi" gets
 *      cross-area pairs missing from the matrix → haversine fallback at 40 km/h
 *      under-estimates real Omani drives (mountain roads, no straight lines)
 *      by 2-3×, and the solver happily clubs distant outliers onto coastal
 *      cluster days because the cost is masked. Verified 2026-05-20 against
 *      Plan #45 Rumais Sun 2026-05-24: 37 visits spanning ~150 km radius on
 *      one day, including a south-mountain outlier that should be on its own.
 *   2. AREA (for unassigned customers). Same intent: any two customers tagged
 *      in the same area may share a salesman's route.
 *   3. REGION fallback for unassigned customers lacking an area.
 *   4. Floating (no salesman, no area, no region) — k-NN, bounded fallback
 *      acceptable for the last-resort case.
 *
 * The returned spec carries full-graph groups as customer arrays (one OSRM
 * self-matrix call each, see fetchMatrix) and the floating set as a flat pair
 * list (k-NN means no shape benefit from grouping). Replaces the older
 * `buildNeededPairs` which returned a flat pair list — that forced the matrix
 * fetcher to over-fetch a 25×25 OSRM rectangle per 25 needed pairs.
 */
// A point participating in the matrix. Customers use their real id; a
// commute-enabled salesman's start location rides along under the pseudo-id
// `-salesmanId` (the sidecar's commute_drive_minutes looks cells up by that
// key). Negative ids can never collide with SQLite rowids.
interface MatrixNode {
  id: number;
  lat: number;
  lng: number;
}

function toMatrixNode(c: Customer): MatrixNode {
  return { id: c.id, lat: c.lat as number, lng: c.lng as number };
}

function salesmanStartNode(s: Salesman): MatrixNode {
  return { id: -s.id, lat: s.startLocationLat, lng: s.startLocationLng };
}

interface MatrixSpec {
  fullGraphs: MatrixNode[][];
  knnPairs: Array<{ a: MatrixNode; b: MatrixNode }>;
}

function buildMatrixSpec(customers: Customer[], commuteSalesmen: Salesman[] = []): MatrixSpec {
  // Group by (effective-salesman OR area OR region OR floating). Same fall-
  // through order as the legacy buildNeededPairs.
  const groups = new Map<string, Customer[]>();
  for (const c of customers) {
    const effectiveSalesmanId = c.pinnedSalesmanId ?? c.assignedSalesmanId;
    let key: string;
    if (effectiveSalesmanId !== null) key = `salesman:${effectiveSalesmanId}`;
    else if (c.area) key = `area:${c.area}`;
    else if (c.region) key = `region:${c.region}`;
    else key = 'floating';
    const arr = groups.get(key) ?? [];
    arr.push(c);
    groups.set(key, arr);
  }

  const commuteById = new Map(commuteSalesmen.map((s) => [s.id, s]));
  const fullGraphs: MatrixNode[][] = [];
  const knnPairs: Array<{ a: MatrixNode; b: MatrixNode }> = [];
  const knnSeen = new Set<string>();

  for (const [key, group] of groups) {
    if (key === 'floating') {
      // k-NN against itself — limited universe, route quality bounded by
      // haversine fallback in vrp.py. Each floating customer's K nearest
      // peers are added as both-direction pairs.
      for (const a of group) {
        const aLat = a.lat as number;
        const aLng = a.lng as number;
        const ranked = group
          .filter((b) => b.id !== a.id)
          .map((b) => ({
            c: b,
            d: haversineKm(
              { lat: aLat, lng: aLng },
              { lat: b.lat as number, lng: b.lng as number },
            ),
          }))
          .sort((x, y) => x.d - y.d)
          .slice(0, KNN_PER_CUSTOMER);
        for (const { c: b } of ranked) {
          const k1 = `${a.id}->${b.id}`;
          if (!knnSeen.has(k1)) {
            knnSeen.add(k1);
            knnPairs.push({ a: toMatrixNode(a), b: toMatrixNode(b) });
          }
          const k2 = `${b.id}->${a.id}`;
          if (!knnSeen.has(k2)) {
            knnSeen.add(k2);
            knnPairs.push({ a: toMatrixNode(b), b: toMatrixNode(a) });
          }
        }
      }
    } else {
      // Full-graph group: OSRM gets a single self-matrix call with origins =
      // destinations = group customers. Every returned cell is between two
      // customers that may share a route, so all of it is cache-worthy AND
      // immediately consumed — no off-diagonal waste like the old flat-pair
      // path had. A commute-enabled salesman's start point joins their own
      // group so the solver gets real home↔customer drive times. Groups that
      // end up with fewer than 2 nodes need no matrix at all and are skipped.
      const nodes = group.map(toMatrixNode);
      if (key.startsWith('salesman:')) {
        const s = commuteById.get(Number(key.slice('salesman:'.length)));
        if (s) nodes.push(salesmanStartNode(s));
      }
      if (nodes.length >= 2) fullGraphs.push(nodes);
    }
  }

  return { fullGraphs, knnPairs };
}

interface MatrixFetchResult {
  cells: MatrixCell[];
  requested: number;
  cached: number;
}

export type ProgressPhase =
  | 'loading'
  | 'clustering'
  | 'matrix'
  | 'optimizing'
  | 'saving'
  | 'done';

export interface ProgressEvent {
  phase: ProgressPhase;
  message: string;
  percent: number;
}

type ProgressCallback = (evt: ProgressEvent) => void;

/**
 * Fetch the drive-time matrix for the given spec. One self-matrix OSRM call
 * per full-graph group; the floating k-NN set uses small parallel-array
 * batches that aren't worth grouping (few pairs, high cross-group sparsity).
 *
 * History: pre-refactor (2026-05) every batch was a 25×25 parallel-array
 * request that asked OSRM to compute 625 cells but only consumed the 25
 * diagonal. Off-diagonal cells were cached opportunistically but mostly
 * between random cross-cluster customers — cache hit rate on subsequent
 * runs was poor. Per-group self-matrix flips that: every OSRM-computed cell
 * is between two same-group customers, so every cell is both consumed now
 * AND useful on next run.
 */
async function fetchMatrix(
  spec: MatrixSpec,
  onProgress?: (chunkIndex: number, totalChunks: number) => void,
): Promise<MatrixFetchResult> {
  const result: MatrixCell[] = [];
  let requested = 0;
  let cachedCount = 0;

  // Each full-graph group + the knn block = one progress step.
  const totalChunks = spec.fullGraphs.length + (spec.knnPairs.length > 0 ? 1 : 0);
  let chunkIndex = 0;

  // --- Full-graph groups: one OSRM self-matrix call per group ---
  for (const group of spec.fullGraphs) {
    onProgress?.(chunkIndex, totalChunks);
    chunkIndex += 1;

    // Dedupe by lat/lng so two customers stacked on the same coordinate
    // don't produce a redundant OSRM column. customerIdsByPointIndex tracks
    // which node IDs map to each unique point so the result cells can be
    // re-keyed back to (node_id, node_id) — node ids are customer ids plus
    // the negative pseudo-ids of commute-enabled salesman start points.
    const points: MatrixPoint[] = [];
    const pointIndexByKey = new Map<string, number>();
    const customerIdsByPointIndex = new Map<number, number[]>();
    const pointKey = (lat: number, lng: number): string =>
      `${lat.toFixed(6)},${lng.toFixed(6)}`;
    for (const c of group) {
      const { lat, lng } = c;
      const key = pointKey(lat, lng);
      let idx = pointIndexByKey.get(key);
      if (idx === undefined) {
        idx = points.length;
        pointIndexByKey.set(key, idx);
        points.push({ lat, lng });
        customerIdsByPointIndex.set(idx, []);
      }
      customerIdsByPointIndex.get(idx)!.push(c.id);
    }
    if (points.length < 2) continue;

    // Cache lookup pass: every ordered (point_i, point_j) where i!=j.
    const distancePairs: DistancePair[] = [];
    for (let i = 0; i < points.length; i++) {
      for (let j = 0; j < points.length; j++) {
        if (i === j) continue;
        distancePairs.push({
          origin: points[i]!,
          dest: points[j]!,
          originIndex: i,
          destIndex: j,
        });
      }
    }
    const cached = lookupDistanceCache(distancePairs);
    cachedCount += cached.length;
    // Hydrate result rows for the cached pairs (one per ordered customer pair
    // sharing the cached point pair). When two customers stack on a single
    // point, the self-cell (i,i) is implicitly zero and not cached.
    const cachedCells = new Map<string, { duration: number; distance: number }>();
    for (const c of cached) {
      cachedCells.set(`${c.originIndex}->${c.destIndex}`, {
        duration: c.durationSeconds!,
        distance: c.distanceMeters!,
      });
    }

    const totalPairs = points.length * (points.length - 1);
    if (cached.length < totalPairs) {
      // At least one pair is uncached — easiest to request the whole self-
      // matrix in one call and cache everything. The sidecar handles internal
      // chunking against OSRM's URL-length cap; for typical Towell groups
      // (<= ~100 customers) it's one /table call.
      const resp = await sidecarFetch<MatrixBatchResponse>('/matrix/batch', {
        origins: points,
        destinations: points,
      });
      requested += resp.cells.length;

      // Cache all OK cells. The sidecar's origin_index / dest_index are
      // 0..points.length-1 (since origins === destinations === points).
      const indexMap = new Map<number, MatrixPoint>();
      points.forEach((p, i) => indexMap.set(i, p));
      putDistanceCache(resp.cells, indexMap);

      // Overlay onto cachedCells so the result-emit pass below treats fresh
      // cells the same as cached ones.
      for (const c of resp.cells) {
        if (c.status !== 'ok') continue;
        if (c.durationSeconds === null || c.distanceMeters === null) continue;
        if (c.originIndex === c.destIndex) continue;
        cachedCells.set(`${c.originIndex}->${c.destIndex}`, {
          duration: c.durationSeconds,
          distance: c.distanceMeters,
        });
      }
    }

    // Emit one MatrixCell per ordered customer pair (a, b) that share the
    // resolved (point_i, point_j) cell. Two customers stacked on the same
    // coordinate produce a zero self-pair, surfaced as duration=0/distance=0.
    for (let i = 0; i < points.length; i++) {
      for (let j = 0; j < points.length; j++) {
        if (i === j) continue;
        const cell = cachedCells.get(`${i}->${j}`);
        if (!cell) continue;
        const aIds = customerIdsByPointIndex.get(i) ?? [];
        const bIds = customerIdsByPointIndex.get(j) ?? [];
        for (const aId of aIds) {
          for (const bId of bIds) {
            if (aId === bId) continue;
            result.push({
              originIndex: aId,
              destIndex: bId,
              durationSeconds: cell.duration,
              distanceMeters: cell.distance,
              status: 'ok',
            });
          }
        }
      }
    }
  }

  // --- KNN pairs: existing flat-pair batching, unchanged semantics ---
  if (spec.knnPairs.length > 0) {
    onProgress?.(chunkIndex, totalChunks);
    chunkIndex += 1;

    const distancePairs: DistancePair[] = spec.knnPairs.map((p, i) => ({
      origin: { lat: p.a.lat, lng: p.a.lng },
      dest: { lat: p.b.lat, lng: p.b.lng },
      originIndex: i,
      destIndex: i,
    }));
    const cached = lookupDistanceCache(distancePairs);
    cachedCount += cached.length;
    const cachedSet = new Set(cached.map((c) => `${c.originIndex}->${c.destIndex}`));
    for (const c of cached) {
      const pair = spec.knnPairs[c.originIndex];
      if (!pair) continue;
      result.push({
        originIndex: pair.a.id,
        destIndex: pair.b.id,
        durationSeconds: c.durationSeconds,
        distanceMeters: c.distanceMeters,
        status: 'ok',
      });
    }
    const misses = spec.knnPairs
      .map((p, i) => ({ pair: p, i }))
      .filter(({ i }) => !cachedSet.has(`${i}->${i}`));

    if (misses.length > 0) {
      const CHUNK = 25;
      for (let start = 0; start < misses.length; start += CHUNK) {
        const oChunk: MatrixPoint[] = misses
          .slice(start, start + CHUNK)
          .map(({ pair }) => ({ lat: pair.a.lat, lng: pair.a.lng }));
        const dChunk: MatrixPoint[] = misses
          .slice(start, start + CHUNK)
          .map(({ pair }) => ({ lat: pair.b.lat, lng: pair.b.lng }));
        const resp = await sidecarFetch<MatrixBatchResponse>('/matrix/batch', {
          origins: oChunk,
          destinations: dChunk,
        });
        requested += resp.cells.length;

        // Cache the diagonal cells only — for knn pairs the off-diagonal is
        // between unrelated random customers (low replay value).
        const combined = new Map<number, MatrixPoint>();
        let ix = 0;
        const diagonal: MatrixCell[] = [];
        for (let k = 0; k < oChunk.length; k++) {
          const cell = resp.cells.find((c) => c.originIndex === k && c.destIndex === k);
          if (!cell || cell.status !== 'ok') continue;
          const oi = ix++;
          const di = ix++;
          combined.set(oi, oChunk[k]!);
          combined.set(di, dChunk[k]!);
          diagonal.push({
            originIndex: oi,
            destIndex: di,
            durationSeconds: cell.durationSeconds,
            distanceMeters: cell.distanceMeters,
            status: cell.status,
          });
          const missEntry = misses[start + k];
          if (!missEntry) continue;
          result.push({
            originIndex: missEntry.pair.a.id,
            destIndex: missEntry.pair.b.id,
            durationSeconds: cell.durationSeconds,
            distanceMeters: cell.distanceMeters,
            status: cell.status,
          });
        }
        putDistanceCache(diagonal, combined);
      }
    }
  }

  return { cells: result, requested, cached: cachedCount };
}

export interface GeneratePlanInput {
  datasetId: number;
  name: string;
  periodStart: string;
  periodEnd: string;
}

export interface GeneratePlanResult {
  planId: number;
}

// Format a LOCAL date as YYYY-MM-DD. toISOString() converts to UTC first, so
// in Oman (UTC+4) any local time before 04:00 formats as the previous day —
// which turned the default "next Sunday" into a Saturday for anyone opening
// the app early in the morning.
function toLocalISODate(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function nextPeriodStart(): string {
  const today = new Date();
  // Next Sunday (0 = Sunday in JS getDay).
  const daysAhead = (7 - today.getDay()) % 7;
  const start = new Date(today);
  start.setDate(today.getDate() + (daysAhead || 7));
  return toLocalISODate(start);
}

export function defaultPeriodStart(): string {
  return nextPeriodStart();
}

export function defaultPeriodEnd(start: string): string {
  const [y, m, d] = start.split('-').map(Number);
  const end = new Date(y!, (m ?? 1) - 1, d ?? 1);
  end.setDate(end.getDate() + 27); // 28 days = exactly 4 calendar weeks
  return toLocalISODate(end);
}

// ---- Phase 7a: upfront salesman assignment ----

export interface ComputeAssignmentsInput {
  datasetId: number;
  balanceLambda?: number;
}

// Run the constrained-k-medoids assignment in the sidecar and persist the
// result. Mirrors generatePlan's matrix-then-solver shape but without the
// per-week/per-day machinery — assignment is a one-shot per dataset.
export async function computeAssignments(
  input: ComputeAssignmentsInput,
): Promise<AssignmentComputeResult> {
  const allCustomers = listGeocodedCustomersForDataset(input.datasetId);
  // Drop customers without coordinates — they go straight to unassignable
  // without round-tripping the sidecar.
  const customers = allCustomers.filter((c) => c.lat !== null && c.lng !== null);
  if (customers.length === 0) {
    throw new Error('no geocoded customers in active dataset');
  }
  const salesmen = listSalesmen();
  if (salesmen.length === 0) {
    throw new Error('no salesmen configured');
  }

  // Reuse the matrix spec from generatePlan — same sparsity policy (full
  // graph per salesman/area/region, k-NN for floating customers). The
  // sidecar's haversine fallback handles any missing pair.
  const spec = buildMatrixSpec(customers);
  const matrix = await fetchMatrix(spec);

  const request: AssignSalesmenRequest = {
    customers: customers.map(customerToOptimize),
    salesmen: salesmen.map(salesmanToOptimize),
    matrixCells: matrix.cells,
    // Pass through undefined when no override — JSON.stringify omits the key
    // and Pydantic's AssignSalesmenRequest.balance_lambda default (3.0, see
    // sidecar/models.py) supplies the canonical value. Don't re-default here.
    balanceLambda: input.balanceLambda,
  };
  const response = await sidecarFetch<AssignSalesmenResponse>(
    '/assign-salesmen',
    request,
  );

  // Persist: wipe existing assignments for this dataset, then apply the new ones.
  // Single-shot — no migration of overrides needed because we write to
  // assigned_salesman_id, not pinned_salesman_id.
  clearAssignmentsForDataset(input.datasetId);
  bulkApplyAssignments(
    response.assignments.map((a) => ({
      customerId: a.customerId,
      salesmanId: a.salesmanId,
    })),
  );
  recordRun({
    datasetId: input.datasetId,
    customerCount: customers.length,
    salesmanCount: salesmen.length,
    unassignableCount: response.unassignable.length,
    runtimeSeconds: response.runtimeSeconds,
    solverStatus: response.solverStatus,
  });

  return {
    assigned: response.assignments.length,
    unassignable: response.unassignable,
    perSalesmanLoadMinutes: response.perSalesmanLoadMinutes,
    targetLoadMinutes: response.targetLoadMinutes,
    runtimeSeconds: response.runtimeSeconds,
    solverStatus: response.solverStatus,
  };
}

export async function generatePlan(
  input: GeneratePlanInput,
  onProgress?: ProgressCallback,
): Promise<GeneratePlanResult> {
  onProgress?.({ phase: 'loading', message: 'Loading customers…', percent: 2 });
  const allCustomers = listGeocodedCustomersForDataset(input.datasetId);
  const skipped: Array<{ customerId: number; reason: string }> = [];
  const rawCustomers = allCustomers.filter((c) => {
    if (c.lat === null || c.lng === null) {
      skipped.push({ customerId: c.id, reason: 'missing coordinates' });
      return false;
    }
    return true;
  });
  if (rawCustomers.length === 0) {
    throw new Error('no geocoded customers in active dataset');
  }
  const salesmen = listSalesmen();
  if (salesmen.length === 0) {
    throw new Error('no salesmen configured');
  }

  // Pre-clustering was removed 2026-05-18 in favor of letting the VRP solver
  // pick salesman + day jointly (it respects area / channel eligibility and
  // load-balances across the full problem). Phase 7a then introduced upfront
  // assignment via `/assign-salesmen` for relationship stickiness — see
  // [planner.ts effectivePinnedSalesmanId]. User pins from the import column
  // are still honored as hard constraints.
  const customers = rawCustomers;

  const spec = buildMatrixSpec(
    customers,
    salesmen.filter((s) => s.includeCommute),
  );
  const groupCount = spec.fullGraphs.length + (spec.knnPairs.length > 0 ? 1 : 0);
  onProgress?.({
    phase: 'matrix',
    message: `Fetching road distances (${groupCount} group${groupCount === 1 ? '' : 's'})…`,
    percent: 10,
  });
  // Matrix fetch spans 10% → 75% of the bar; remaining 25% goes to optimize+save.
  const matrix = await fetchMatrix(spec, (chunkIndex, totalChunks) => {
    const pct = totalChunks <= 0 ? 75 : 10 + Math.round((chunkIndex / totalChunks) * 65);
    onProgress?.({
      phase: 'matrix',
      message: `Fetching road distances (${chunkIndex + 1} of ${totalChunks})…`,
      percent: pct,
    });
  });

  // Single-pass VRP. No automatic pinning — the solver picks salesman + day
  // jointly for each visit, respecting area eligibility and user-set pins
  // from the `pinned_salesman_name` import column. This gives the best
  // load balance and lowest unassigned count. Consistency across weeks
  // (same salesman per customer) is achieved by the user explicitly pinning
  // relationship-critical accounts in the Excel template — that's a
  // business decision, not a routing one.
  onProgress?.({
    phase: 'optimizing',
    message: 'Optimizing routes (OR-Tools, 4 weeks — up to 8 min/week, so ~32 min total on full datasets)…',
    percent: 78,
  });
  const request: OptimizeRequest = {
    periodStart: input.periodStart,
    periodEnd: input.periodEnd,
    salesmen: salesmen.map(salesmanToOptimize),
    customers: customers.map((c) => ({
      ...customerToOptimize(c),
      pinnedSalesmanId: effectivePinnedSalesmanId(c),
    })),
    matrixCells: matrix.cells,
  };
  const response = await sidecarFetch<OptimizeResponse>('/optimize', request);

  onProgress?.({ phase: 'saving', message: 'Saving plan…', percent: 95 });
  const facetimeByCustomer = new Map(customers.map((c) => [c.id, c.facetimeMinutes]));
  const log: SolverLog = {
    objectiveValue: response.objectiveValue,
    runtimeSeconds: response.runtimeSeconds,
    solverStatus: response.solverStatus,
    unassignedCustomers: response.unassignedCustomers as UnassignedCustomer[],
    skippedCustomers: skipped,
    matrixCellsRequested: matrix.requested,
    matrixCellsCached: matrix.cached,
  };

  // One transaction for the whole save. Piecemeal writes could leave the plan
  // row and roster snapshot committed with no visits behind them (e.g. a
  // duplicate-sequence UNIQUE violation part-way through insertVisitsBulk) —
  // an empty shell plan indistinguishable from a real one in the plan list.
  const planId = getDb().transaction(() => {
    const id = createPlan({
      name: input.name,
      datasetId: input.datasetId,
      periodStart: input.periodStart,
      periodEnd: input.periodEnd,
    });

    // Freeze the roster into the plan. Everything downstream (calendar,
    // analytics, map, export) resolves visits against this snapshot, so later
    // roster edits/deletes leave this plan untouched.
    snapshotSalesmenForPlan(id, salesmen);

    insertVisitsBulk(
      id,
      response.visits.map((v) => ({
        salesmanId: v.salesmanId,
        customerId: v.customerId,
        scheduledDate: v.scheduledDate,
        scheduledStartTime: v.scheduledStartTime,
        sequence: v.sequence,
        driveMinutesTo: v.driveMinutesTo,
        facetimeMinutes: facetimeByCustomer.get(v.customerId) ?? null,
      })),
    );
    setSolverLog(id, log);
    return id;
  })();

  onProgress?.({ phase: 'done', message: 'Done', percent: 100 });
  return { planId };
}

export interface SuggestTeamSizeInput {
  datasetId: number;
  template: TeamSizeTemplate;
}

export async function suggestTeamSize(
  input: SuggestTeamSizeInput,
): Promise<SuggestTeamSizeResponse> {
  const customers = listGeocodedCustomersForDataset(input.datasetId);
  if (customers.length === 0) {
    throw new Error('no geocoded customers in active dataset');
  }
  const spec = buildMatrixSpec(customers);
  const matrix = await fetchMatrix(spec);

  const request: SuggestTeamSizeRequest = {
    customers: customers.map((c) => ({
      ...customerToOptimize(c),
      pinnedSalesmanId: effectivePinnedSalesmanId(c),
    })),
    matrixCells: matrix.cells,
    template: input.template,
  };
  return sidecarFetch<SuggestTeamSizeResponse>('/optimize/suggest-team-size', request);
}


export interface ReassignVisitInput {
  planId: number;
  visitId: number;
  toSalesmanId: number;
  toDate: string;
}

function dayOfWeek(isoDate: string): number {
  // Parse the YYYY-MM-DD as a local date — `new Date('2026-06-01')` is parsed as
  // UTC midnight and can drift to the previous day in negative-UTC zones.
  // CLAUDE.md convention: Sun=0..Sat=6 (matches JS getDay).
  const [y, m, d] = isoDate.split('-').map(Number);
  return new Date(y!, (m ?? 1) - 1, d ?? 1).getDay();
}

function customerCoversSalesman(c: Customer, s: Salesman): boolean {
  // A user PIN (import column) stays hard. The ALGORITHM's assignment
  // (assigned_salesman_id) deliberately does NOT block a manual move — the
  // drag IS the user overriding the algorithm for this plan. It still feeds
  // the solver as a hard pin at generation time (effectivePinnedSalesmanId).
  if (c.pinnedSalesmanId !== null) return c.pinnedSalesmanId === s.id;
  // Area / region — satisfying either is enough when either constraint is set.
  const hasAreaConstraint = s.assignedAreas.length > 0;
  const hasRegionConstraint = s.assignedRegions.length > 0;
  if (hasAreaConstraint || hasRegionConstraint) {
    const cArea = c.area && c.area !== '' ? c.area : null;
    const cRegion = c.region && c.region !== '' ? c.region : null;
    if (cArea !== null || cRegion !== null) {
      const areaMatch =
        hasAreaConstraint && cArea !== null && s.assignedAreas.includes(cArea);
      const regionMatch =
        hasRegionConstraint && cRegion !== null && s.assignedRegions.includes(cRegion);
      if (!areaMatch && !regionMatch) return false;
    }
  }
  if (s.channelSkills.length > 0 && c.channel !== null && !s.channelSkills.includes(c.channel)) {
    return false;
  }
  return true;
}

export async function reassignVisit(input: ReassignVisitInput): Promise<void> {
  const visit = getVisit(input.visitId);
  if (!visit) throw new Error(`visit ${input.visitId} not found`);
  if (visit.journeyPlanId !== input.planId) {
    throw new Error('visit does not belong to this plan');
  }

  const customer = getCustomer(visit.customerId);
  if (!customer) throw new Error('customer not found');
  // Salesmen are plan-scoped: resolve against the plan's snapshot first. A
  // drag to a salesman who joined the roster AFTER generation falls back to
  // the live roster and grows the snapshot, keeping the plan self-contained.
  const snapshotSalesman = getPlanSalesman(input.planId, input.toSalesmanId);
  const destSalesman = snapshotSalesman ?? getSalesman(input.toSalesmanId);
  if (!destSalesman) throw new Error('destination salesman not found');
  const commit = (rewrites: DayRewrite[]): void => {
    getDb().transaction(() => {
      if (!snapshotSalesman) addSalesmanToPlanSnapshot(input.planId, destSalesman);
      for (const r of rewrites) {
        replaceVisitsForDay(input.planId, r.salesmanId, r.date, r.visits);
      }
    })();
  };

  // Validate the move against domain constraints. Surfaced verbatim in the
  // renderer toast so the user knows why the drop was rejected.
  if (!customerCoversSalesman(customer, destSalesman)) {
    throw new Error(
      customer.pinnedSalesmanId !== null
        ? `${customer.name} is pinned to another salesman`
        : `${customer.name} (area ${customer.area ?? '—'}, channel ${customer.channel ?? '—'}) is not covered by ${destSalesman.name}`,
    );
  }
  const destDow = dayOfWeek(input.toDate);
  if (!destSalesman.workingDays.includes(destDow)) {
    throw new Error(`${destSalesman.name} does not work on that day`);
  }
  if (customer.allowedDays !== null && !customer.allowedDays.includes(destDow)) {
    throw new Error(`${customer.name} is not allowed on that weekday`);
  }

  if (visit.salesmanId === input.toSalesmanId) {
    // Day move within the same salesman — a single-visit schedule touch-up.
    if (visit.scheduledDate === input.toDate) return;
    const destCustomers = dayCustomersWith(
      input.planId,
      input.toSalesmanId,
      input.toDate,
      customer.id,
    );
    const sourceCustomers = listVisitsForSalesmanDay(
      input.planId,
      visit.salesmanId,
      visit.scheduledDate,
    )
      .filter((v) => v.id !== input.visitId)
      .map((v) => getCustomer(v.customerId))
      .filter((c): c is Customer => c !== null && c.lat !== null && c.lng !== null);
    commit([
      await resequenceDay(destSalesman, input.toDate, destCustomers),
      await resequenceDay(destSalesman, visit.scheduledDate, sourceCustomers),
    ]);
    return;
  }

  // Cross-salesman drag = relationship handover (user decision 2026-06-11):
  // ALL of this customer's visits in the plan move to the destination
  // salesman. Siblings keep their original days; the dragged visit lands on
  // the drop date. assigned_salesman_id is deliberately NOT touched — future
  // generations still follow the Assignments screen.
  const movingVisits = listVisitsForCustomerInPlan(input.planId, customer.id).filter(
    (v) => v.salesmanId !== input.toSalesmanId,
  );
  const targetDateFor = (v: Visit): string =>
    v.id === input.visitId ? input.toDate : v.scheduledDate;
  for (const v of movingVisits) {
    const d = targetDateFor(v);
    if (!destSalesman.workingDays.includes(dayOfWeek(d))) {
      throw new Error(
        `${destSalesman.name} does not work on ${d}, but ${customer.name} has a visit that day — handover cancelled, no visits were moved.`,
      );
    }
  }

  // Every day is solved before anything is written, then all days commit in
  // one transaction: an OSRM/sidecar failure mid-handover leaves the plan
  // exactly as it was. The awaits must stay outside the transaction —
  // better-sqlite3 transactions are synchronous.
  const rewrites: DayRewrite[] = [];
  for (const date of new Set(movingVisits.map(targetDateFor))) {
    const destCustomers = dayCustomersWith(
      input.planId,
      input.toSalesmanId,
      date,
      customer.id,
    );
    rewrites.push(await resequenceDay(destSalesman, date, destCustomers));
  }

  const movedIds = new Set(movingVisits.map((v) => v.id));
  const sourceDays = new Map<string, { salesmanId: number; date: string }>();
  for (const v of movingVisits) {
    sourceDays.set(`${v.salesmanId}|${v.scheduledDate}`, {
      salesmanId: v.salesmanId,
      date: v.scheduledDate,
    });
  }
  for (const { salesmanId, date } of sourceDays.values()) {
    const remaining = listVisitsForSalesmanDay(input.planId, salesmanId, date)
      .filter((v) => !movedIds.has(v.id))
      .map((v) => getCustomer(v.customerId))
      .filter((c): c is Customer => c !== null && c.lat !== null && c.lng !== null);
    const sourceSalesman =
      getPlanSalesman(input.planId, salesmanId) ?? getSalesman(salesmanId);
    if (!sourceSalesman) throw new Error('source salesman not found');
    rewrites.push(await resequenceDay(sourceSalesman, date, remaining));
  }
  commit(rewrites);
}

// Existing customers on (salesman, date) plus one more — the day set fed to
// the re-sequencer. Records come via getCustomer so the matrix and solver get
// lat/lng + facetime.
function dayCustomersWith(
  planId: number,
  salesmanId: number,
  date: string,
  extraCustomerId: number,
): Customer[] {
  const ids = new Set(
    listVisitsForSalesmanDay(planId, salesmanId, date).map((v) => v.customerId),
  );
  ids.add(extraCustomerId);
  return Array.from(ids)
    .map((id) => getCustomer(id))
    .filter((c): c is Customer => c !== null && c.lat !== null && c.lng !== null);
}

interface DayRewrite {
  salesmanId: number;
  date: string;
  visits: VisitInsert[];
}

// Solves the day's sequence but writes nothing — reassignVisit commits all
// affected days together so a failed solve can't leave a half-applied move.
async function resequenceDay(
  salesman: Salesman,
  date: string,
  customers: Customer[],
): Promise<DayRewrite> {
  if (customers.length === 0) {
    return { salesmanId: salesman.id, date, visits: [] };
  }

  // Day-resequence touches one salesman's day's customer set. Treat the whole
  // set as a single full-graph group so OSRM computes one self-matrix call
  // covering exactly the pairs the day-solver needs. A commute-enabled
  // salesman's start point joins the group so the depot legs get real times.
  const nodes = customers.map(toMatrixNode);
  if (salesman.includeCommute) nodes.push(salesmanStartNode(salesman));
  const matrix = await fetchMatrix({ fullGraphs: [nodes], knnPairs: [] });

  const request: ResequenceDayRequest = {
    salesman: salesmanToOptimize(salesman),
    // Day membership is the CALLER's validated decision (reassignVisit checks
    // pin/area/channel/working-day). Pin every member to this day's salesman
    // so the sidecar's eligibility pre-screen (pin > area/channel) can never
    // silently drop a manually-moved customer — the day-solver only sequences.
    customers: customers.map((c) => ({
      ...customerToOptimize(c),
      pinnedSalesmanId: salesman.id,
    })),
    matrixCells: matrix.cells,
    date,
  };
  const response = await sidecarFetch<ResequenceDayResponse>(
    '/optimize/resequence-day',
    request,
  );

  const facetimeById = new Map(customers.map((c) => [c.id, c.facetimeMinutes]));
  return {
    salesmanId: salesman.id,
    date,
    visits: response.visits.map((v) => ({
      salesmanId: v.salesmanId,
      customerId: v.customerId,
      scheduledDate: v.scheduledDate,
      scheduledStartTime: v.scheduledStartTime,
      sequence: v.sequence,
      driveMinutesTo: v.driveMinutesTo,
      facetimeMinutes: facetimeById.get(v.customerId) ?? null,
    })),
  };
}

// Sole polyline source after Google Directions was removed 2026-05-22. Hits
// the local OSRM /route endpoint via the sidecar — no caching (OSRM is local
// and fast enough that a 1-hit cache adds complexity for ~50 ms savings).
export async function osrmRouteForVisits(
  waypoints: MatrixPoint[],
): Promise<OsrmRouteResponse> {
  if (waypoints.length < 2) {
    throw new Error('at least 2 waypoints required for /route/osrm');
  }
  return sidecarFetch<OsrmRouteResponse>('/route/osrm', { waypoints });
}
