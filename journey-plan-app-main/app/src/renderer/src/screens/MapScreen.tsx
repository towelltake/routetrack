import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { MapContainer, Marker, Polyline, TileLayer } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import type {
  Customer,
  JourneyPlan,
  OsrmRouteResponse,
  PlanWithVisits,
  Salesman,
  Visit,
} from '@journey/shared';

const MUSCAT = { lat: 23.5880, lng: 58.3829 };

// One stable color per salesman, assigned by snapshot order. Past the curated
// palette, golden-angle hues keep extra salesmen distinguishable.
const SALESMAN_PALETTE = [
  '#2563eb', '#dc2626', '#16a34a', '#9333ea', '#ea580c', '#0891b2',
  '#db2777', '#65a30d', '#7c3aed', '#0d9488', '#b45309', '#1d4ed8',
  '#be123c', '#15803d', '#a21caf', '#475569',
];

function salesmanColor(index: number): string {
  return SALESMAN_PALETTE[index] ?? `hsl(${Math.round((index * 137.508) % 360)}, 65%, 42%)`;
}

// 7-day buckets from periodStart — mirrors analytics/computeMetrics.weekIndexOf
// so "Week 2" here is the same week the Analytics screen reports on.
function weekIndexOf(visitDate: string, periodStart: string): number {
  const [vy, vm, vd] = visitDate.split('-').map(Number);
  const [py, pm, pd] = periodStart.split('-').map(Number);
  const a = new Date(vy!, (vm ?? 1) - 1, vd ?? 1).getTime();
  const b = new Date(py!, (pm ?? 1) - 1, pd ?? 1).getTime();
  const days = Math.floor((a - b) / (1000 * 60 * 60 * 24));
  return Math.floor(days / 7);
}

function planWeekCount(plan: JourneyPlan): number {
  const [sy, sm, sd] = plan.periodStart.split('-').map(Number);
  const [ey, em, ed] = plan.periodEnd.split('-').map(Number);
  const start = new Date(sy!, (sm ?? 1) - 1, sd ?? 1).getTime();
  const end = new Date(ey!, (em ?? 1) - 1, ed ?? 1).getTime();
  const days = Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1;
  return Math.max(1, Math.ceil(days / 7));
}

interface DayRoute {
  key: string; // `${salesmanId}|${date}`
  salesmanId: number;
  date: string;
  visits: Visit[]; // sequence-sorted
}

type RouteGeometry = OsrmRouteResponse | 'error';

const DOT_ICON = makeDotIcon(false);
const DOT_ICON_DIMMED = makeDotIcon(true);
const routeDotIconCache = new Map<string, L.DivIcon>();

function makeDotIcon(dimmed: boolean): L.DivIcon {
  return L.divIcon({
    className: 'leaflet-dot-icon',
    html: `<span style="background:#22c55e;opacity:${dimmed ? 0.35 : 0.95};border:1px solid #1f2937;width:10px;height:10px;border-radius:50%;display:block;"></span>`,
    iconSize: [12, 12],
    iconAnchor: [6, 6],
  });
}

function makeRouteDotIcon(color: string): L.DivIcon {
  let icon = routeDotIconCache.get(color);
  if (!icon) {
    icon = L.divIcon({
      className: 'leaflet-dot-icon',
      html: `<span style="background:${color};border:2px solid #ffffff;box-shadow:0 1px 2px rgba(0,0,0,0.4);width:12px;height:12px;border-radius:50%;display:block;"></span>`,
      iconSize: [14, 14],
      iconAnchor: [7, 7],
    });
    routeDotIconCache.set(color, icon);
  }
  return icon;
}

function makeNumberedIcon(n: number, color: string): L.DivIcon {
  return L.divIcon({
    className: 'leaflet-numbered-icon',
    html: `<span style="background:${color};color:#fff;font-weight:700;font-size:12px;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;border:2px solid #ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.4);">${n}</span>`,
    iconSize: [24, 24],
    iconAnchor: [12, 12],
  });
}

