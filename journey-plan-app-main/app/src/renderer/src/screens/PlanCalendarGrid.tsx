import { memo, useMemo, useState } from 'react';
import {
  DndContext,
  PointerSensor,
  useDraggable,
  useDroppable,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import type { PlanWithVisits, Salesman, Visit } from '@journey/shared';
import { isVisitOverCapacity } from './planMath';

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const WEEKEND_DOWS = new Set([5, 6]);
const DAYS_PER_WEEK = 7;

interface PlanCalendarGridProps {
  detail: PlanWithVisits;
  salesmanLookup: Record<number, Salesman>;
  customerNames: Record<number, string>;
  customerAddresses: Record<number, string>;
  readOnly: boolean;
  onMoved: () => void;
  onSelectVisit: (visit: Visit) => void;
}

function parseISODate(iso: string): Date {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y!, (m ?? 1) - 1, d ?? 1);
}

function formatISODate(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${dd}`;
}

function buildDateRange(start: string, end: string): string[] {
  const startD = parseISODate(start);
  const endD = parseISODate(end);
  const out: string[] = [];
  for (let cur = startD; cur <= endD; cur.setDate(cur.getDate() + 1)) {
    out.push(formatISODate(cur));
  }
  return out;
}

// Chunk the full plan period into 7-day windows so the user navigates a week at a time.
// The final chunk may be short if the period doesn't divide evenly.
function chunkIntoWeeks(dates: string[]): string[][] {
  const weeks: string[][] = [];
  for (let i = 0; i < dates.length; i += DAYS_PER_WEEK) {
    weeks.push(dates.slice(i, i + DAYS_PER_WEEK));
  }
  return weeks;
}

// Pick the week that contains today's date if it falls inside the plan; otherwise
// fall back to the first week so the user always lands on something meaningful.
function defaultWeekIndex(weeks: string[][]): number {
  const today = formatISODate(new Date());
  for (let i = 0; i < weeks.length; i++) {
    if (weeks[i]!.includes(today)) return i;
  }
  return 0;
}

function formatRangeLabel(week: string[]): string {
  if (week.length === 0) return '';
  const first = parseISODate(week[0]!);
  const last = parseISODate(week[week.length - 1]!);
  const fmt = (d: Date) =>
    d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  return `${fmt(first)} – ${fmt(last)}`;
}

interface DropCellId {
  salesmanId: number;
  date: string;
}

function dropCellKey({ salesmanId, date }: DropCellId): string {
  return `cell:${salesmanId}:${date}`;
}

function parseDragData(id: string): { visitId: number } | null {
  if (!id.startsWith('visit:')) return null;
  return { visitId: Number(id.slice(6)) };
}

function parseDropData(id: string): DropCellId | null {
  if (!id.startsWith('cell:')) return null;
  const [, salesmanId, date] = id.split(':');
  return { salesmanId: Number(salesmanId), date: date ?? '' };
}

export function PlanCalendarGrid({
  detail,
  salesmanLookup,
  customerNames,
  customerAddresses,
  readOnly,
  onMoved,
  onSelectVisit,
}: PlanCalendarGridProps) {
  const [error, setError] = useState<string | null>(null);
  const [moving, setMoving] = useState(false);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
  );

  // Union of every salesman's working_days. Non-working DOWs get filtered out of
  // the rendered columns so the user only sees days at least one salesman covers.
  // Edge case: if no salesmen are configured (e.g. fresh roster), fall back to
  // the Oman default Sun-Thu so the grid still has columns.
  const visibleDows = useMemo(() => {
    const set = new Set<number>();
    for (const s of Object.values(salesmanLookup)) {
      for (const d of s.workingDays) set.add(d);
    }
    if (set.size === 0) [0, 1, 2, 3, 4].forEach((d) => set.add(d));
    return set;
  }, [salesmanLookup]);

  // The full plan period and the chunking into 7-day windows are stable across
  // re-renders unless the plan itself swaps — memoize on the period bounds.
  const weeks = useMemo(() => {
    const allDates = buildDateRange(detail.plan.periodStart, detail.plan.periodEnd);
    return chunkIntoWeeks(allDates);
  }, [detail.plan.periodStart, detail.plan.periodEnd]);

  const [weekIndex, setWeekIndex] = useState<number>(() => defaultWeekIndex(weeks));
  // Clamp on plan switch: if the previous plan had more weeks than the new one,
  // weekIndex could be out of bounds. Snap to the last week available.
  const safeWeekIndex = Math.min(weekIndex, Math.max(weeks.length - 1, 0));
  // 7-day window minus any DOW no salesman works. The week label still says
  // "Sun-Sat" naturally even if Fri/Sat are hidden — the Today/Prev/Next math
  // operates on full 7-day windows so the user's mental model stays sane.
  const visibleDates = (weeks[safeWeekIndex] ?? []).filter((d) =>
    visibleDows.has(parseISODate(d).getDay()),
  );

  // Group visits by (salesman, date) cell ONCE per plan-change so per-week
  // navigation doesn't rebuild the map. Per-cell visit arrays come back sorted
  // by sequence so chips render in the right order.
  const visitsByCell = useMemo(() => {
    const out = new Map<string, Visit[]>();
    for (const v of detail.visits) {
      const key = dropCellKey({ salesmanId: v.salesmanId, date: v.scheduledDate });
      if (!out.has(key)) out.set(key, []);
      out.get(key)!.push(v);
    }
    for (const list of out.values()) list.sort((a, b) => a.sequence - b.sequence);
    return out;
  }, [detail.visits]);

  // Hide salesmen with zero visits ANYWHERE in the plan to keep the grid tight —
  // an empty row still serves as a drop target so we keep them when they have
  // visits in OTHER weeks (so dragging can move work to them in the current week).
  const salesmanRows = useMemo(() => {
    const idsWithAnyVisits = new Set<number>(detail.visits.map((v) => v.salesmanId));
    return Object.values(salesmanLookup)
      .filter((s) => idsWithAnyVisits.has(s.id))
      .sort((a, b) => a.name.localeCompare(b.name));
  }, [detail.visits, salesmanLookup]);

  const handleDragEnd = async (evt: DragEndEvent) => {
    if (readOnly || !evt.over) return;
    const drag = parseDragData(String(evt.active.id));
    const drop = parseDropData(String(evt.over.id));
    if (!drag || !drop || !drop.date) return;
    const visit = detail.visits.find((v) => v.id === drag.visitId);
    if (!visit) return;
    if (visit.salesmanId === drop.salesmanId && visit.scheduledDate === drop.date) {
      return;
    }
    setMoving(true);
    setError(null);
    try {
      await window.api.reassignVisit({
        planId: detail.plan.id,
        visitId: drag.visitId,
        toSalesmanId: drop.salesmanId,
        toDate: drop.date,
      });
      onMoved();
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
      // The move is atomic, so this normally re-shows the unchanged plan — but
      // the grid must always mirror the DB, not the pre-drag snapshot.
      onMoved();
    } finally {
      setMoving(false);
    }
  };

  const rangeLabel = formatRangeLabel(visibleDates);

  return (
    <div>
      <div className="week-nav">
        <button
          onClick={() => setWeekIndex((i) => Math.max(0, i - 1))}
          disabled={safeWeekIndex === 0}
        >
          ← Prev week
        </button>
        <span className="week-nav-label">
          <strong>Week {safeWeekIndex + 1}</strong> of {weeks.length} · {rangeLabel}
        </span>
        <button
          onClick={() => setWeekIndex((i) => Math.min(weeks.length - 1, i + 1))}
          disabled={safeWeekIndex >= weeks.length - 1}
        >
          Next week →
        </button>
        <button
          className="week-nav-today"
          onClick={() => setWeekIndex(defaultWeekIndex(weeks))}
          title="Jump to the week containing today"
        >
          Today
        </button>
      </div>
      {error && (
        <p className="result err" style={{ marginBottom: 8 }}>
          {error}
        </p>
      )}
      {moving && <p className="muted" style={{ marginBottom: 8 }}>Re-sequencing…</p>}
      <DndContext sensors={sensors} onDragEnd={handleDragEnd}>
        <div className="calendar-grid-wrap">
          <table
            className="calendar-grid week-view"
            style={{ ['--visible-day-count' as string]: visibleDates.length || 1 }}
          >
            <thead>
              <tr>
                <th className="corner-cell">Salesman</th>
                {visibleDates.map((d) => {
                  const dow = parseISODate(d).getDay();
                  return (
                    <th
                      key={d}
                      className={WEEKEND_DOWS.has(dow) ? 'date-cell weekend' : 'date-cell'}
                    >
                      <div className="date-label">{d.slice(5)}</div>
                      <div className="date-dow">{DAY_LABELS[dow]}</div>
                    </th>
                  );
                })}
              </tr>
            </thead>
            <tbody>
              {salesmanRows.map((s) => (
                <tr key={s.id}>
                  <th className="salesman-cell">{s.name}</th>
                  {visibleDates.map((d) => {
                    const key = dropCellKey({ salesmanId: s.id, date: d });
                    const visits = visitsByCell.get(key) ?? [];
                    const dow = parseISODate(d).getDay();
                    return (
                      <DropCell
                        key={key}
                        salesmanId={s.id}
                        date={d}
                        visits={visits}
                        salesman={s}
                        customerNames={customerNames}
                        customerAddresses={customerAddresses}
                        weekend={WEEKEND_DOWS.has(dow)}
                        readOnly={readOnly}
                        onSelectVisit={onSelectVisit}
                      />
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </DndContext>
    </div>
  );
}

interface DropCellProps {
  salesmanId: number;
  date: string;
  visits: Visit[];
  salesman: Salesman;
  customerNames: Record<number, string>;
  customerAddresses: Record<number, string>;
  weekend: boolean;
  readOnly: boolean;
  onSelectVisit: (visit: Visit) => void;
}

function DropCell({
  salesmanId,
  date,
  visits,
  salesman,
  customerNames,
  customerAddresses,
  weekend,
  readOnly,
  onSelectVisit,
}: DropCellProps) {
  const { isOver, setNodeRef } = useDroppable({
    id: dropCellKey({ salesmanId, date }),
    disabled: readOnly,
  });
  const cls = [
    'grid-cell',
    weekend ? 'weekend' : '',
    isOver ? 'drop-over' : '',
  ]
    .filter(Boolean)
    .join(' ');
  return (
    <td ref={setNodeRef} className={cls}>
      {visits.map((v) => (
        <VisitChip
          key={v.id}
          visit={v}
          customerName={customerNames[v.customerId] ?? `#${v.customerId}`}
          customerAddress={customerAddresses[v.customerId] ?? ''}
          overCapacity={isVisitOverCapacity(v, salesman)}
          readOnly={readOnly}
          onSelect={onSelectVisit}
        />
      ))}
    </td>
  );
}

