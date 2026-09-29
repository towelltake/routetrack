import type { Customer, SalesChannel } from '@journey/shared';
import { getDb } from '../db';

interface CustomerRow {
  id: number;
  dataset_id: number | null;
  external_code: string;
  name: string;
  address: string | null;
  lat: number | null;
  lng: number | null;
  facetime_minutes: number;
  monthly_frequency: number;
  allowed_days_csv: string | null;
  pinned_salesman_id: number | null;
  assigned_salesman_id: number | null;
  area: string | null;
  region: string | null;
  channel: string | null;
  visit_window_start: string | null;
  visit_window_end: string | null;
}

function coerceChannel(raw: string | null): SalesChannel | null {
  if (raw === null) return null;
  const v = raw.trim().toUpperCase();
  return v === 'MT' || v === 'TT' || v === 'WS' ? v : null;
}

function parseDaysCsv(csv: string | null): number[] | null {
  if (csv === null || csv === '') return null;
  return csv.split(',').map((s) => Number(s.trim())).filter((n) => Number.isFinite(n));
}

function rowToCustomer(row: CustomerRow): Customer {
  return {
    id: row.id,
    datasetId: row.dataset_id ?? 0,
    externalCode: row.external_code,
    name: row.name,
    address: row.address,
    lat: row.lat,
    lng: row.lng,
    facetimeMinutes: row.facetime_minutes,
    monthlyFrequency: row.monthly_frequency,
    allowedDays: parseDaysCsv(row.allowed_days_csv),
    pinnedSalesmanId: row.pinned_salesman_id,
    assignedSalesmanId: row.assigned_salesman_id,
    area: row.area,
    region: row.region,
    channel: coerceChannel(row.channel),
    visitWindowStart: row.visit_window_start,
    visitWindowEnd: row.visit_window_end,
  };
}

export interface ListCustomersOptions {
  datasetId: number;
  search?: string;
  limit?: number;
  offset?: number;
}

export function listCustomers(opts: ListCustomersOptions): Customer[] {
  const where: string[] = ['dataset_id = ?'];
  const params: (string | number)[] = [opts.datasetId];
  if (opts.search) {
    where.push('(LOWER(name) LIKE ? OR LOWER(external_code) LIKE ?)');
    const needle = `%${opts.search.toLowerCase()}%`;
    params.push(needle, needle);
  }
  const limit = opts.limit ?? 5000;
  const offset = opts.offset ?? 0;
  const sql = `SELECT * FROM customers WHERE ${where.join(' AND ')} ORDER BY name LIMIT ? OFFSET ?`;
  const rows = getDb().prepare(sql).all(...params, limit, offset) as CustomerRow[];
  return rows.map(rowToCustomer);
}

export function getCustomer(id: number): Customer | null {
  const row = getDb()
    .prepare('SELECT * FROM customers WHERE id = ?')
    .get(id) as CustomerRow | undefined;
  return row ? rowToCustomer(row) : null;
}

export interface CustomerWrite {
  id?: number;
  datasetId: number;
  externalCode: string;
  name: string;
  address: string | null;
  lat: number | null;
  lng: number | null;
  facetimeMinutes: number;
  monthlyFrequency: number;
  allowedDays: number[] | null;
  pinnedSalesmanId: number | null;
  area: string | null;
  region: string | null;
  channel: SalesChannel | null;
  visitWindowStart: string | null;
  visitWindowEnd: string | null;
}

