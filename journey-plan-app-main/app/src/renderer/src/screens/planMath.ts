import type { Salesman, Visit } from '@journey/shared';

export function parseHHMM(s: string): number {
  const [h, m] = s.split(':').map(Number);
  return (h ?? 0) * 60 + (m ?? 0);
}

// SQLite writes CURRENT_TIMESTAMP as "YYYY-MM-DD HH:MM:SS" in UTC with no zone
// designator, which `new Date()` reads as LOCAL time — so every stored
// timestamp rendered 4 hours early in Oman. Normalise to an ISO UTC instant
// before formatting. ISO strings that already carry a zone pass through.
export function formatDbTimestamp(raw: string): string {
  const hasZone = /[Zz]|[+-]\d{2}:?\d{2}$/.test(raw);
  const iso = hasZone ? raw : `${raw.trim().replace(' ', 'T')}Z`;
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? raw : d.toLocaleString();
}

// 0=Sun .. 6=Sat per the app's convention. Mirrors scripts/audit-latest-plan.py:dow.
export function dowOf(isoDate: string): number {
  const [y, m, d] = isoDate.split('-').map(Number);
  const jsDow = new Date(y!, (m ?? 1) - 1, d ?? 1).getDay();
  return jsDow;
}

// A visit is "over-capacity" when its end time (start + facetime) lands past
// the salesman's configured working_hours_end. Under the 2026-05-20 soft-
// capacity change the solver assigns these instead of dropping them, and we
// surface them red so the user can spot the overflow and drag them to a
// freer day. `facetimeMinutes` is nullable on legacy plans; treat null as
// not-overcap to avoid false positives.
export function isVisitOverCapacity(visit: Visit, salesman: Salesman | undefined): boolean {
  if (!salesman || visit.facetimeMinutes == null) return false;
  const startMin = parseHHMM(visit.scheduledStartTime);
  const endMin = startMin + visit.facetimeMinutes;
  return endMin > parseHHMM(salesman.workingHoursEnd);
}