interface VisitChipProps {
  visit: Visit;
  customerName: string;
  customerAddress: string;
  overCapacity: boolean;
  readOnly: boolean;
  onSelect: (visit: Visit) => void;
}

// Memoized so a drag of one chip doesn't re-render every other chip in the week.
// The visit object reference is stable within a single PlanWithVisits payload, so
// shallow-equality on props is the right cache key here.
const VisitChip = memo(function VisitChip({
  visit,
  customerName,
  customerAddress,
  overCapacity,
  readOnly,
  onSelect,
}: VisitChipProps) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: `visit:${visit.id}`,
    disabled: readOnly,
  });
  const style: React.CSSProperties = {
    opacity: isDragging ? 0.5 : 1,
    transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined,
  };
  const chipClass = overCapacity ? 'visit-chip overcap' : 'visit-chip';
  const overcapHint = overCapacity
    ? '\n⚠ Past working hours — drag to a freer day to clear the overflow.'
    : '';
  const tooltip = customerAddress
    ? `${customerName}\n${customerAddress}${overcapHint}`
    : `${customerName}${overcapHint}`;
  return (
    <div
      ref={setNodeRef}
      style={style}
      className={chipClass}
      onClick={() => onSelect(visit)}
      {...(readOnly ? {} : attributes)}
      {...(readOnly ? {} : listeners)}
      title={tooltip}
    >
      <div className="chip-line chip-line-1">
        <span className="chip-seq">#{visit.sequence}</span>
        <span className="chip-name">{customerName}</span>
        <span className="chip-time">{visit.scheduledStartTime}</span>
      </div>
      {customerAddress && (
        <div className="chip-line chip-line-2">
          <span className="chip-address">{customerAddress}</span>
        </div>
      )}
    </div>
  );
});
