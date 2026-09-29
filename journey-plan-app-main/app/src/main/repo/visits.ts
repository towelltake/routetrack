import type { Visit } from '@journey/shared';
import { getDb } from '../db';

interface VisitRow {
  id: number;
  journey_plan_id: number;
  salesman_id: number;
  customer_id: number;
  scheduled_date: string;
  scheduled_start_time: string;
  sequence: number;
  drive_minutes_to: number | null;
  facetime_minutes: number | null;
}

function rowToVisit(row: VisitRow): Visit {
  return {
    id: row.id,
    journeyPlanId: row.journey_plan_id,
    salesmanId: row.salesman_id,
    customerId: row.customer_id,
    scheduledDate: row.scheduled_date,
    scheduledStartTime: row.scheduled_start_time,
    sequence: row.sequence,
    driveMinutesTo: row.drive_minutes_to,
    facetimeMinutes: row.facetime_minutes,
  };
}

export interface VisitInsert {
  salesmanId: number;
  customerId: number;
  scheduledDate: string;
  scheduledStartTime: string;
  sequence: number;
  driveMinutesTo: number | null;
  facetimeMinutes: number | null;
}

export function insertVisitsBulk(planId: number, visits: VisitInsert[]): void {
  const db = getDb();
  const stmt = db.prepare(
    `INSERT INTO visits (
      journey_plan_id, salesman_id, customer_id, scheduled_date, scheduled_start_time,
      sequence, drive_minutes_to, facetime_minutes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
  );
  const tx = db.transaction((vs: VisitInsert[]) => {
    for (const v of vs) {
      stmt.run(
        planId,
        v.salesmanId,
        v.customerId,
        v.scheduledDate,
        v.scheduledStartTime,
        v.sequence,
        v.driveMinutesTo,
        v.facetimeMinutes,
      );
    }
  });
  tx(visits);
}

export function listVisitsForPlan(planId: number): Visit[] {
  const rows = getDb()
    .prepare(
      `SELECT * FROM visits WHERE journey_plan_id = ?
       ORDER BY salesman_id, scheduled_date, sequence`,
    )
    .all(planId) as VisitRow[];
  return rows.map(rowToVisit);
}

export function listVisitsForSalesmanDay(
  planId: number,
  salesmanId: number,
  date: string,
): Visit[] {
  const rows = getDb()
    .prepare(
      `SELECT * FROM visits
       WHERE journey_plan_id = ? AND salesman_id = ? AND scheduled_date = ?
       ORDER BY sequence`,
    )
    .all(planId, salesmanId, date) as VisitRow[];
  return rows.map(rowToVisit);
}

export function listVisitsForCustomerInPlan(planId: number, customerId: number): Visit[] {
  const rows = getDb()
    .prepare(
      `SELECT * FROM visits WHERE journey_plan_id = ? AND customer_id = ?
       ORDER BY scheduled_date, sequence`,
    )
    .all(planId, customerId) as VisitRow[];
  return rows.map(rowToVisit);
}

export function getVisit(id: number): Visit | null {
  const row = getDb()
    .prepare('SELECT * FROM visits WHERE id = ?')
    .get(id) as VisitRow | undefined;
  return row ? rowToVisit(row) : null;
}

export function deleteVisitsForDay(
  planId: number,
  salesmanId: number,
  date: string,
): void {
  getDb()
    .prepare(
      `DELETE FROM visits
       WHERE journey_plan_id = ? AND salesman_id = ? AND scheduled_date = ?`,
    )
    .run(planId, salesmanId, date);
}

export function deleteVisit(id: number): void {
  getDb().prepare('DELETE FROM visits WHERE id = ?').run(id);
}

export function replaceVisitsForDay(
  planId: number,
  salesmanId: number,
  date: string,
  visits: VisitInsert[],
): void {
  const db = getDb();
  const del = db.prepare(
    `DELETE FROM visits
     WHERE journey_plan_id = ? AND salesman_id = ? AND scheduled_date = ?`,
  );
  const ins = db.prepare(
    `INSERT INTO visits (
      journey_plan_id, salesman_id, customer_id, scheduled_date, scheduled_start_time,
      sequence, drive_minutes_to, facetime_minutes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
  );
  const tx = db.transaction((vs: VisitInsert[]) => {
    del.run(planId, salesmanId, date);
    for (const v of vs) {
      ins.run(
        planId,
        v.salesmanId,
        v.customerId,
        v.scheduledDate,
        v.scheduledStartTime,
        v.sequence,
        v.driveMinutesTo,
        v.facetimeMinutes,
      );
    }
  });
  tx(visits);
}
