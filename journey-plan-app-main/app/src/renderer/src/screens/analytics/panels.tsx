import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import type { PlanDistance } from '@journey/shared';
import { PRINT_MODE } from '../../printMode';
import type { AnalyticsMetrics } from './computeMetrics';
import { DAY_LABELS } from './computeMetrics';

// In the hidden PDF-export window the snapshot is taken right after first
// paint — mount animations would be frozen mid-tween, so charts render static.
const ANIMATE = !PRINT_MODE;

const COLOR_FACETIME = '#4f46e5';
const COLOR_DRIVE = '#f59e0b';
const COLOR_RED = '#dc2626';
const COLOR_BAR = '#2563eb';
const WEEK_COLORS = ['#1d4ed8', '#0891b2', '#10b981', '#a855f7', '#f97316', '#e11d48'];

function fmtMin(min: number): string {
  if (min < 60) return `${Math.round(min)} min`;
  const h = Math.floor(min / 60);
  const m = Math.round(min % 60);
  return m === 0 ? `${h}h` : `${h}h ${m}m`;
}

function fmtKm(meters: number): string {
  return `${Math.round(meters / 1000).toLocaleString()} km`;
}

export function KpiStrip({ m, distance }: { m: AnalyticsMetrics; distance?: PlanDistance | null }) {
  const tiles: { label: string; value: string; tone?: 'good' | 'warn' | 'bad'; hint?: string }[] = [
    { label: 'Visits', value: m.totals.visits.toLocaleString() },
    {
      label: 'Customers covered',
      value: `${m.totals.customersVisited.toLocaleString()} / ${m.totals.customersInDataset.toLocaleString()}`,
      tone: m.totals.coveragePct >= 99.5 ? 'good' : m.totals.coveragePct >= 90 ? 'warn' : 'bad',
    },
    {
      label: 'Coverage',
      value: `${m.totals.coveragePct.toFixed(1)}%`,
      tone: m.totals.coveragePct >= 99.5 ? 'good' : m.totals.coveragePct >= 90 ? 'warn' : 'bad',
    },
    {
      label: 'Salesmen used',
      value: `${m.totals.salesmenUsed} / ${m.totals.salesmenTotal}`,
    },
    { label: 'Working days planned', value: m.totals.distinctDaysCovered.toLocaleString() },
    {
      label: 'Avg utilization',
      value: `${m.totals.avgUtilizationPct.toFixed(1)}%`,
      tone: m.totals.avgUtilizationPct > 100 ? 'bad' : m.totals.avgUtilizationPct >= 70 ? 'good' : 'warn',
    },
    { label: 'Facetime', value: fmtMin(m.totals.facetimeMin) },
    { label: 'Drive time', value: fmtMin(m.totals.driveMin) },
    ...(distance
      ? [
          {
            label: 'Distance driven',
            value: fmtKm(distance.totalMeters),
            hint: "Summed along the solver's routing matrix (the legs the optimizer priced). The Map view sums the drawn road polyline and runs ~1–2% higher — both are correct.",
          },
        ]
      : []),
    {
      label: 'Red over-capacity visits',
      value: m.totals.redVisits.toLocaleString(),
      tone: m.totals.redVisits === 0 ? 'good' : m.totals.redVisits < 10 ? 'warn' : 'bad',
    },
    {
      label: 'Days over budget',
      value: m.totals.daysOverBudget.toLocaleString(),
      tone: m.totals.daysOverBudget === 0 ? 'good' : 'warn',
    },
  ];
  return (
    <div className="kpi-strip">
      {tiles.map((t) => (
        <div
          key={t.label}
          className={`kpi-tile ${t.tone ? `kpi-tile--${t.tone}` : ''}`}
          title={t.hint}
        >
          <div className="kpi-label">{t.label}</div>
          <div className="kpi-value">{t.value}</div>
        </div>
      ))}
    </div>
  );
}