export function upsertCustomer(c: CustomerWrite): number {
  const db = getDb();
  const daysCsv = c.allowedDays ? c.allowedDays.join(',') : null;
  if (c.id) {
    db.prepare(
      `UPDATE customers SET
        external_code = ?, name = ?, address = ?, lat = ?, lng = ?,
        facetime_minutes = ?, monthly_frequency = ?, allowed_days_csv = ?,
        pinned_salesman_id = ?, area = ?, region = ?, channel = ?,
        visit_window_start = ?, visit_window_end = ?,
        updated_at = CURRENT_TIMESTAMP
       WHERE id = ?`,
    ).run(
      c.externalCode,
      c.name,
      c.address,
      c.lat,
      c.lng,
      c.facetimeMinutes,
      c.monthlyFrequency,
      daysCsv,
      c.pinnedSalesmanId,
      c.area,
      c.region,
      c.channel,
      c.visitWindowStart,
      c.visitWindowEnd,
      c.id,
    );
    return c.id;
  }
  const result = db
    .prepare(
      `INSERT INTO customers (
        dataset_id, external_code, name, address, lat, lng,
        facetime_minutes, monthly_frequency, allowed_days_csv,
        pinned_salesman_id, area, region, channel,
        visit_window_start, visit_window_end
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    )
    .run(
      c.datasetId,
      c.externalCode,
      c.name,
      c.address,
      c.lat,
      c.lng,
      c.facetimeMinutes,
      c.monthlyFrequency,
      daysCsv,
      c.pinnedSalesmanId,
      c.area,
      c.region,
      c.channel,
      c.visitWindowStart,
      c.visitWindowEnd,
    );
  return Number(result.lastInsertRowid);
}

export function distinctAreasForDataset(datasetId: number): string[] {
  const rows = getDb()
    .prepare(
      `SELECT DISTINCT area FROM customers
       WHERE dataset_id = ? AND area IS NOT NULL AND TRIM(area) != ''
       ORDER BY area`,
    )
    .all(datasetId) as Array<{ area: string }>;
  return rows.map((r) => r.area);
}

export function distinctRegionsForDataset(datasetId: number): string[] {
  const rows = getDb()
    .prepare(
      `SELECT DISTINCT region FROM customers
       WHERE dataset_id = ? AND region IS NOT NULL AND TRIM(region) != ''
       ORDER BY region`,
    )
    .all(datasetId) as Array<{ region: string }>;
  return rows.map((r) => r.region);
}

export function listGeocodedCustomersForDataset(datasetId: number): Customer[] {
  // Name kept for caller stability; the "geocoded" qualifier is now a lat+lng
  // presence check since geocoding was removed Phase 10 and migration 0010
  // dropped geocode_status. Importer enforces lat+lng required on every row,
  // so this filter is mostly belt-and-braces.
  const rows = getDb()
    .prepare(
      `SELECT * FROM customers
       WHERE dataset_id = ? AND lat IS NOT NULL AND lng IS NOT NULL`,
    )
    .all(datasetId) as CustomerRow[];
  return rows.map(rowToCustomer);
}

export function deleteCustomer(id: number): void {
  // visits.customer_id is ON DELETE CASCADE (migration 0013), so deleting a
  // customer silently rewrites EVERY plan that ever visited it — including
  // locked 'final' ones, which would lose a stop, keep a stale
  // drive_minutes_to on the following visit, and show a sequence gap in the
  // Excel export. Plan deletion already refuses final plans; match that here.
  const finalPlans = getDb()
    .prepare(
      `SELECT DISTINCT p.name
         FROM visits v
         JOIN journey_plans p ON p.id = v.journey_plan_id
        WHERE v.customer_id = ? AND p.status = 'final'
        ORDER BY p.name`,
    )
    .all(id) as { name: string }[];
  if (finalPlans.length > 0) {
    const names = finalPlans.map((p) => p.name).join(', ');
    throw new Error(
      `This customer is scheduled in ${finalPlans.length} final plan${finalPlans.length === 1 ? '' : 's'} (${names}). ` +
        'Unlock those plans first if you really want to remove it from them.',
    );
  }
  getDb().prepare('DELETE FROM customers WHERE id = ?').run(id);
}

// ---- Phase 7a: upfront salesman assignment ----

export interface AssignmentApply {
  customerId: number;
  salesmanId: number;
}

// Apply a batch of (customer, salesman) algorithm assignments inside a single
// transaction. Writes to assigned_salesman_id (the algorithm column), not
// pinned_salesman_id (the user-override column). Returns the number of rows
// updated.
export function bulkApplyAssignments(rows: AssignmentApply[]): number {
  if (rows.length === 0) return 0;
  const db = getDb();
  const stmt = db.prepare(
    `UPDATE customers
        SET assigned_salesman_id = ?, updated_at = CURRENT_TIMESTAMP
      WHERE id = ?`,
  );
  let updated = 0;
  const tx = db.transaction((batch: AssignmentApply[]) => {
    for (const r of batch) {
      const result = stmt.run(r.salesmanId, r.customerId);
      updated += Number(result.changes);
    }
  });
  tx(rows);
  return updated;
}

// Wipe algorithm-computed assignments for a dataset (e.g. before a fresh
// compute). User overrides on pinned_salesman_id are untouched.
export function clearAssignmentsForDataset(datasetId: number): number {
  const result = getDb()
    .prepare(
      `UPDATE customers
          SET assigned_salesman_id = NULL, updated_at = CURRENT_TIMESTAMP
        WHERE dataset_id = ?`,
    )
    .run(datasetId);
  return Number(result.changes);
}

// User-facing override from the Assignments screen. Writes to pinned_salesman_id
// directly — the existing user-override column. Passing null clears the pin.
export function setUserAssignmentOverride(
  customerId: number,
  salesmanId: number | null,
): void {
  getDb()
    .prepare(
      `UPDATE customers
          SET pinned_salesman_id = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?`,
    )
    .run(salesmanId, customerId);
}

// Wipe every pin in a dataset. Used by the "Clear all overrides" button.
// IMPORTANT: this also clears pins set at import time — see plan §"Risks
// acknowledged". Returns the number of customers affected.
export function clearAllOverridesForDataset(datasetId: number): number {
  const result = getDb()
    .prepare(
      `UPDATE customers
          SET pinned_salesman_id = NULL, updated_at = CURRENT_TIMESTAMP
        WHERE dataset_id = ? AND pinned_salesman_id IS NOT NULL`,
    )
    .run(datasetId);
  return Number(result.changes);
}

// Returns the most recent updated_at among customers in the dataset. Used by
// the staleness check to detect whether source data has changed since the
// last assignment run.
export function maxCustomerUpdatedAt(datasetId: number): string | null {
  const row = getDb()
    .prepare(
      `SELECT MAX(updated_at) AS max_updated_at FROM customers WHERE dataset_id = ?`,
    )
    .get(datasetId) as { max_updated_at: string | null } | undefined;
  return row?.max_updated_at ?? null;
}

// Count customers in the dataset whose updated_at is strictly after the given
// ISO timestamp. Used by the staleness banner to report "X customers changed".
export function countCustomersUpdatedSince(datasetId: number, since: string): number {
  const row = getDb()
    .prepare(
      `SELECT COUNT(*) AS n FROM customers
        WHERE dataset_id = ? AND updated_at > ?`,
    )
    .get(datasetId, since) as { n: number };
  return row.n;
}
