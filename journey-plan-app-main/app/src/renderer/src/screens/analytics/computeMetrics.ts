import type {
  Customer,
  JourneyPlan,
  PlanWithVisits,
  Salesman,
  Visit,
} from '@journey/shared';
import { dowOf, isVisitOverCapacity, parseHHMM } from '../planMath';

// 10-min slack mirrors scripts/audit-latest-plan.py:DAILY_SLACK.
const DAILY_SLACK = 10;
const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export interface SalesmanLoad {
  salesmanId: number;
  name: string;
  // Territory label for grouping the tables by geography. Derived from the
  // regions of the customers this salesman actually visits in THIS plan (the
  // one carrying the most visits), so it reflects the plan rather than roster
  // config. Falls back to the salesman's configured assignedRegions, then '—'.
  region: string;
  regionsCovered: string[]; // every distinct region touched, most-visited first
  visits: number;
  facetimeMin: number;
  driveMin: number;
  loadMin: number; // face + drive
  capacityMin: number; // sum of (work_end - work_start) across working days in period
  utilizationPct: number; // loadMin / capacityMin * 100
  daysWorked: number;
  daysAvailable: number;
  redVisits: number; // count of isVisitOverCapacity visits
  daysOverBudget: number;
}

export interface WeekBalance {
  salesmanId: number;
  name: string;
  region: string;
  regionsCovered: string[];
  weeks: number[]; // visits per week (length = #weeks in plan)
  maxMinRatio: number; // max / min across non-zero weeks; 1.0 = perfect
}

export interface FrequencyConformance {
  perCustomer: { customerId: number; name: string; got: number; want: number; delta: number }[];
  overCount: number;
  underCount: number;
  exactCount: number;
}

export interface DowCell {
  salesmanId: number;
  dow: number; // 0..6
  visits: number;
  loadMin: number;
}

export interface AnalyticsMetrics {
  plan: JourneyPlan;
  totals: {
    visits: number;
    customersInDataset: number;
    customersVisited: number;
    coveragePct: number;
    salesmenUsed: number;
    salesmenTotal: number;
    distinctDaysCovered: number;
    facetimeMin: number;
    driveMin: number;
    redVisits: number;
    daysOverBudget: number;
    avgUtilizationPct: number;
  };
  perSalesman: SalesmanLoad[];
  weekBalance: WeekBalance[];
  dowHeatmap: DowCell[];
  weekCount: number;
  frequency: FrequencyConformance;
  facetimeVsDrive: { name: string; value: number }[];
}

function workingMinutesPerDay(s: Salesman): number {
  return parseHHMM(s.workingHoursEnd) - parseHHMM(s.workingHoursStart);
}

// Region is optional on a customer (Phase 9), so a dataset that never filled it
// in legitimately yields '—' everywhere rather than a misleading label.
function regionLabel(
  s: Salesman,
  regionCounts: Map<string, number>,
): { region: string; regionsCovered: string[] } {
  const regionsCovered = [...regionCounts.entries()]
    .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
    .map(([r]) => r);
  if (regionsCovered.length > 0) return { region: regionsCovered[0]!, regionsCovered };
  if (s.assignedRegions.length > 0) {
    return { region: s.assignedRegions.join(', '), regionsCovered: [...s.assignedRegions] };
  }
  return { region: '—', regionsCovered: [] };
}

function workingDayCountInPeriod(s: Salesman, dates: string[]): number {
  const set = new Set(s.workingDays);
  return dates.filter((d) => set.has(dowOf(d))).length;
}

function expandDates(periodStart: string, periodEnd: string): string[] {
  const out: string[] = [];
  const [sy, sm, sd] = periodStart.split('-').map(Number);
  const [ey, em, ed] = periodEnd.split('-').map(Number);
  const start = new Date(sy!, (sm ?? 1) - 1, sd ?? 1);
  const end = new Date(ey!, (em ?? 1) - 1, ed ?? 1);
  for (let cur = new Date(start); cur <= end; cur.setDate(cur.getDate() + 1)) {
    const y = cur.getFullYear();
    const m = String(cur.getMonth() + 1).padStart(2, '0');
    const d = String(cur.getDate()).padStart(2, '0');
    out.push(`${y}-${m}-${d}`);
  }
  return out;
}

