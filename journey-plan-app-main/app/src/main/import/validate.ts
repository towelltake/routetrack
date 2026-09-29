import { z } from 'zod';
import type { ImportFieldName, ImportMapping, ImportRowError, SalesChannel } from '@journey/shared';
import type { RawRow } from './excel';

// Oman lat/lng bounds (CLAUDE.md §7). A bit of slack on the edges to allow real coastal points.
const LAT_MIN = 16;
const LAT_MAX = 28;
const LNG_MIN = 52;
const LNG_MAX = 60;

const DAY_NAMES: Record<string, number> = {
  sun: 0, sunday: 0,
  mon: 1, monday: 1,
  tue: 2, tues: 2, tuesday: 2,
  wed: 3, weds: 3, wednesday: 3,
  thu: 4, thur: 4, thurs: 4, thursday: 4,
  fri: 5, friday: 5,
  sat: 6, saturday: 6,
};

function parseAllowedDays(raw: unknown): number[] | null {
  if (raw === null || raw === undefined || raw === '') return null;
  const text = String(raw).trim();
  if (!text) return null;
  const parts = text.split(/[,;|\s]+/).filter(Boolean);
  const out: number[] = [];
  for (const part of parts) {
    const asNum = Number(part);
    if (Number.isInteger(asNum) && asNum >= 0 && asNum <= 6) {
      out.push(asNum);
      continue;
    }
    const day = DAY_NAMES[part.toLowerCase()];
    if (day === undefined) throw new Error(`unrecognised day "${part}"`);
    out.push(day);
  }
  return Array.from(new Set(out)).sort();
}

function coerceNumber(raw: unknown): number | null {
  if (raw === null || raw === undefined || raw === '') return null;
  if (typeof raw === 'number') return Number.isFinite(raw) ? raw : null;
  const n = Number(String(raw).trim());
  return Number.isFinite(n) ? n : null;
}

const HHMM_RE = /^([01]?\d|2[0-3]):([0-5]\d)$/;

// Accepts "9:00" / "09:00" strings and Excel time serials (fraction of a day).
function parseHHMM(raw: unknown): string | null {
  if (raw === null || raw === undefined || raw === '') return null;
  if (typeof raw === 'number' && raw >= 0 && raw < 1) {
    const totalMin = Math.round(raw * 24 * 60);
    const h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
  }
  const text = String(raw).trim();
  const match = HHMM_RE.exec(text);
  if (!match) throw new Error(`"${text}" is not a valid HH:MM time`);
  return `${match[1]!.padStart(2, '0')}:${match[2]}`;
}

const facetimeSchema = z.number().int().min(1).max(240);
// Max 20 = up to 5 visits/week × 4 weeks (daily Sun–Thu).
const frequencySchema = z.number().int().min(1).max(20);
const latSchema = z.number().min(LAT_MIN).max(LAT_MAX);
const lngSchema = z.number().min(LNG_MIN).max(LNG_MAX);

export interface ValidatedRow {
  rowNumber: number; // 1-based, includes header row
  externalCode: string;
  name: string;
  address: string | null;
  lat: number | null;
  lng: number | null;
  facetimeMinutes: number | null;
  monthlyFrequency: number | null;
  allowedDays: number[] | null;
  pinnedSalesmanName: string | null;
  area: string | null;
  region: string | null;
  channel: SalesChannel | null;
  visitWindowStart: string | null;
  visitWindowEnd: string | null;
}

export interface ValidationResult {
  rows: ValidatedRow[];
  errors: ImportRowError[];
}

export interface ValidationDefaults {
  facetimeMinutes: number;
  monthlyFrequency: number;
}

