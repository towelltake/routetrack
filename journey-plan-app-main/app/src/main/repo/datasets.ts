import type { CustomerDataset } from '@journey/shared';
import { getDb } from '../db';

interface DatasetRow {
  id: number;
  name: string;
  source_filename: string;
  imported_at: string;
  is_active: number;
  row_count: number;
}

function rowToDataset(row: DatasetRow): CustomerDataset {
  return {
    id: row.id,
    name: row.name,
    sourceFilename: row.source_filename,
    importedAt: row.imported_at,
    isActive: row.is_active === 1,
    rowCount: row.row_count,
  };
}

export function listDatasets(): CustomerDataset[] {
  const rows = getDb()
    .prepare('SELECT * FROM customer_datasets ORDER BY imported_at DESC')
    .all() as DatasetRow[];
  return rows.map(rowToDataset);
}

export function getActiveDataset(): CustomerDataset | null {
  const row = getDb()
    .prepare('SELECT * FROM customer_datasets WHERE is_active = 1')
    .get() as DatasetRow | undefined;
  return row ? rowToDataset(row) : null;
}

export function getDataset(id: number): CustomerDataset | null {
  const row = getDb()
    .prepare('SELECT * FROM customer_datasets WHERE id = ?')
    .get(id) as DatasetRow | undefined;
  return row ? rowToDataset(row) : null;
}

export function activateDataset(id: number): void {
  const db = getDb();
  const tx = db.transaction(() => {
    db.prepare('UPDATE customer_datasets SET is_active = 0 WHERE is_active = 1').run();
    db.prepare('UPDATE customer_datasets SET is_active = 1 WHERE id = ?').run(id);
  });
  tx();
}

// Hard-deletes a dataset and everything that lives on top of it: plans (with
// their visits via FK CASCADE) and customers. Refuses the active dataset so a
// stray click can't wipe the data the user is currently working with.
export function deleteDataset(id: number): { plans: number; customers: number } {
  const db = getDb();
  const ds = getDataset(id);
  if (!ds) throw new Error('dataset not found');
  if (ds.isActive) {
    throw new Error('Cannot delete the active dataset. Make another dataset active first.');
  }
  // Same rule the per-plan delete enforces: a locked plan is locked whichever
  // screen the delete is issued from.
  const finalPlans = db
    .prepare("SELECT name FROM journey_plans WHERE dataset_id = ? AND status = 'final' ORDER BY name")
    .all(id) as { name: string }[];
  if (finalPlans.length > 0) {
    throw new Error(
      `This dataset has ${finalPlans.length} final plan${finalPlans.length === 1 ? '' : 's'} ` +
        `(${finalPlans.map((p) => p.name).join(', ')}). Unlock or delete them first.`,
    );
  }
  let plansDeleted = 0;
  let customersDeleted = 0;
  const tx = db.transaction(() => {
    plansDeleted = (
      db.prepare('DELETE FROM journey_plans WHERE dataset_id = ?').run(id).changes
    );
    customersDeleted = (
      db.prepare('DELETE FROM customers WHERE dataset_id = ?').run(id).changes
    );
    db.prepare('DELETE FROM customer_datasets WHERE id = ?').run(id);
  });
  tx();
  return { plans: plansDeleted, customers: customersDeleted };
}
