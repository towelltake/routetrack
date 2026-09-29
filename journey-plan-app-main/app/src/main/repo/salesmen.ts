import type { Salesman, SalesChannel } from '@journey/shared';
import { getDb } from '../db';

export interface SalesmanRow {
  id: number;
  name: string;
  start_location_lat: number;
  start_location_lng: number;
  working_days_csv: string;
  working_hours_start: string;
  working_hours_end: string;
  assigned_areas_csv: string;
  assigned_regions_csv: string;
  channel_skills_csv: string;
  include_commute: number;
}

function parseCsvStrings(csv: string): string[] {
  if (!csv) return [];
  return csv
    .split(',')
    .map((s) => s.trim())
    .filter((s) => s !== '');
}

function parseChannelSkills(csv: string): SalesChannel[] {
  const out: SalesChannel[] = [];
  for (const s of parseCsvStrings(csv)) {
    const v = s.toUpperCase();
    if (v === 'MT' || v === 'TT' || v === 'WS') out.push(v);
  }
  return out;
}

export function rowToSalesman(row: SalesmanRow): Salesman {
  return {
    id: row.id,
    name: row.name,
    startLocationLat: row.start_location_lat,
    startLocationLng: row.start_location_lng,
    workingDays: row.working_days_csv
      .split(',')
      .map((s) => Number(s.trim()))
      .filter((n) => Number.isFinite(n)),
    workingHoursStart: row.working_hours_start,
    workingHoursEnd: row.working_hours_end,
    assignedAreas: parseCsvStrings(row.assigned_areas_csv),
    assignedRegions: parseCsvStrings(row.assigned_regions_csv ?? ''),
    channelSkills: parseChannelSkills(row.channel_skills_csv ?? ''),
    includeCommute: row.include_commute === 1,
  };
}

export function listSalesmen(): Salesman[] {
  const rows = getDb().prepare('SELECT * FROM salesmen ORDER BY name').all() as SalesmanRow[];
  return rows.map(rowToSalesman);
}

export function getSalesman(id: number): Salesman | null {
  const row = getDb()
    .prepare('SELECT * FROM salesmen WHERE id = ?')
    .get(id) as SalesmanRow | undefined;
  return row ? rowToSalesman(row) : null;
}

export interface SalesmanWrite {
  id?: number;
  name: string;
  startLocationLat: number;
  startLocationLng: number;
  workingDays: number[];
  workingHoursStart: string;
  workingHoursEnd: string;
  assignedAreas: string[];
  assignedRegions: string[];
  channelSkills: SalesChannel[];
  includeCommute: boolean;
}

export function upsertSalesman(s: SalesmanWrite): number {
  const db = getDb();
  const daysCsv = s.workingDays.join(',');
  const areasCsv = s.assignedAreas.join(',');
  const regionsCsv = s.assignedRegions.join(',');
  const skillsCsv = s.channelSkills.join(',');
  const commute = s.includeCommute ? 1 : 0;
  if (s.id) {
    db.prepare(
      `UPDATE salesmen SET
        name = ?, start_location_lat = ?, start_location_lng = ?,
        working_days_csv = ?, working_hours_start = ?, working_hours_end = ?,
        assigned_areas_csv = ?, assigned_regions_csv = ?, channel_skills_csv = ?,
        include_commute = ?,
        updated_at = CURRENT_TIMESTAMP
       WHERE id = ?`,
    ).run(
      s.name,
      s.startLocationLat,
      s.startLocationLng,
      daysCsv,
      s.workingHoursStart,
      s.workingHoursEnd,
      areasCsv,
      regionsCsv,
      skillsCsv,
      commute,
      s.id,
    );
    return s.id;
  }
  const result = db
    .prepare(
      `INSERT INTO salesmen (
        name, start_location_lat, start_location_lng,
        working_days_csv, working_hours_start, working_hours_end,
        assigned_areas_csv, assigned_regions_csv, channel_skills_csv,
        include_commute
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    )
    .run(
      s.name,
      s.startLocationLat,
      s.startLocationLng,
      daysCsv,
      s.workingHoursStart,
      s.workingHoursEnd,
      areasCsv,
      regionsCsv,
      skillsCsv,
      commute,
    );
  return Number(result.lastInsertRowid);
}

// Since migration 0013, plans snapshot their salesmen and visits no longer
// reference the roster, so deletes here never touch existing plans.
export function deleteSalesman(id: number): void {
  getDb().prepare('DELETE FROM salesmen WHERE id = ?').run(id);
}

// Bulk-delete every salesman row. Existing plans keep their visits and their
// plan_salesmen snapshots (migration 0013). Cascades:
//   - `customers.pinned_salesman_id` is ON DELETE SET NULL: user pins clear.
//   - `customers.assigned_salesman_id` is ON DELETE SET NULL (migration 0007):
//     algorithm assignments also clear. The Assignments screen will then show
//     every customer as unassigned until Compute Assignments is re-run.
// Customer rows themselves are untouched. Returns the count of salesmen removed.
export function clearAllSalesmen(): number {
  const result = getDb().prepare('DELETE FROM salesmen').run();
  return Number(result.changes);
}

export function getSalesmanByName(name: string): Salesman | null {
  const row = getDb()
    .prepare('SELECT * FROM salesmen WHERE LOWER(name) = LOWER(?)')
    .get(name) as SalesmanRow | undefined;
  return row ? rowToSalesman(row) : null;
}
