import { readFileSync } from 'node:fs';
import * as XLSX from 'xlsx';
import type { ImportPreview } from '@journey/shared';

const PREVIEW_ROWS = 50;

export type RawCell = string | number | boolean | null;
export type RawRow = RawCell[];

export interface ParsedSheet {
  headers: string[];
  rows: RawRow[];
}

export function readWorkbook(filePath: string): XLSX.WorkBook {
  const buf = readFileSync(filePath);
  return XLSX.read(buf, { type: 'buffer', cellDates: false });
}

export function parseSheet(workbook: XLSX.WorkBook, sheetName: string): ParsedSheet {
  const ws = workbook.Sheets[sheetName];
  if (!ws) throw new Error(`sheet "${sheetName}" not found`);
  const arr = XLSX.utils.sheet_to_json<RawRow>(ws, {
    header: 1,
    blankrows: false,
    defval: null,
  });
  if (arr.length === 0) return { headers: [], rows: [] };
  const headers = (arr[0] as RawCell[]).map((h) => String(h ?? '').trim());
  const rows = arr.slice(1) as RawRow[];
  return { headers, rows };
}

export function buildPreview(filePath: string, sheetName?: string): ImportPreview {
  const workbook = readWorkbook(filePath);
  const sheets = workbook.SheetNames;
  const selected = sheetName && sheets.includes(sheetName) ? sheetName : sheets[0];
  if (!selected) {
    return {
      filePath,
      sheets: [],
      selectedSheet: '',
      headers: [],
      preview: [],
      rowCount: 0,
    };
  }
  const { headers, rows } = parseSheet(workbook, selected);
  return {
    filePath,
    sheets,
    selectedSheet: selected,
    headers,
    preview: rows.slice(0, PREVIEW_ROWS).map((r) =>
      r.map((v) => (typeof v === 'boolean' ? String(v) : v)),
    ),
    rowCount: rows.length,
  };
}
