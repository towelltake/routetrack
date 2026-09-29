import type { JourneyPlan, PlanStatus, SolverLog } from '@journey/shared';
import { getDb } from '../db';

interface JourneyPlanRow {
  id: number;
  name: string;
  dataset_id: number | null;
  period_start: string;
  period_end: string;
  status: PlanStatus;
  solver_log: string | null;
  created_at: string;
}

function rowToPlan(row: JourneyPlanRow): JourneyPlan {
  return {
    id: row.id,
    name: row.name,
    datasetId: row.dataset_id,
    periodStart: row.period_start,
    periodEnd: row.period_end,
    status: row.status,
    createdAt: row.created_at,
  };
}

export interface CreatePlanInput {
  name: string;
  datasetId: number;
  periodStart: string;
  periodEnd: string;
}

export function createPlan(input: CreatePlanInput): number {
  const result = getDb()
    .prepare(
      `INSERT INTO journey_plans (name, dataset_id, period_start, period_end, status)
       VALUES (?, ?, ?, ?, 'draft')`,
    )
    .run(input.name, input.datasetId, input.periodStart, input.periodEnd);
  return Number(result.lastInsertRowid);
}

export function listPlansForDataset(datasetId: number): JourneyPlan[] {
  const rows = getDb()
    .prepare(
      `SELECT * FROM journey_plans
       WHERE dataset_id = ?
       ORDER BY created_at DESC`,
    )
    .all(datasetId) as JourneyPlanRow[];
  return rows.map(rowToPlan);
}

export function getPlan(id: number): JourneyPlan | null {
  const row = getDb()
    .prepare('SELECT * FROM journey_plans WHERE id = ?')
    .get(id) as JourneyPlanRow | undefined;
  return row ? rowToPlan(row) : null;
}

export function getSolverLog(id: number): SolverLog | null {
  const row = getDb()
    .prepare('SELECT solver_log FROM journey_plans WHERE id = ?')
    .get(id) as { solver_log: string | null } | undefined;
  if (!row?.solver_log) return null;
  return JSON.parse(row.solver_log) as SolverLog;
}

export function setSolverLog(id: number, log: SolverLog): void {
  getDb()
    .prepare('UPDATE journey_plans SET solver_log = ? WHERE id = ?')
    .run(JSON.stringify(log), id);
}

export function setPlanStatus(id: number, status: PlanStatus): void {
  getDb().prepare('UPDATE journey_plans SET status = ? WHERE id = ?').run(status, id);
}

export function deletePlan(id: number): void {
  getDb().prepare('DELETE FROM journey_plans WHERE id = ?').run(id);
}
