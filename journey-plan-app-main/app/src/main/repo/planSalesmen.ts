import type { Statement } from 'better-sqlite3';
import type { Salesman } from '@journey/shared';
import { getDb } from '../db';
import { rowToSalesman, type SalesmanRow } from './salesmen';

// Plan-scoped salesman snapshots (migration 0013). Each plan freezes the
// roster it was generated with, so later roster edits/deletes never change
// what an existing plan shows. `salesman_id` here is the roster id at
// snapshot time and is what visits.salesman_id resolves against.

type PlanSalesmanRow = Omit<SalesmanRow, 'id'> & {
  journey_plan_id: number;
  salesman_id: number;
};

function rowToPlanSalesman(row: PlanSalesmanRow): Salesman {
  return rowToSalesman({ ...row, id: row.salesman_id });
}

const INSERT_SQL = `INSERT OR REPLACE INTO plan_salesmen (
  journey_plan_id, salesman_id, name, start_location_lat, start_location_lng,
  working_days_csv, working_hours_start, working_hours_end,
  assigned_areas_csv, assigned_regions_csv, channel_skills_csv, include_commute
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`;

function runInsert(stmt: Statement, planId: number, s: Salesman): void {
  stmt.run(
    planId,
    s.id,
    s.name,
    s.startLocationLat,
    s.startLocationLng,
    s.workingDays.join(','),
    s.workingHoursStart,
    s.workingHoursEnd,
    s.assignedAreas.join(','),
    s.assignedRegions.join(','),
    s.channelSkills.join(','),
    s.includeCommute ? 1 : 0,
  );
}

export function snapshotSalesmenForPlan(planId: number, salesmen: Salesman[]): void {
  const db = getDb();
  const stmt = db.prepare(INSERT_SQL);
  const tx = db.transaction((list: Salesman[]) => {
    for (const s of list) runInsert(stmt, planId, s);
  });
  tx(salesmen);
}

// Used when a visit is dragged to a salesman who joined the roster after the
// plan was generated — the plan's snapshot grows to include them so the plan
// stays self-contained.
export function addSalesmanToPlanSnapshot(planId: number, s: Salesman): void {
  runInsert(getDb().prepare(INSERT_SQL), planId, s);
}

export function listPlanSalesmen(planId: number): Salesman[] {
  const rows = getDb()
    .prepare('SELECT * FROM plan_salesmen WHERE journey_plan_id = ? ORDER BY name')
    .all(planId) as PlanSalesmanRow[];
  return rows.map(rowToPlanSalesman);
}

export function getPlanSalesman(planId: number, salesmanId: number): Salesman | null {
  const row = getDb()
    .prepare('SELECT * FROM plan_salesmen WHERE journey_plan_id = ? AND salesman_id = ?')
    .get(planId, salesmanId) as PlanSalesmanRow | undefined;
  return row ? rowToPlanSalesman(row) : null;
}