export function validateRows(
  rows: RawRow[],
  headers: string[],
  mapping: ImportMapping,
): ValidationResult {
  if (!mapping.external_code) throw new Error('mapping is missing required field: external_code');
  if (!mapping.name) throw new Error('mapping is missing required field: name');

  const headerIndex = new Map(headers.map((h, i) => [h, i]));
  const idx = (field: ImportFieldName): number | null => {
    const col = mapping[field];
    if (!col) return null;
    const at = headerIndex.get(col);
    return at === undefined ? null : at;
  };

  const externalCodeIdx = idx('external_code')!;
  const nameIdx = idx('name')!;
  const addressIdx = idx('address');
  const latIdx = idx('lat');
  const lngIdx = idx('lng');
  const facetimeIdx = idx('facetime_minutes');
  const frequencyIdx = idx('monthly_frequency');
  const allowedDaysIdx = idx('allowed_days');
  const pinnedIdx = idx('pinned_salesman_name');
  const areaIdx = idx('area');
  const regionIdx = idx('region');
  const channelIdx = idx('channel');
  const windowStartIdx = idx('visit_window_start');
  const windowEndIdx = idx('visit_window_end');

  const validated: ValidatedRow[] = [];
  const errors: ImportRowError[] = [];
  const seenCodes = new Set<string>();

  rows.forEach((row, i) => {
    const rowNumber = i + 2; // header is row 1
    const rowErrors: string[] = [];

    const externalCode = String(row[externalCodeIdx] ?? '').trim();
    const name = String(row[nameIdx] ?? '').trim();

    if (!externalCode) rowErrors.push('external_code is empty');
    if (!name) rowErrors.push('name is empty');

    if (externalCode) {
      if (seenCodes.has(externalCode)) {
        rowErrors.push(`duplicate external_code "${externalCode}" within this upload`);
      } else {
        // Register the code eagerly so later rows with the same code are flagged
        // as duplicates regardless of whether this row also fails other checks.
        // Otherwise a row that errored on, say, lat-out-of-bounds would let a
        // second occurrence of the same code slip through silently.
        seenCodes.add(externalCode);
      }
    }

    const lat = latIdx !== null ? coerceNumber(row[latIdx]) : null;
    const lng = lngIdx !== null ? coerceNumber(row[lngIdx]) : null;

    if (lat !== null) {
      const r = latSchema.safeParse(lat);
      if (!r.success) rowErrors.push(`lat ${lat} outside Oman bounds [${LAT_MIN}, ${LAT_MAX}]`);
    }
    if (lng !== null) {
      const r = lngSchema.safeParse(lng);
      if (!r.success) rowErrors.push(`lng ${lng} outside Oman bounds [${LNG_MIN}, ${LNG_MAX}]`);
    }
    // Coordinates are mandatory — geocoding was removed 2026-05-22.
    if (lat === null || lng === null) {
      rowErrors.push('lat and lng are both required (geocoding is no longer available)');
    }

    let facetime: number | null = null;
    if (facetimeIdx !== null) {
      facetime = coerceNumber(row[facetimeIdx]);
      if (facetime !== null) {
        const r = facetimeSchema.safeParse(facetime);
        if (!r.success) rowErrors.push(`facetime ${facetime} out of range [1, 240]`);
      }
    }

    let frequency: number | null = null;
    if (frequencyIdx !== null) {
      frequency = coerceNumber(row[frequencyIdx]);
      if (frequency !== null) {
        const r = frequencySchema.safeParse(frequency);
        if (!r.success) rowErrors.push(`monthly_frequency ${frequency} out of range [1, 20]`);
      }
    }

    let allowedDays: number[] | null = null;
    if (allowedDaysIdx !== null) {
      try {
        allowedDays = parseAllowedDays(row[allowedDaysIdx]);
      } catch (err) {
        rowErrors.push(err instanceof Error ? err.message : String(err));
      }
    }

    const address = addressIdx !== null && row[addressIdx] !== null
      ? String(row[addressIdx]).trim() || null
      : null;
    const pinnedName = pinnedIdx !== null && row[pinnedIdx] !== null
      ? String(row[pinnedIdx]).trim() || null
      : null;
    const area = areaIdx !== null && row[areaIdx] !== null
      ? String(row[areaIdx]).trim() || null
      : null;
    const region = regionIdx !== null && row[regionIdx] !== null
      ? String(row[regionIdx]).trim() || null
      : null;

    let channel: SalesChannel | null = null;
    if (channelIdx !== null && row[channelIdx] !== null) {
      const raw = String(row[channelIdx]).trim().toUpperCase();
      if (raw === '') {
        channel = null;
      } else if (raw === 'MT' || raw === 'TT' || raw === 'WS') {
        channel = raw;
      } else {
        rowErrors.push(`channel "${row[channelIdx]}" must be MT, TT, WS, or empty`);
      }
    }

    let visitWindowStart: string | null = null;
    let visitWindowEnd: string | null = null;
    try {
      visitWindowStart = windowStartIdx !== null ? parseHHMM(row[windowStartIdx]) : null;
      visitWindowEnd = windowEndIdx !== null ? parseHHMM(row[windowEndIdx]) : null;
    } catch (err) {
      rowErrors.push(err instanceof Error ? err.message : String(err));
    }
    if ((visitWindowStart === null) !== (visitWindowEnd === null)) {
      rowErrors.push('visit_window_start and visit_window_end must both be set or both be empty');
    } else if (visitWindowStart !== null && visitWindowEnd !== null && visitWindowStart >= visitWindowEnd) {
      rowErrors.push(`visit window start ${visitWindowStart} must be before end ${visitWindowEnd}`);
    }

    if (rowErrors.length > 0) {
      errors.push({
        rowNumber,
        externalCode: externalCode || null,
        errors: rowErrors,
      });
      return;
    }

    validated.push({
      rowNumber,
      externalCode,
      name,
      address,
      lat,
      lng,
      facetimeMinutes: facetime,
      monthlyFrequency: frequency,
      allowedDays,
      pinnedSalesmanName: pinnedName,
      area,
      region,
      channel,
      visitWindowStart,
      visitWindowEnd,
    });
  });

  return { rows: validated, errors };
}