export function LoadUtilizationPanel({
  m,
  distance,
}: {
  m: AnalyticsMetrics;
  distance?: PlanDistance | null;
}) {
  const data = m.perSalesman.map((p) => ({
    name: p.name,
    facetime: Math.round(p.facetimeMin),
    drive: Math.round(p.driveMin),
    capacity: Math.round(p.capacityMin),
    utilization: Math.round(p.utilizationPct * 10) / 10,
  }));
  const metersBySalesman = new Map(
    (distance?.perSalesman ?? []).map((d) => [d.salesmanId, d.meters]),
  );
  return (
    <ChartCard
      title="Per-salesman load (facetime + drive vs capacity)"
      subtitle="Stacked minutes per salesman: blue = facetime, orange = drive. Capacity and utilization against it are in the table below."
    >
      <ResponsiveContainer width="100%" height={Math.max(220, data.length * 36 + 60)}>
        <BarChart data={data} layout="vertical" margin={{ left: 80, right: 24, top: 8, bottom: 8 }}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis type="number" tickFormatter={(v) => `${Math.round(v / 60)}h`} />
          <YAxis type="category" dataKey="name" width={140} />
          <Tooltip formatter={(v) => fmtMin(Number(v))} />
          <Legend />
          <Bar dataKey="facetime" name="Facetime" stackId="load" fill={COLOR_FACETIME} isAnimationActive={ANIMATE} />
          <Bar dataKey="drive" name="Drive" stackId="load" fill={COLOR_DRIVE} isAnimationActive={ANIMATE} />
        </BarChart>
      </ResponsiveContainer>
      <table className="util-table">
        <thead>
          <tr>
            <th>Salesman</th>
            <th>Region</th>
            <th>Visits</th>
            <th>Load</th>
            <th>Capacity</th>
            <th>Utilization</th>
            {distance && <th>Distance</th>}
            <th>Days worked</th>
            <th>Red</th>
            <th>Over budget</th>
          </tr>
        </thead>
        <tbody>
          {m.perSalesman.map((p) => (
            <tr key={p.salesmanId}>
              <td>{p.name}</td>
              <RegionCell region={p.region} all={p.regionsCovered} />
              <td>{p.visits}</td>
              <td>{fmtMin(p.loadMin)}</td>
              <td>{fmtMin(p.capacityMin)}</td>
              <td>
                <span className={utilToneClass(p.utilizationPct)}>{p.utilizationPct.toFixed(1)}%</span>
              </td>
              {distance && (
                <td>{metersBySalesman.has(p.salesmanId) ? fmtKm(metersBySalesman.get(p.salesmanId)!) : '—'}</td>
              )}
              <td>
                {p.daysWorked} / {p.daysAvailable}
              </td>
              <td className={p.redVisits > 0 ? 'cell-bad' : ''}>{p.redVisits}</td>
              <td className={p.daysOverBudget > 0 ? 'cell-warn' : ''}>{p.daysOverBudget}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {distance && (
        <p className="muted" style={{ marginTop: 8, fontSize: 11 }}>
          Distance is summed along the solver&apos;s routing matrix — the legs the optimizer
          priced. The Map view measures the drawn road polyline and runs ~1–2% higher; both are
          correct.
          {distance.legsMissing > 0 && (
            <>
              {' '}
              {distance.legsMissing.toLocaleString()} leg
              {distance.legsMissing === 1 ? '' : 's'} not in the distance cache were excluded —
              regenerate the plan to refresh.
            </>
          )}
        </p>
      )}
    </ChartCard>
  );
}

// A salesman can straddle regions; show the dominant one and how many others,
// with the full list on hover so the column stays one line wide in the PDF.
function RegionCell({ region, all }: { region: string; all: string[] }) {
  const extra = all.length > 1 ? ` +${all.length - 1}` : '';
  return (
    <td className="cell-region" title={all.length > 1 ? all.join(', ') : undefined}>
      {region}
      {extra}
    </td>
  );
}

function utilToneClass(pct: number): string {
  if (pct > 100) return 'cell-bad';
  if (pct >= 70) return 'cell-good';
  return 'cell-warn';
}

export function WeekBalancePanel({ m }: { m: AnalyticsMetrics }) {
  const data = m.weekBalance.map((wb) => {
    const row: Record<string, string | number> = { name: wb.name, ratio: Number(wb.maxMinRatio.toFixed(2)) };
    wb.weeks.forEach((n, i) => {
      row[`W${i + 1}`] = n;
    });
    return row;
  });
  return (
    <ChartCard
      title="Week-by-week visit balance per salesman"
      subtitle={`Lower max/min ratio = flatter week distribution. ${m.weekCount}-week period.`}
    >
      <ResponsiveContainer width="100%" height={Math.max(220, data.length * 32 + 60)}>
        <BarChart data={data} layout="vertical" margin={{ left: 80, right: 24, top: 8, bottom: 8 }}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis type="number" />
          <YAxis type="category" dataKey="name" width={140} />
          <Tooltip />
          <Legend />
          {Array.from({ length: m.weekCount }).map((_, i) => (
            <Bar
              key={i}
              dataKey={`W${i + 1}`}
              fill={WEEK_COLORS[i % WEEK_COLORS.length]}
              name={`Week ${i + 1}`}
              isAnimationActive={ANIMATE}
            />
          ))}
        </BarChart>
      </ResponsiveContainer>
      <table className="util-table">
        <thead>
          <tr>
            <th>Salesman</th>
            <th>Region</th>
            {Array.from({ length: m.weekCount }).map((_, i) => (
              <th key={i}>W{i + 1}</th>
            ))}
            <th>max/min</th>
          </tr>
        </thead>
        <tbody>
          {m.weekBalance.map((wb) => (
            <tr key={wb.salesmanId}>
              <td>{wb.name}</td>
              <RegionCell region={wb.region} all={wb.regionsCovered} />
              {wb.weeks.map((n, i) => (
                <td key={i}>{n}</td>
              ))}
              <td className={wb.maxMinRatio > 1.5 ? 'cell-bad' : wb.maxMinRatio > 1.2 ? 'cell-warn' : 'cell-good'}>
                {wb.maxMinRatio > 0 ? wb.maxMinRatio.toFixed(2) + '×' : '—'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </ChartCard>
  );
}

export function DowHeatmapPanel({ m }: { m: AnalyticsMetrics }) {
  // Build a (salesmanId × dow) -> visits / load lookup
  const byKey = new Map<string, { visits: number; loadMin: number }>();
  for (const c of m.dowHeatmap) {
    byKey.set(`${c.salesmanId}:${c.dow}`, { visits: c.visits, loadMin: c.loadMin });
  }
  const maxLoad = m.dowHeatmap.reduce((mx, c) => Math.max(mx, c.loadMin), 0);
  return (
    <ChartCard
      title="Day-of-week heatmap (load in minutes)"
      subtitle="Darker = more minutes scheduled on that DOW. Helps spot Thursday-piling."
    >
      <div className="dow-heatmap">
        <div className="dow-row dow-row--header">
          <div className="dow-cell dow-cell--label" />
          {DAY_LABELS.map((d) => (
            <div key={d} className="dow-cell dow-cell--header">
              {d}
            </div>
          ))}
        </div>
        {m.perSalesman.map((p) => (
          <div key={p.salesmanId} className="dow-row">
            <div className="dow-cell dow-cell--label" title={`${p.name} · ${p.region}`}>
              {p.name}
            </div>
            {DAY_LABELS.map((_, dow) => {
              const cell = byKey.get(`${p.salesmanId}:${dow}`);
              const load = cell?.loadMin ?? 0;
              const intensity = maxLoad > 0 ? load / maxLoad : 0;
              return (
                <div
                  key={dow}
                  className="dow-cell"
                  style={{
                    background:
                      intensity === 0
                        ? '#f3f4f6'
                        : `rgba(37, 99, 235, ${(0.15 + intensity * 0.75).toFixed(3)})`,
                    color: intensity > 0.55 ? 'white' : '#111827',
                  }}
                  title={`${p.name} · ${DAY_LABELS[dow]} · ${cell?.visits ?? 0} visits · ${fmtMin(load)}`}
                >
                  {cell ? cell.visits : ''}
                </div>
              );
            })}
          </div>
        ))}
      </div>
    </ChartCard>
  );
}

export function FacetimeDrivePanel({ m }: { m: AnalyticsMetrics }) {
  const total = m.totals.facetimeMin + m.totals.driveMin;
  const facetimePct = total > 0 ? (m.totals.facetimeMin / total) * 100 : 0;
  return (
    <ChartCard
      title="Facetime vs drive time (group total)"
      subtitle={`${facetimePct.toFixed(1)}% productive (on-site) · ${(100 - facetimePct).toFixed(1)}% drive.`}
    >
      <ResponsiveContainer width="100%" height={260}>
        <PieChart>
          <Pie
            data={m.facetimeVsDrive}
            dataKey="value"
            nameKey="name"
            outerRadius={100}
            innerRadius={55}
            label={(p) => `${p.name}: ${((p.percent ?? 0) * 100).toFixed(1)}%`}
            isAnimationActive={ANIMATE}
          >
            {m.facetimeVsDrive.map((entry, i) => (
              <Cell key={i} fill={entry.name === 'Facetime' ? COLOR_FACETIME : COLOR_DRIVE} />
            ))}
          </Pie>
          <Tooltip formatter={(v) => fmtMin(Number(v))} />
          <Legend />
        </PieChart>
      </ResponsiveContainer>
    </ChartCard>
  );
}

export function FrequencyPanel({ m }: { m: AnalyticsMetrics }) {
  // Bucket deltas into ±0,1,2,3+
  const buckets = new Map<string, number>();
  const order = ['-3 or less', '-2', '-1', '+1', '+2', '+3 or more'];
  for (const r of m.frequency.perCustomer) {
    let key: string;
    if (r.delta <= -3) key = '-3 or less';
    else if (r.delta === -2) key = '-2';
    else if (r.delta === -1) key = '-1';
    else if (r.delta === 1) key = '+1';
    else if (r.delta === 2) key = '+2';
    else key = '+3 or more';
    buckets.set(key, (buckets.get(key) ?? 0) + 1);
  }
  const data = order.map((k) => ({ band: k, count: buckets.get(k) ?? 0 }));
  const noMismatches = m.frequency.perCustomer.length === 0;
  return (
    <ChartCard
      title="Frequency conformance"
      subtitle={`${m.frequency.exactCount.toLocaleString()} customers got exactly their requested visits · ${m.frequency.overCount} over · ${m.frequency.underCount} under.`}
    >
      {noMismatches ? (
        <p className="muted" style={{ padding: 16 }}>
          Perfect conformance — every visited customer received exactly its requested monthly
          frequency.
        </p>
      ) : (
      <ResponsiveContainer width="100%" height={220}>
        <BarChart data={data} margin={{ left: 8, right: 16, top: 8, bottom: 8 }}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis dataKey="band" />
          <YAxis />
          <Tooltip />
          <Bar dataKey="count" fill={COLOR_BAR} isAnimationActive={ANIMATE}>
            {data.map((d, i) => (
              <Cell key={i} fill={d.band.startsWith('-') ? COLOR_RED : COLOR_FACETIME} />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>
      )}
      {m.frequency.perCustomer.length > 0 && (
        <details className="freq-details" open={PRINT_MODE}>
          <summary>{m.frequency.perCustomer.length} mismatched customers (top 20 by |delta|)</summary>
          <table className="util-table">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Got</th>
                <th>Want</th>
                <th>Δ</th>
              </tr>
            </thead>
            <tbody>
              {m.frequency.perCustomer.slice(0, 20).map((r) => (
                <tr key={r.customerId}>
                  <td>{r.name}</td>
                  <td>{r.got}</td>
                  <td>{r.want}</td>
                  <td className={r.delta < 0 ? 'cell-bad' : 'cell-warn'}>{r.delta > 0 ? `+${r.delta}` : r.delta}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </details>
      )}
    </ChartCard>
  );
}

export function RedVisitsPanel({ m }: { m: AnalyticsMetrics }) {
  const data = m.perSalesman
    .filter((p) => p.redVisits > 0)
    .map((p) => ({ name: p.name, redVisits: p.redVisits, daysOverBudget: p.daysOverBudget }));
  if (data.length === 0) {
    return (
      <ChartCard title="Over-capacity (red) visits per salesman" subtitle="Visits whose start + facetime cross the salesman's working_hours_end.">
        <p className="muted" style={{ padding: 16 }}>No over-capacity visits. All visits finish within the configured working window.</p>
      </ChartCard>
    );
  }
  return (
    <ChartCard
      title="Over-capacity (red) visits per salesman"
      subtitle="Visits whose start + facetime cross the salesman's working_hours_end. Drag them in Plan to free capacity."
    >
      <ResponsiveContainer width="100%" height={Math.max(200, data.length * 32 + 60)}>
        <BarChart data={data} layout="vertical" margin={{ left: 80, right: 24, top: 8, bottom: 8 }}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis type="number" allowDecimals={false} />
          <YAxis type="category" dataKey="name" width={140} />
          <Tooltip />
          <Bar dataKey="redVisits" name="Red visits" fill={COLOR_RED} isAnimationActive={ANIMATE} />
        </BarChart>
      </ResponsiveContainer>
    </ChartCard>
  );
}

export function SolverHealthPanel({ m, solverLog }: { m: AnalyticsMetrics; solverLog: NonNullable<import('@journey/shared').PlanWithVisits['solverLog']> }) {
  const cached = solverLog.matrixCellsCached;
  const fetched = solverLog.matrixCellsRequested;
  const hitPct = fetched + cached > 0 ? (cached / (cached + fetched)) * 100 : 0;
  return (
    <ChartCard title="Solver health" subtitle="From the solver_log JSON written when the plan was generated.">
      <div className="kpi-strip">
        <div className="kpi-tile">
          <div className="kpi-label">Status</div>
          <div className="kpi-value">{solverLog.solverStatus}</div>
        </div>
        <div className="kpi-tile">
          <div className="kpi-label">Runtime</div>
          <div className="kpi-value">{solverLog.runtimeSeconds.toFixed(1)}s</div>
        </div>
        <div className="kpi-tile">
          <div className="kpi-label">Objective</div>
          <div className="kpi-value">{Math.round(solverLog.objectiveValue).toLocaleString()}</div>
        </div>
        <div className="kpi-tile">
          <div className="kpi-label">Matrix cache hit</div>
          <div className="kpi-value">{hitPct.toFixed(0)}%</div>
        </div>
        <div className="kpi-tile">
          <div className="kpi-label">Cells fetched</div>
          <div className="kpi-value">{fetched.toLocaleString()}</div>
        </div>
        <div className="kpi-tile">
          <div className="kpi-label">Cells cached</div>
          <div className="kpi-value">{cached.toLocaleString()}</div>
        </div>
      </div>
      {(solverLog.unassignedCustomers.length > 0 || solverLog.skippedCustomers.length > 0) && (
        <p className="muted" style={{ marginTop: 12 }}>
          {solverLog.skippedCustomers.length + solverLog.unassignedCustomers.length} customers
          were not visited (see Plan screen for the list — this should normally be zero post-Phase 11).
        </p>
      )}
      {/* Suppress unused-warning while keeping the prop for future drill-downs */}
      <span style={{ display: 'none' }}>{m.totals.visits}</span>
    </ChartCard>
  );
}

function ChartCard({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle?: string;
  children: React.ReactNode;
}) {
  return (
    <section className="chart-card">
      <header>
        <h3>{title}</h3>
        {subtitle && <p className="muted">{subtitle}</p>}
      </header>
      <div className="chart-card-body">{children}</div>
    </section>
  );
}
