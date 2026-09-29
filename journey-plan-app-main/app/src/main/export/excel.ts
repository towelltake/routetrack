import ExcelJS from 'exceljs';
import type { Customer, Visit } from '@journey/shared';
import { getDb } from '../db';
import { getCustomer } from '../repo/customers';
import { getPlan } from '../repo/journeyPlans';
import { listPlanSalesmen } from '../repo/planSalesmen';
import { listVisitsForPlan } from '../repo/visits';

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// Match the precision used by writes in app/src/main/cache.ts so lookups hit.
const COORD_PRECISION = 6;
function roundCoord(n: number): number {
  const factor = 10 ** COORD_PRECISION;
  return Math.round(n * factor) / factor;
}

// Pull leg distances out of distance_cache for every consecutive
// (prev customer → this customer) pair within a (salesman, date). First visit of
// the day has no leg in → 0. If the cache is missing a pair (rare; would mean the
// solver routed against a pair it never measured), we fall back to 0 metres for
// that leg rather than crashing the export.
function buildLegDistances(
  visits: Visit[],
  customers: Map<number, Customer>,
): Map<number, number> {
  const out = new Map<number, number>();
  const stmt = getDb().prepare(
    `SELECT distance_meters FROM distance_cache
     WHERE origin_lat = ? AND origin_lng = ? AND dest_lat = ? AND dest_lng = ?`,
  );
  const sorted = [...visits].sort((a, b) => {
    if (a.salesmanId !== b.salesmanId) return a.salesmanId - b.salesmanId;
    if (a.scheduledDate !== b.scheduledDate) return a.scheduledDate < b.scheduledDate ? -1 : 1;
    return a.sequence - b.sequence;
  });
  for (let i = 0; i < sorted.length; i++) {
    const v = sorted[i]!;
    const prev = i > 0 ? sorted[i - 1]! : null;
    const sameLeg =
      prev && prev.salesmanId === v.salesmanId && prev.scheduledDate === v.scheduledDate;
    if (!sameLeg) {
      out.set(v.id, 0);
      continue;
    }
    const a = customers.get(prev.customerId);
    const b = customers.get(v.customerId);
    if (!a?.lat || !a?.lng || !b?.lat || !b?.lng) {
      out.set(v.id, 0);
      continue;
    }
    const row = stmt.get(
      roundCoord(a.lat),
      roundCoord(a.lng),
      roundCoord(b.lat),
      roundCoord(b.lng),
    ) as { distance_meters: number } | undefined;
    // Clamp: OSRM returns -1 for a same-node pair, and two such rows are known
    // to be in existing prod DBs (fixed at the cache boundary 2026-06-14, but
    // this query reads the table directly and so never saw that clamp).
    out.set(v.id, Math.max(0, row?.distance_meters ?? 0));
  }
  return out;
}
const DEFAULT_WEEKEND_DOWS = new Set([5, 6]); // Fri, Sat (Oman default)

function dayOfWeek(isoDate: string): number {
  const [y, m, d] = isoDate.split('-').map(Number);
  return new Date(y!, (m ?? 1) - 1, d ?? 1).getDay();
}

function weekdayLabel(isoDate: string): string {
  return DAY_LABELS[dayOfWeek(isoDate)] ?? '';
}

function groupVisitsBySalesman(visits: Visit[]): Map<number, Visit[]> {
  const out = new Map<number, Visit[]>();
  for (const v of visits) {
    if (!out.has(v.salesmanId)) out.set(v.salesmanId, []);
    out.get(v.salesmanId)!.push(v);
  }
  return out;
}

