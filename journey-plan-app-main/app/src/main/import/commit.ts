import { basename } from 'node:path';
import type { ImportCommitRequest, ImportCommitResult } from '@journey/shared';
import { getDb } from '../db';
import { getSettings } from '../repo/settings';
import { getSalesmanByName } from '../repo/salesmen';
import { readWorkbook, parseSheet } from './excel';
import { validateRows } from './validate';

export function commitImport(req: ImportCommitRequest): ImportCommitResult {
  const workbook = readWorkbook(req.filePath);
  const { headers, rows } = parseSheet(workbook, req.sheet);
  const { rows: validated, errors } = validateRows(rows, headers, req.mapping);

  // An import where nothing validated must NOT create-and-activate an empty
  // dataset: that silently deactivates the dataset the user is working with and
  // blanks the Customers, Map, Plan and Analytics screens. The usual cause is a
  // mis-mapped lat/lng column, which rejects every row.
  if (validated.length === 0) {
    throw new Error(
      `No rows could be imported — all ${rows.length} row${rows.length === 1 ? '' : 's'} failed validation. ` +
        `The active dataset was left untouched. First error: ${errors[0]?.errors.join('; ') ?? 'unknown'}. ` +
        'Check the column mapping, especially Latitude and Longitude.',
    );
  }

  const settings = getSettings();
  const db = getDb();

  const datasetName =
    req.datasetName.trim() || `${basename(req.filePath)} (${new Date().toISOString()})`;

  let datasetId = 0;
  // Pin-name misses are warnings, not errors — the row still imports with
  // pinned_salesman_id = NULL. Surface them so a typo'd Excel cell doesn't
  // vanish into "no pin" without notice.
  const warnings: typeof errors = [];

  const tx = db.transaction(() => {
    const newDatasetInsert = db
      .prepare(
        `INSERT INTO customer_datasets (name, source_filename, is_active, row_count)
         VALUES (?, ?, 0, ?)`,
      )
      .run(datasetName, basename(req.filePath), validated.length);
    datasetId = Number(newDatasetInsert.lastInsertRowid);

    const insertCustomer = db.prepare(
      `INSERT INTO customers (
        dataset_id, external_code, name, address, lat, lng,
        facetime_minutes, monthly_frequency, allowed_days_csv,
        pinned_salesman_id, area, region, channel,
        visit_window_start, visit_window_end
      ) VALUES (
        @datasetId, @externalCode, @name, @address, @lat, @lng,
        @facetimeMinutes, @monthlyFrequency, @allowedDaysCsv,
        @pinnedSalesmanId, @area, @region, @channel,
        @visitWindowStart, @visitWindowEnd
      )`,
    );

    for (const row of validated) {
      const facetime = row.facetimeMinutes ?? settings.defaultFacetimeMinutes;
      const frequency = row.monthlyFrequency ?? settings.defaultMonthlyFrequency;

      let pinnedSalesmanId: number | null = null;
      if (row.pinnedSalesmanName) {
        const match = getSalesmanByName(row.pinnedSalesmanName);
        if (match) {
          pinnedSalesmanId = match.id;
        } else {
          warnings.push({
            rowNumber: row.rowNumber,
            externalCode: row.externalCode || null,
            errors: [
              `pinned_salesman_name "${row.pinnedSalesmanName}" did not match any existing salesman — pin cleared`,
            ],
          });
        }
      }

      insertCustomer.run({
        datasetId,
        externalCode: row.externalCode,
        name: row.name,
        address: row.address,
        lat: row.lat,
        lng: row.lng,
        facetimeMinutes: facetime,
        monthlyFrequency: frequency,
        allowedDaysCsv: row.allowedDays ? row.allowedDays.join(',') : null,
        pinnedSalesmanId,
        area: row.area,
        region: row.region,
        channel: row.channel,
        visitWindowStart: row.visitWindowStart,
        visitWindowEnd: row.visitWindowEnd,
      });
    }

    // Atomically swap active dataset: only one row can have is_active=1 (partial unique index).
    db.prepare('UPDATE customer_datasets SET is_active = 0 WHERE is_active = 1').run();
    db.prepare('UPDATE customer_datasets SET is_active = 1 WHERE id = ?').run(datasetId);
  });

  tx();

  return {
    datasetId,
    inserted: validated.length,
    errors,
    warnings,
  };
}
