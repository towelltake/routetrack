import type { AssignmentRun, AssignmentStaleness, SolverStatus } from '@journey/shared';
import { getDb } from '../db';
import { countCustomersUpdatedSince, maxCustomerUpdatedAt } from './customers';

interface AssignmentRunRow {
  dataset_id: number;
  computed_at: string;
  customer_count: number;
  salesman_count: number;
  unassignable_count: number;
  runtime_seconds: number;
  solver_status: string;
}

function rowToRun(row: AssignmentRunRow): AssignmentRun {
  return {
    datasetId: row.dataset_id,
    computedAt: row.computed_at,
    customerCount: row.customer_count,
    salesmanCount: row.salesman_count,
    unassignableCount: row.unassignable_count,
    runtimeSeconds: row.runtime_seconds,
    solverStatus: row.solver_status as SolverStatus,
  };
}

export function getLatestRun(datasetId: number): AssignmentRun | null {
  const row = getDb()
    .prepare('SELECT * FROM customer_assignment_runs WHERE dataset_id = ?')
    .get(datasetId) as AssignmentRunRow | undefined;
  return row ? rowToRun(row) : null;
}

export interface RecordRunInput {
  datasetId: number;
  customerCount: number;
  salesmanCount: number;
  unassignableCount: number;
  runtimeSeconds: number;
  solverStatus: SolverStatus;
}

export function recordRun(input: RecordRunInput): void {
  // INSERT OR REPLACE since the table is keyed by dataset_id and we only
  // keep the latest run per dataset.
  getDb()
    .prepare(
      `INSERT OR REPLACE INTO customer_assignment_runs (
        dataset_id, computed_at, customer_count, salesman_count,
        unassignable_count, runtime_seconds, solver_status
      ) VALUES (?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?)`,
    )
    .run(
      input.datasetId,
      input.customerCount,
      input.salesmanCount,
      input.unassignableCount,
      input.runtimeSeconds,
      input.solverStatus,
    );
}

// Compares last-compute time against customer + salesman max(updated_at). The
// banner only ever needs "did anything change since last compute"; we deliberately
// don't try to attribute the staleness reason to specific row deltas — too noisy.
export function getStaleness(datasetId: number): AssignmentStaleness {
  const latest = getLatestRun(datasetId);
  if (!latest) {
    return { stale: true, reason: 'No assignments computed yet for this dataset.' };
  }
  const computedAt = latest.computedAt;
  const newCustomers = countCustomersUpdatedSince(datasetId, computedAt);
  const maxCustomer = maxCustomerUpdatedAt(datasetId);
  // Salesman edits are dataset-agnostic — we check the latest salesman update
  // against the run timestamp directly.
  const salesmanRow = getDb()
    .prepare('SELECT MAX(updated_at) AS m FROM salesmen')
    .get() as { m: string | null };
  const salesmanChanged =
    salesmanRow.m !== null && salesmanRow.m > computedAt;
  if (newCustomers > 0 && salesmanChanged) {
    return {
      stale: true,
      reason: `${newCustomers} customer(s) and one or more salesmen changed since the last compute.`,
    };
  }
  if (newCustomers > 0) {
    return {
      stale: true,
      reason: `${newCustomers} customer(s) added or edited since the last compute.`,
    };
  }
  if (salesmanChanged) {
    return {
      stale: true,
      reason: 'One or more salesmen changed since the last compute.',
    };
  }
  // Mark as fresh — note maxCustomer is read but only to keep the SELECT optimised;
  // we don't expose it.
  void maxCustomer;
  return { stale: false, reason: 'Assignments are up to date.' };
}