// Shared selection panel — renders the visit-detail card.
function CustomerSidePanel({
  selected,
  onClose,
}: {
  selected: Customer | null;
  onClose: () => void;
}) {
  if (!selected) return null;
  return (
    <aside className="side-panel">
      <header>
        <h3>{selected.name}</h3>
        <button onClick={onClose}>×</button>
      </header>
      <dl>
        <dt>Code</dt>
        <dd>{selected.externalCode}</dd>
        <dt>Coordinates</dt>
        <dd>
          {selected.lat?.toFixed(5)}, {selected.lng?.toFixed(5)}
        </dd>
        <dt>Area</dt>
        <dd>{selected.area ?? '—'}</dd>
        <dt>Facetime</dt>
        <dd>{selected.facetimeMinutes} min</dd>
        <dt>Visits / month</dt>
        <dd>{selected.monthlyFrequency}</dd>
      </dl>
    </aside>
  );
}

export function MapScreen() {
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [plans, setPlans] = useState<JourneyPlan[]>([]);
  const [selectedPlanId, setSelectedPlanId] = useState<number | ''>('');
  const [detail, setDetail] = useState<PlanWithVisits | null>(null);
  const [detailError, setDetailError] = useState<string | null>(null);
  const [selected, setSelected] = useState<Customer | null>(null);

  // Filters. 'all' shows everything; each narrows the displayed routes.
  const [weekFilter, setWeekFilter] = useState<number | 'all'>('all');
  const [salesmanFilter, setSalesmanFilter] = useState<number | 'all'>('all');
  const [dayFilter, setDayFilter] = useState<string | 'all'>('all');

  // OSRM geometries per day-route, cached per plan. The generation counter
  // discards in-flight responses that resolve after the user switched plans.
  const [geometries, setGeometries] = useState<Map<string, RouteGeometry>>(new Map());
  const inFlight = useRef<Set<string>>(new Set());
  const planGeneration = useRef(0);

  useEffect(() => {
    void (async () => {
      setCustomers(await window.api.listCustomers({ limit: 10000 }));
      setPlans(await window.api.listPlans());
    })();
  }, []);

  useEffect(() => {
    planGeneration.current += 1;
    inFlight.current.clear();
    setGeometries(new Map());
    setWeekFilter('all');
    setSalesmanFilter('all');
    setDayFilter('all');
    setDetailError(null);
    if (selectedPlanId === '') {
      setDetail(null);
      return;
    }
    let cancelled = false;
    void window.api
      .getPlan(selectedPlanId)
      .then((d) => {
        if (!cancelled) setDetail(d);
      })
      .catch((err) => {
        if (!cancelled) setDetailError(err instanceof Error ? err.message : String(err));
      });
    return () => {
      cancelled = true;
    };
  }, [selectedPlanId]);

  const customerById = useMemo(
    () => new Map(customers.map((c) => [c.id, c])),
    [customers],
  );

  // Color assignment: plan-snapshot salesmen first (stable name order from the
  // repo), then any visit salesman_id missing from the snapshot (legacy edge).
  const salesmanIndex = useMemo(() => {
    const index = new Map<number, { salesman: Salesman | null; color: string; order: number }>();
    if (!detail) return index;
    detail.salesmen.forEach((s, i) => {
      index.set(s.id, { salesman: s, color: salesmanColor(i), order: i });
    });
    for (const v of detail.visits) {
      if (!index.has(v.salesmanId)) {
        const order = index.size;
        index.set(v.salesmanId, { salesman: null, color: salesmanColor(order), order });
      }
    }
    return index;
  }, [detail]);

  const salesmanName = useCallback(
    (id: number): string => salesmanIndex.get(id)?.salesman?.name ?? `#${id}`,
    [salesmanIndex],
  );

  // All day-routes in the plan, before filtering — also drives the legend counts.
  const allRoutes = useMemo<DayRoute[]>(() => {
    if (!detail) return [];
    const byKey = new Map<string, DayRoute>();
    for (const v of detail.visits) {
      const key = `${v.salesmanId}|${v.scheduledDate}`;
      let route = byKey.get(key);
      if (!route) {
        route = { key, salesmanId: v.salesmanId, date: v.scheduledDate, visits: [] };
        byKey.set(key, route);
      }
      route.visits.push(v);
    }
    for (const r of byKey.values()) {
      r.visits.sort((a, b) => a.sequence - b.sequence);
    }
    return Array.from(byKey.values());
  }, [detail]);

  const weekCount = detail ? planWeekCount(detail.plan) : 0;

  const matchesWeekAndSalesman = useCallback(
    (r: DayRoute): boolean => {
      if (!detail) return false;
      if (
        weekFilter !== 'all' &&
        weekIndexOf(r.date, detail.plan.periodStart) !== weekFilter
      ) {
        return false;
      }
      return salesmanFilter === 'all' || r.salesmanId === salesmanFilter;
    },
    [detail, weekFilter, salesmanFilter],
  );

  // Day options narrow with the other two filters so the dropdown only offers
  // dates that would actually show something.
  const dayOptions = useMemo(() => {
    const dates = new Set<string>();
    for (const r of allRoutes) {
      if (matchesWeekAndSalesman(r)) dates.add(r.date);
    }
    return Array.from(dates).sort();
  }, [allRoutes, matchesWeekAndSalesman]);

  useEffect(() => {
    if (dayFilter !== 'all' && !dayOptions.includes(dayFilter)) setDayFilter('all');
  }, [dayOptions, dayFilter]);

  const displayedRoutes = useMemo(
    () =>
      allRoutes.filter(
        (r) => matchesWeekAndSalesman(r) && (dayFilter === 'all' || r.date === dayFilter),
      ),
    [allRoutes, matchesWeekAndSalesman, dayFilter],
  );

  const waypointsFor = useCallback(
    (r: DayRoute): { lat: number; lng: number }[] =>
      r.visits
        .map((v) => customerById.get(v.customerId))
        .filter((c): c is Customer => !!c && c.lat !== null && c.lng !== null)
        .map((c) => ({ lat: c.lat as number, lng: c.lng as number })),
    [customerById],
  );

  // Fetch missing OSRM geometries for the displayed routes, a few at a time.
  // Geometry only depends on (salesman, date), so results stay cached while
  // the user flips filters within the same plan.
  const pendingKeys = useMemo(
    () =>
      displayedRoutes
        .filter((r) => !geometries.has(r.key) && waypointsFor(r).length >= 2)
        .map((r) => r.key),
    [displayedRoutes, geometries, waypointsFor],
  );

  useEffect(() => {
    const toFetch = displayedRoutes.filter(
      (r) =>
        !geometries.has(r.key) && !inFlight.current.has(r.key) && waypointsFor(r).length >= 2,
    );
    if (toFetch.length === 0) return;
    const generation = planGeneration.current;
    // Mark in-flight SYNCHRONOUSLY: this effect re-runs on every geometry
    // arrival and filter flip, and workers start asynchronously — marking
    // inside the worker would let a re-run re-queue routes already fetching.
    for (const r of toFetch) inFlight.current.add(r.key);
    const queue = [...toFetch];
    const CONCURRENCY = 6;
    const worker = async () => {
      for (;;) {
        const route = queue.shift();
        if (!route) return;
        let geometry: RouteGeometry;
        try {
          geometry = await window.api.osrmRouteForWaypoints(waypointsFor(route));
        } catch {
          geometry = 'error';
        }
        inFlight.current.delete(route.key);
        if (planGeneration.current !== generation) return;
        setGeometries((prev) => {
          const next = new Map(prev);
          next.set(route.key, geometry);
          return next;
        });
      }
    };
    void Promise.all(
      Array.from({ length: Math.min(CONCURRENCY, queue.length) }, worker),
    );
  }, [displayedRoutes, geometries, waypointsFor]);

  // Legend counts respect week+day filters but NOT the salesman filter, so the
  // chips stay clickable as a salesman switcher.
  const legendEntries = useMemo(() => {
    if (!detail) return [];
    const visitCounts = new Map<number, number>();
    for (const r of allRoutes) {
      if (weekFilter !== 'all' && weekIndexOf(r.date, detail.plan.periodStart) !== weekFilter)
        continue;
      if (dayFilter !== 'all' && r.date !== dayFilter) continue;
      visitCounts.set(r.salesmanId, (visitCounts.get(r.salesmanId) ?? 0) + r.visits.length);
    }
    return Array.from(salesmanIndex.entries())
      .filter(([id]) => visitCounts.has(id))
      .sort((a, b) => a[1].order - b[1].order)
      .map(([id, entry]) => ({
        salesmanId: id,
        name: entry.salesman?.name ?? `#${id}`,
        color: entry.color,
        visits: visitCounts.get(id) ?? 0,
      }));
  }, [detail, allRoutes, salesmanIndex, weekFilter, dayFilter]);

  // Numbered stop markers only when a single day-route is on screen — with
  // many routes visible, sequence numbers are noise and 1,000+ numbered
  // divIcons drag the map.
  const singleRoute = displayedRoutes.length === 1 ? displayedRoutes[0]! : null;

  const routeCustomerColor = useMemo(() => {
    const out = new Map<number, string>();
    for (const r of displayedRoutes) {
      const color = salesmanIndex.get(r.salesmanId)?.color ?? '#2563eb';
      for (const v of r.visits) {
        out.set(v.customerId, color);
      }
    }
    return out;
  }, [displayedRoutes, salesmanIndex]);

  const allMarkers = useMemo(
    () => customers.filter((c) => c.lat !== null && c.lng !== null),
    [customers],
  );

  const summary = useMemo(() => {
    let meters = 0;
    let seconds = 0;
    let loaded = 0;
    for (const r of displayedRoutes) {
      const g = geometries.get(r.key);
      if (g && g !== 'error') {
        meters += g.totalDistanceMeters;
        seconds += g.totalDurationSeconds;
        loaded += 1;
      }
    }
    return { meters, seconds, loaded };
  }, [displayedRoutes, geometries]);

  return (
    <div className="screen map-screen">
      <header className="screen-header">
        <h2>Map</h2>
        <div className="filters">
          <span className="badge ok">{allMarkers.length.toLocaleString()} customers</span>
        </div>
      </header>

      {plans.length > 0 && (
        <div className="diag-row">
          <label>
            Plan{' '}
            <select
              value={selectedPlanId}
              onChange={(e) =>
                setSelectedPlanId(e.target.value === '' ? '' : Number(e.target.value))
              }
            >
              <option value="">— none —</option>
              {plans.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </label>
          <label style={{ marginLeft: 12 }}>
            Week{' '}
            <select
              value={weekFilter === 'all' ? 'all' : String(weekFilter)}
              onChange={(e) =>
                setWeekFilter(e.target.value === 'all' ? 'all' : Number(e.target.value))
              }
              disabled={!detail}
            >
              <option value="all">All weeks</option>
              {Array.from({ length: weekCount }, (_, i) => (
                <option key={i} value={i}>
                  Week {i + 1}
                </option>
              ))}
            </select>
          </label>
          <label style={{ marginLeft: 12 }}>
            Salesman{' '}
            <select
              value={salesmanFilter === 'all' ? 'all' : String(salesmanFilter)}
              onChange={(e) =>
                setSalesmanFilter(e.target.value === 'all' ? 'all' : Number(e.target.value))
              }
              disabled={!detail}
            >
              <option value="all">All salesmen</option>
              {detail?.salesmen.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
          </label>
          <label style={{ marginLeft: 12 }}>
            Day{' '}
            <select
              value={dayFilter}
              onChange={(e) => setDayFilter(e.target.value)}
              disabled={!detail || dayOptions.length === 0}
            >
              <option value="all">All days</option>
              {dayOptions.map((d) => (
                <option key={d} value={d}>
                  {d}
                </option>
              ))}
            </select>
          </label>
          {detail && displayedRoutes.length > 0 && (
            <small className="muted" style={{ marginLeft: 12 }}>
              {displayedRoutes.length} route{displayedRoutes.length === 1 ? '' : 's'}
              {summary.loaded > 0 && (
                <>
                  {' '}· {(summary.meters / 1000).toFixed(1)} km ·{' '}
                  {Math.round(summary.seconds / 60)} min · <em>OSRM</em>
                </>
              )}
            </small>
          )}
          {pendingKeys.length > 0 && (
            <small className="muted" style={{ marginLeft: 12 }}>
              Loading routes… ({pendingKeys.length} left)
            </small>
          )}
          {detailError && (
            <small className="result err" style={{ marginLeft: 12 }}>
              {detailError}
            </small>
          )}
        </div>
      )}

      {detail && legendEntries.length > 0 && (
        <div className="diag-row map-legend" style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
          <button
            onClick={() => setSalesmanFilter('all')}
            style={{
              fontSize: 12,
              padding: '2px 8px',
              opacity: salesmanFilter === 'all' ? 1 : 0.55,
            }}
            title="Show every salesman's routes"
          >
            All
          </button>
          {legendEntries.map((entry) => (
            <button
              key={entry.salesmanId}
              onClick={() =>
                setSalesmanFilter((cur) =>
                  cur === entry.salesmanId ? 'all' : entry.salesmanId,
                )
              }
              style={{
                fontSize: 12,
                padding: '2px 8px',
                display: 'inline-flex',
                alignItems: 'center',
                gap: 6,
                opacity:
                  salesmanFilter === 'all' || salesmanFilter === entry.salesmanId ? 1 : 0.45,
              }}
              title={`${entry.name} — ${entry.visits} visit${entry.visits === 1 ? '' : 's'} in the current week/day filter. Click to focus.`}
            >
              <span
                style={{
                  background: entry.color,
                  width: 10,
                  height: 10,
                  borderRadius: '50%',
                  display: 'inline-block',
                  flexShrink: 0,
                }}
              />
              {entry.name}
              <span className="muted">{entry.visits}</span>
            </button>
          ))}
        </div>
      )}

      <div className="map-container">
        <MapContainer
          center={[MUSCAT.lat, MUSCAT.lng]}
          zoom={7}
          style={{ width: '100%', height: '100%' }}
        >
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            maxZoom={19}
          />

          {allMarkers.map((c) => {
            const routeColor = routeCustomerColor.get(c.id);
            if (routeColor !== undefined && singleRoute) return null;
            return (
              <Marker
                key={c.id}
                position={[c.lat as number, c.lng as number]}
                icon={
                  routeColor !== undefined
                    ? makeRouteDotIcon(routeColor)
                    : routeCustomerColor.size > 0
                      ? DOT_ICON_DIMMED
                      : DOT_ICON
                }
                eventHandlers={{ click: () => setSelected(c) }}
              />
            );
          })}

          {singleRoute &&
            singleRoute.visits.map((v) => {
              const c = customerById.get(v.customerId);
              if (!c || c.lat === null || c.lng === null) return null;
              const color = salesmanIndex.get(singleRoute.salesmanId)?.color ?? '#2563eb';
              return (
                <Marker
                  key={`route-${v.id}`}
                  position={[c.lat, c.lng]}
                  icon={makeNumberedIcon(v.sequence, color)}
                  eventHandlers={{ click: () => setSelected(c) }}
                />
              );
            })}

          {displayedRoutes.map((r) => {
            const waypoints = waypointsFor(r);
            if (waypoints.length < 2) return null;
            const color = salesmanIndex.get(r.salesmanId)?.color ?? '#2563eb';
            const g = geometries.get(r.key);
            if (g && g !== 'error' && g.coordinates.length > 0) {
              return (
                <Polyline
                  key={r.key}
                  positions={g.coordinates.map((p) => [p.lat, p.lng] as [number, number])}
                  pathOptions={{
                    color,
                    weight: singleRoute ? 5 : 3,
                    opacity: singleRoute ? 0.9 : 0.75,
                  }}
                />
              );
            }
            if (g === 'error' || (g && g.coordinates.length === 0)) {
              // OSRM couldn't route this day — straight dashed legs as fallback.
              return (
                <Polyline
                  key={r.key}
                  positions={waypoints.map((p) => [p.lat, p.lng] as [number, number])}
                  pathOptions={{ color, weight: 2, opacity: 0.6, dashArray: '6 8' }}
                />
              );
            }
            return null; // still loading
          })}
        </MapContainer>
      </div>

      <CustomerSidePanel selected={selected} onClose={() => setSelected(null)} />
    </div>
  );
}