export async function exportPlanToExcel(planId: number, outputPath: string): Promise<void> {
  const plan = getPlan(planId);
  if (!plan) throw new Error(`plan ${planId} not found`);
  const visits = listVisitsForPlan(planId);
  // Salesman names come from the plan's own snapshot (migration 0013), so the
  // export stays correct even after roster edits/deletes.
  const planSalesmen = listPlanSalesmen(planId);
  const salesmanNames = new Map(planSalesmen.map((s) => [s.id, s.name]));
  // Non-working days come from each salesman's own snapshot schedule, not a
  // fixed Fri/Sat: rosters like the Saturday-working Nestle team had every one
  // of their regular Saturdays shaded as if it were a weekend.
  const workingDaysById = new Map(planSalesmen.map((s) => [s.id, new Set(s.workingDays)]));

  const customerCache = new Map<number, Customer>();
  for (const v of visits) {
    if (!customerCache.has(v.customerId)) {
      const c = getCustomer(v.customerId);
      if (c) customerCache.set(v.customerId, c);
    }
  }

  const legMetres = buildLegDistances(visits, customerCache);
  const metresToKm = (m: number): number => Math.round((m / 1000) * 10) / 10;

  const wb = new ExcelJS.Workbook();
  wb.creator = 'Journey Plan App';
  wb.created = new Date();

  // ---- Summary sheet ----
  const summary = wb.addWorksheet('Summary');
  summary.columns = [
    { header: 'Field', key: 'field', width: 28 },
    { header: 'Value', key: 'value', width: 40 },
  ];
  summary.getRow(1).font = { bold: true };

  const totalDriveMin = visits.reduce((s, v) => s + (v.driveMinutesTo ?? 0), 0);
  const totalFacetimeMin = visits.reduce((s, v) => s + (v.facetimeMinutes ?? 0), 0);
  const totalDistanceMetres = visits.reduce((s, v) => s + (legMetres.get(v.id) ?? 0), 0);
  const salesmanCount = new Set(visits.map((v) => v.salesmanId)).size;

  summary.addRows([
    { field: 'Plan name', value: plan.name },
    { field: 'Status', value: plan.status },
    { field: 'Period start', value: plan.periodStart },
    { field: 'Period end', value: plan.periodEnd },
    { field: 'Created at', value: plan.createdAt },
    { field: 'Salesmen with visits', value: salesmanCount },
    { field: 'Total visits', value: visits.length },
    { field: 'Total drive (min)', value: totalDriveMin },
    { field: 'Total distance (km)', value: metresToKm(totalDistanceMetres) },
    { field: 'Total facetime (min)', value: totalFacetimeMin },
  ]);

  // Per-salesman summary block.
  summary.addRow([]);
  const header = summary.addRow([
    'Salesman',
    'Visits',
    'Drive (min)',
    'Distance (km)',
    'Facetime (min)',
  ]);
  header.font = { bold: true };
  const bySalesman = groupVisitsBySalesman(visits);
  for (const [salesmanId, sv] of bySalesman) {
    const sName = salesmanNames.get(salesmanId) ?? `#${salesmanId}`;
    const sDrive = sv.reduce((s, v) => s + (v.driveMinutesTo ?? 0), 0);
    const sFace = sv.reduce((s, v) => s + (v.facetimeMinutes ?? 0), 0);
    const sMetres = sv.reduce((s, v) => s + (legMetres.get(v.id) ?? 0), 0);
    summary.addRow([sName, sv.length, sDrive, metresToKm(sMetres), sFace]);
  }
  summary.views = [{ state: 'frozen', ySplit: 1 }];

  // ---- One sheet per salesman ----
  // Excel rejects duplicate worksheet names, names >31 chars, or names containing
  // \ / ? * [ ] :. Sanitize + dedupe so collisions (e.g. two salesmen sharing a
  // name from the Suggest-team-size flow) don't blow up the whole export.
  const usedSheetNames = new Set<string>();
  const makeSheetName = (raw: string): string => {
    const base = raw.replace(/[\\/?*[\]:]/g, '_').slice(0, 31).trim() || 'Salesman';
    if (!usedSheetNames.has(base)) {
      usedSheetNames.add(base);
      return base;
    }
    for (let i = 2; i < 1000; i++) {
      const suffix = ` (${i})`;
      const candidate = `${base.slice(0, 31 - suffix.length)}${suffix}`;
      if (!usedSheetNames.has(candidate)) {
        usedSheetNames.add(candidate);
        return candidate;
      }
    }
    throw new Error(`could not derive unique sheet name from "${raw}"`);
  };

  // 'Summary' is already taken by the first sheet above.
  usedSheetNames.add('Summary');

  for (const [salesmanId, sv] of bySalesman) {
    const sheetName = makeSheetName(salesmanNames.get(salesmanId) ?? `Salesman ${salesmanId}`);
    const sheet = wb.addWorksheet(sheetName);
    sheet.columns = [
      { header: 'Date', key: 'date', width: 12 },
      { header: 'Weekday', key: 'weekday', width: 10 },
      { header: 'Seq', key: 'sequence', width: 6 },
      { header: 'Start', key: 'start', width: 8 },
      { header: 'Code', key: 'code', width: 14 },
      { header: 'Customer', key: 'customer', width: 32 },
      { header: 'Address', key: 'address', width: 40 },
      { header: 'Area', key: 'area', width: 16 },
      { header: 'Drive (min)', key: 'drive', width: 12 },
      { header: 'Distance (km)', key: 'distanceKm', width: 13 },
      { header: 'Facetime (min)', key: 'facetime', width: 14 },
    ];
    sheet.getRow(1).font = { bold: true };
    sheet.views = [{ state: 'frozen', ySplit: 1 }];

    // Group by date, sort by date then sequence.
    const sortedVisits = [...sv].sort((a, b) => {
      if (a.scheduledDate !== b.scheduledDate) {
        return a.scheduledDate < b.scheduledDate ? -1 : 1;
      }
      return a.sequence - b.sequence;
    });

    let prevDate: string | null = null;
    let dayDrive = 0;
    let dayFace = 0;
    let dayMetres = 0;
    const flushDayTotal = (forDate: string) => {
      const totalRow = sheet.addRow({
        date: '',
        weekday: '',
        sequence: '',
        start: '',
        code: '',
        customer: `${forDate} total`,
        address: '',
        area: '',
        drive: dayDrive,
        distanceKm: metresToKm(dayMetres),
        facetime: dayFace,
      });
      totalRow.font = { italic: true };
      totalRow.eachCell((cell) => {
        cell.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: 'FFF3F4F6' },
        };
      });
    };

    for (const v of sortedVisits) {
      if (prevDate !== null && v.scheduledDate !== prevDate) {
        flushDayTotal(prevDate);
        dayDrive = 0;
        dayFace = 0;
        dayMetres = 0;
      }
      const c = customerCache.get(v.customerId);
      const metresForLeg = legMetres.get(v.id) ?? 0;
      const row = sheet.addRow({
        date: v.scheduledDate,
        weekday: weekdayLabel(v.scheduledDate),
        sequence: v.sequence,
        start: v.scheduledStartTime,
        code: c?.externalCode ?? '',
        customer: c?.name ?? `#${v.customerId}`,
        address: c?.address ?? '',
        area: c?.area ?? '',
        drive: v.driveMinutesTo ?? 0,
        distanceKm: metresToKm(metresForLeg),
        facetime: v.facetimeMinutes ?? 0,
      });
      const workingDays = workingDaysById.get(salesmanId);
      const isOffDay = workingDays
        ? !workingDays.has(dayOfWeek(v.scheduledDate))
        : DEFAULT_WEEKEND_DOWS.has(dayOfWeek(v.scheduledDate));
      if (isOffDay) {
        row.eachCell((cell) => {
          cell.fill = {
            type: 'pattern',
            pattern: 'solid',
            fgColor: { argb: 'FFFEF3C7' },
          };
        });
      }
      dayDrive += v.driveMinutesTo ?? 0;
      dayFace += v.facetimeMinutes ?? 0;
      dayMetres += metresForLeg;
      prevDate = v.scheduledDate;
    }
    if (prevDate !== null) flushDayTotal(prevDate);
  }

  await wb.xlsx.writeFile(outputPath);
}