// ---- Entity-level guards for the IPC edit paths ----
// The Customers/Salesmen screens write straight through `customers:upsert` /
// `salesmen:upsert`, which used to bypass every rule above — a frequency of 0
// or a latitude of 91 typed into the edit panel reached SQLite and only failed
// later as an opaque "/optimize returned HTTP 422". Same rules, one source.

const hhmmSchema = z.string().regex(HHMM_RE, 'must be HH:MM');
const dowSchema = z.number().int().min(0).max(6);

function minutesOf(hhmm: string): number {
  const [h, m] = hhmm.split(':').map(Number);
  return h! * 60 + m!;
}

export const customerUpsertSchema = z
  .object({
    externalCode: z.string().trim().min(1, 'customer code is required'),
    name: z.string().trim().min(1, 'name is required'),
    lat: latSchema.nullable(),
    lng: lngSchema.nullable(),
    facetimeMinutes: facetimeSchema,
    monthlyFrequency: frequencySchema,
    allowedDays: z.array(dowSchema).nullable(),
    visitWindowStart: hhmmSchema.nullable(),
    visitWindowEnd: hhmmSchema.nullable(),
  })
  .refine((c) => (c.lat === null) === (c.lng === null), {
    message: 'latitude and longitude must both be set or both be empty',
  })
  .refine((c) => (c.visitWindowStart === null) === (c.visitWindowEnd === null), {
    message: 'visit window start and end must both be set or both be empty',
  })
  .refine(
    (c) =>
      c.visitWindowStart === null ||
      c.visitWindowEnd === null ||
      minutesOf(c.visitWindowStart) < minutesOf(c.visitWindowEnd),
    { message: 'visit window start must be before its end' },
  );

export const salesmanUpsertSchema = z
  .object({
    name: z.string().trim().min(1, 'name is required'),
    startLocationLat: latSchema,
    startLocationLng: lngSchema,
    workingDays: z.array(dowSchema).min(1, 'at least one working day is required'),
    workingHoursStart: hhmmSchema,
    workingHoursEnd: hhmmSchema,
    channelSkills: z.array(z.enum(['MT', 'TT', 'WS'])),
  })
  .refine((s) => minutesOf(s.workingHoursStart) < minutesOf(s.workingHoursEnd), {
    message: 'working hours start must be before working hours end',
  });

// Throws a single human-readable Error the renderer can show verbatim in a toast.
export function assertValid<T>(schema: z.ZodType<T>, value: unknown, label: string): void {
  const result = schema.safeParse(value);
  if (result.success) return;
  const detail = result.error.issues
    .map((i) => (i.path.length > 0 ? `${i.path.join('.')}: ${i.message}` : i.message))
    .join('; ');
  throw new Error(`${label} is not valid — ${detail}`);
}