// Bucket visits into 7-day windows starting from periodStart, mirroring the
// PlanCalendarGrid week chunking so the week-balance numbers line up with what
// the user sees on the Plan screen.
function weekIndexOf(visitDate: string, periodStart: string): number {
  const [vy, vm, vd] = visitDate.split('-').map(Number);
  const [py, pm, pd] = periodStart.split('-').map(Number);
  const a = new Date(vy!, (vm ?? 1) - 1, vd ?? 1).getTime();
  const b = new Date(py!, (pm ?? 1) - 1, pd ?? 1).getTime();
  const days = Math.floor((a - b) / (1000 * 60 * 60 * 24));
  return Math.floor(days / 7);
}

export function computeMetrics(
  detail: PlanWithVisits,
  salesmen: Salesman[],
  customers: Customer[],
): AnalyticsMetrics {
  const visits: Visit[] = detail.visits;
  const planDates = expandDates(detail.plan.periodStart, detail.plan.periodEnd);
  const weekCount = Math.max(1, Math.ceil(planDates.length / 7));
  const salesmanById = new Map(salesmen.map((s) => [s.id, s]));
  const customerById = new Map(customers.map((c) => [c.id, c]));
  const datasetCustomerIds = new Set(
    customers
      .filter((c) => detail.plan.datasetId === null || c.datasetId === detail.plan.datasetId)
      .map((c) => c.id),
  );

  // --- per-salesman aggregation ---
  const perS = new Map<
    number,
    {
      visits: number;
      face: number;
      drive: number;
      redVisits: number;
      daysWorkedSet: Set<string>;
      dayLoad: Map<string, { face: number; drive: number }>;
      regionCounts: Map<string, number>;
    }
  >();
  for (const v of visits) {
    let slot = perS.get(v.salesmanId);
    if (!slot) {
      slot = {
        visits: 0,
        face: 0,
        drive: 0,
        redVisits: 0,
        daysWorkedSet: new Set(),
        dayLoad: new Map(),
        regionCounts: new Map(),
      };
      perS.set(v.salesmanId, slot);
    }
    slot.visits += 1;
    const visitRegion = customerById.get(v.customerId)?.region?.trim();
    if (visitRegion) {
      slot.regionCounts.set(visitRegion, (slot.regionCounts.get(visitRegion) ?? 0) + 1);
    }
    slot.face += v.facetimeMinutes ?? 0;
    slot.drive += v.driveMinutesTo ?? 0;
    slot.daysWorkedSet.add(v.scheduledDate);
    if (isVisitOverCapacity(v, salesmanById.get(v.salesmanId))) slot.redVisits += 1;
    let day = slot.dayLoad.get(v.scheduledDate);
    if (!day) {
      day = { face: 0, drive: 0 };
      slot.dayLoad.set(v.scheduledDate, day);
    }
    day.face += v.facetimeMinutes ?? 0;
    day.drive += v.driveMinutesTo ?? 0;
  }

  const perSalesman: SalesmanLoad[] = [];
  let totalLoad = 0;
  let totalCapacity = 0;
  let totalDaysOver = 0;
  for (const [sid, slot] of perS) {
    const s = salesmanById.get(sid);
    if (!s) continue;
    const daysAvailable = workingDayCountInPeriod(s, planDates);
    const capacityMin = daysAvailable * workingMinutesPerDay(s);
    const loadMin = slot.face + slot.drive;
    const utilizationPct = capacityMin > 0 ? (loadMin / capacityMin) * 100 : 0;
    const windowMin = workingMinutesPerDay(s);
    let daysOverBudget = 0;
    for (const { face, drive } of slot.dayLoad.values()) {
      if (face + drive > windowMin + DAILY_SLACK) daysOverBudget += 1;
    }
    totalLoad += loadMin;
    totalCapacity += capacityMin;
    totalDaysOver += daysOverBudget;
    const { region, regionsCovered } = regionLabel(s, slot.regionCounts);
    perSalesman.push({
      salesmanId: sid,
      name: s.name,
      region,
      regionsCovered,
      visits: slot.visits,
      facetimeMin: slot.face,
      driveMin: slot.drive,
      loadMin,
      capacityMin,
      utilizationPct,
      daysWorked: slot.daysWorkedSet.size,
      daysAvailable,
      redVisits: slot.redVisits,
      daysOverBudget,
    });
  }
  perSalesman.sort((a, b) => b.loadMin - a.loadMin);

  // --- week balance ---
  const weekVisits = new Map<number, number[]>();
  for (const v of visits) {
    const wi = weekIndexOf(v.scheduledDate, detail.plan.periodStart);
    if (wi < 0 || wi >= weekCount) continue;
    let arr = weekVisits.get(v.salesmanId);
    if (!arr) {
      arr = new Array<number>(weekCount).fill(0);
      weekVisits.set(v.salesmanId, arr);
    }
    arr[wi]! += 1;
  }
  // Reuse the region already derived for the load table so the two never disagree.
  const regionBySalesman = new Map(
    perSalesman.map((p) => [p.salesmanId, { region: p.region, regionsCovered: p.regionsCovered }]),
  );
  const weekBalance: WeekBalance[] = [];
  for (const [sid, weeks] of weekVisits) {
    const s = salesmanById.get(sid);
    if (!s) continue;
    const nonZero = weeks.filter((n) => n > 0);
    const max = nonZero.length > 0 ? Math.max(...nonZero) : 0;
    const min = nonZero.length > 0 ? Math.min(...nonZero) : 0;
    const maxMinRatio = min > 0 ? max / min : 0;
    const geo = regionBySalesman.get(sid) ?? { region: '—', regionsCovered: [] };
    weekBalance.push({
      salesmanId: sid,
      name: s.name,
      region: geo.region,
      regionsCovered: geo.regionsCovered,
      weeks,
      maxMinRatio,
    });
  }
  weekBalance.sort((a, b) => b.maxMinRatio - a.maxMinRatio);

  // --- DOW heatmap ---
  const dowCells = new Map<string, DowCell>();
  for (const v of visits) {
    const d = dowOf(v.scheduledDate);
    const key = `${v.salesmanId}:${d}`;
    let cell = dowCells.get(key);
    if (!cell) {
      cell = { salesmanId: v.salesmanId, dow: d, visits: 0, loadMin: 0 };
      dowCells.set(key, cell);
    }
    cell.visits += 1;
    cell.loadMin += (v.facetimeMinutes ?? 0) + (v.driveMinutesTo ?? 0);
  }

  // --- frequency conformance ---
  const visitsByCustomer = new Map<number, number>();
  for (const v of visits) {
    visitsByCustomer.set(v.customerId, (visitsByCustomer.get(v.customerId) ?? 0) + 1);
  }
  const freq: FrequencyConformance['perCustomer'] = [];
  let over = 0;
  let under = 0;
  let exact = 0;
  for (const cid of datasetCustomerIds) {
    const c = customerById.get(cid);
    if (!c) continue;
    const got = visitsByCustomer.get(cid) ?? 0;
    const want = c.monthlyFrequency;
    if (got === 0 && want > 0) {
      // mirrors audit script — unassigned-coverage is its own section, not a freq mismatch
      continue;
    }
    const delta = got - want;
    if (delta === 0) exact += 1;
    else if (delta > 0) over += 1;
    else under += 1;
    if (delta !== 0) {
      freq.push({ customerId: cid, name: c.name, got, want, delta });
    }
  }
  freq.sort((a, b) => Math.abs(b.delta) - Math.abs(a.delta));

  // --- totals ---
  const customersVisited = new Set(visits.map((v) => v.customerId)).size;
  const distinctDays = new Set(visits.map((v) => v.scheduledDate)).size;
  const totalFace = perSalesman.reduce((s, x) => s + x.facetimeMin, 0);
  const totalDrive = perSalesman.reduce((s, x) => s + x.driveMin, 0);
  const totalRed = perSalesman.reduce((s, x) => s + x.redVisits, 0);

  return {
    plan: detail.plan,
    totals: {
      visits: visits.length,
      customersInDataset: datasetCustomerIds.size,
      customersVisited,
      coveragePct: datasetCustomerIds.size > 0 ? (customersVisited / datasetCustomerIds.size) * 100 : 0,
      salesmenUsed: perSalesman.length,
      salesmenTotal: salesmen.length,
      distinctDaysCovered: distinctDays,
      facetimeMin: totalFace,
      driveMin: totalDrive,
      redVisits: totalRed,
      daysOverBudget: totalDaysOver,
      avgUtilizationPct: totalCapacity > 0 ? (totalLoad / totalCapacity) * 100 : 0,
    },
    perSalesman,
    weekBalance,
    dowHeatmap: Array.from(dowCells.values()),
    weekCount,
    frequency: {
      perCustomer: freq,
      overCount: over,
      underCount: under,
      exactCount: exact,
    },
    facetimeVsDrive: [
      { name: 'Facetime', value: totalFace },
      { name: 'Drive', value: totalDrive },
    ],
  };
}

export { DAY_LABELS };
