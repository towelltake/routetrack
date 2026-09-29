import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import type {
  Customer,
  JourneyPlan,
  OsrmHealthResult,
  PlanWithVisits,
  Salesman,
  SuggestTeamSizeResponse,
  TeamSizeTemplate,
  Visit,
} from '@journey/shared';
import { PlanCalendarGrid } from './PlanCalendarGrid';
import { dowOf } from './planMath';
import { usePlanProgress } from '../contexts/PlanProgressContext';

// A plan is always 4 calendar weeks: cycle_assignment.py hard-codes NUM_WEEKS=4
// and assemble.py reads only periodStart, so periodEnd is a label. It is kept
// derived (and the input read-only) so the dates shown can never disagree with
// the visits actually generated.
function plus27Days(startIso: string): string {
  const [y, m, d] = startIso.split('-').map(Number);
  if (!y || !m || !d) return startIso;
  const end = new Date(y, m - 1, d + 27);
  return `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`;
}

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

interface SalesmanLookup {
  [id: number]: Salesman;
}

interface CustomerLookup {
  [id: number]: Customer;
}

export function PlanScreen() {
  const navigate = useNavigate();
  const [period, setPeriod] = useState<{ periodStart: string; periodEnd: string } | null>(null);
  const [planName, setPlanName] = useState('');
  const [plans, setPlans] = useState<JourneyPlan[]>([]);
  const [selectedPlanId, setSelectedPlanId] = useState<number | null>(null);
  const [detail, setDetail] = useState<PlanWithVisits | null>(null);
  const [salesmen, setSalesmen] = useState<Salesman[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  // generating + progress are hoisted to PlanProgressContext so they survive
  // navigation away from this screen during a multi-minute solve.
  const { progress, generating, beginGeneration, endGeneration } = usePlanProgress();
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);
  const [suggestOpen, setSuggestOpen] = useState(false);
  const [statusBusy, setStatusBusy] = useState(false);
  const [exportBusy, setExportBusy] = useState(false);
  const [selectedVisit, setSelectedVisit] = useState<Visit | null>(null);
  const [osrm, setOsrm] = useState<OsrmHealthResult | null>(null);

  useEffect(() => {
    void window.api.defaultPeriod().then(setPeriod);
    void reloadPlans();
    void window.api.listSalesmen().then(setSalesmen);
    void window.api.listCustomers({ limit: 100000 }).then(setCustomers);
  }, []);

  useEffect(() => {
    let cancelled = false;
    const check = () => {
      void window.api.osrmHealth().then((r) => {
        if (!cancelled) setOsrm(r);
      });
    };
    check();
    const id = window.setInterval(check, 30_000);
    return () => {
      cancelled = true;
      window.clearInterval(id);
    };
  }, []);

  const reloadPlans = async () => {
    const list = await window.api.listPlans();
    setPlans(list);
    // Select against the list we just fetched rather than the `selectedPlanId`
    // captured when this closure was created: after deleting the selected plan
    // the stale value is still non-null, so nothing was re-selected and the
    // detail pane fell back to the empty state with plans still in the list.
    setSelectedPlanId((current) => {
      if (current !== null && list.some((p) => p.id === current)) return current;
      return list.length > 0 ? list[0]!.id : null;
    });
  };

  const deletePlan = async (plan: JourneyPlan) => {
    if (plan.status === 'final') {
      setError('Cannot delete a final plan. Unlock it first.');
      return;
    }
    const ok = window.confirm(
      `Delete "${plan.name}"? This removes the plan and all its visits. Cannot be undone.`,
    );
    if (!ok) return;
    setError(null);
    try {
      await window.api.deletePlan(plan.id);
      if (selectedPlanId === plan.id) setSelectedPlanId(null);
      await reloadPlans();
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    }
  };

  const reloadDetail = async () => {
    if (selectedPlanId === null) return;
    const d = await window.api.getPlan(selectedPlanId);
    setDetail(d);
  };

  useEffect(() => {
    if (selectedPlanId === null) {
      setDetail(null);
      return;
    }
    void reloadDetail();
  }, [selectedPlanId]);

  useEffect(() => {
    if (!planName && period) {
      setPlanName(`Plan ${period.periodStart}`);
    }
  }, [period]);

  // Resolve visits against the PLAN's salesman snapshot, not the live roster —
  // a salesman deleted from the roster still labels his visits in old plans.
  const salesmanLookup: SalesmanLookup = useMemo(
    () => Object.fromEntries((detail?.salesmen ?? []).map((s) => [s.id, s])),
    [detail],
  );

  const customerLookup: CustomerLookup = useMemo(
    () => Object.fromEntries(customers.map((c) => [c.id, c])),
    [customers],
  );

  const customerNames = useMemo(
    () => Object.fromEntries(customers.map((c) => [c.id, c.name])),
    [customers],
  );

  const customerAddresses = useMemo(
    () => Object.fromEntries(customers.map((c) => [c.id, c.address ?? ''])),
    [customers],
  );

  const noSalesmen = salesmen.length === 0;
  const isFinal = detail?.plan.status === 'final';

  const generate = async () => {
    if (!period) return;
    beginGeneration();
    setError(null);
    setInfo(null);
    try {
      const { planId } = await window.api.generatePlan({
        name: planName.trim() || `Plan ${period.periodStart}`,
        periodStart: period.periodStart,
        periodEnd: period.periodEnd,
      });
      setSelectedPlanId(planId);
      await reloadPlans();
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      endGeneration();
    }
  };

  const toggleStatus = async () => {
    if (!detail) return;
    setStatusBusy(true);
    setError(null);
    try {
      const next = detail.plan.status === 'final' ? 'draft' : 'final';
      await window.api.setPlanStatus(detail.plan.id, next);
      await reloadDetail();
      await reloadPlans();
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setStatusBusy(false);
    }
  };

  const exportExcel = async () => {
    if (!detail) return;
    setExportBusy(true);
    setError(null);
    setInfo(null);
    try {
      const r = await window.api.exportPlanExcel(detail.plan.id);
      if (r) setInfo(`Exported to ${r.savedPath}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setExportBusy(false);
    }
  };

  if (!period) {
    return (
      <div className="screen plan-screen">
        <p className="muted">Loading…</p>
      </div>
    );
  }

  return (
    <div className="screen">
      <header className="screen-header">
        <div className="screen-title-row">
          <h2>Journey plans</h2>
          <OsrmBadge health={osrm} />
        </div>
        <div className="filters">
          <label>
            Plan name
            <input
              value={planName}
              onChange={(e) => setPlanName(e.target.value)}
              style={{ width: 200, marginLeft: 8 }}
            />
          </label>
          <label>
            From
            <input
              type="date"
              value={period.periodStart}
              onChange={(e) =>
                setPeriod((p) =>
                  p
                    ? { ...p, periodStart: e.target.value, periodEnd: plus27Days(e.target.value) }
                    : p,
                )
              }
              style={{ marginLeft: 8 }}
            />
          </label>
          <label title="A plan is always exactly 4 weeks — the solver schedules 4 one-week cycles from the start date. Editing this independently used to be possible but had no effect on the visits generated.">
            To
            <input
              type="date"
              value={period.periodEnd}
              readOnly
              disabled
              style={{ marginLeft: 8 }}
            />
            <span className="muted" style={{ marginLeft: 6, fontSize: 11 }}>
              (4 weeks)
            </span>
          </label>
          <button onClick={generate} disabled={generating || noSalesmen}>
            {generating ? 'Generating…' : 'Generate plan'}
          </button>
          <button onClick={() => setSuggestOpen(true)} disabled={generating}>
            Suggest team size
          </button>
          <button onClick={exportExcel} disabled={!detail || exportBusy}>
            {exportBusy ? 'Exporting…' : 'Export to Excel'}
          </button>
          <button
            onClick={() => detail && navigate(`/analytics?planId=${detail.plan.id}`)}
            disabled={!detail}
          >
            View analytics
          </button>
          <button onClick={toggleStatus} disabled={!detail || statusBusy}>
            {statusBusy ? 'Saving…' : isFinal ? 'Unlock' : 'Lock as final'}
          </button>
        </div>
      </header>

      {noSalesmen && (
        <p className="muted">
          No salesmen configured. Add at least one on the Salesmen screen.
        </p>
      )}
      {error && <p className="result err">{error}</p>}
      {info && <p className="result">{info}</p>}
      {generating && progress && (
        <div className="progress-block">
          <div className="progress-row">
            <span>{progress.message}</span>
            <span className="muted">{progress.percent}%</span>
          </div>
          <div className="progress-bar">
            <div className="progress-bar-fill" style={{ width: `${progress.percent}%` }} />
          </div>
        </div>
      )}

      <div style={{ display: 'flex', gap: 16 }}>
        <aside style={{ width: 240, flexShrink: 0 }}>
          <h3>Plans</h3>
          {plans.length === 0 && <p className="muted">No plans yet.</p>}
          <ul style={{ listStyle: 'none', padding: 0 }}>
            {plans.map((p) => (
              <li key={p.id} style={{ marginBottom: 6, display: 'flex', gap: 4 }}>
                <button
                  onClick={() => setSelectedPlanId(p.id)}
                  style={{
                    flex: 1,
                    textAlign: 'left',
                    background: p.id === selectedPlanId ? '#eef2ff' : 'transparent',
                  }}
                >
                  <strong>{p.name}</strong>{' '}
                  <span className={`badge status-${p.status}`}>{p.status}</span>
                  <br />
                  <small className="muted">
                    {p.periodStart} → {p.periodEnd}
                  </small>
                </button>
                <button
                  onClick={() => deletePlan(p)}
                  className="plan-delete-btn"
                  title={
                    p.status === 'final'
                      ? 'Unlock the plan before deleting'
                      : 'Delete this plan and all its visits'
                  }
                  disabled={p.status === 'final'}
                >
                  ×
                </button>
              </li>
            ))}
          </ul>
        </aside>

        <section style={{ flex: 1, minWidth: 0 }}>
          {detail ? (
            <PlanDetail
              detail={detail}
              salesmanLookup={salesmanLookup}
              customerNames={customerNames}
              customerAddresses={customerAddresses}
              readOnly={isFinal}
              onMoved={reloadDetail}
              onSelectVisit={setSelectedVisit}
            />
          ) : (
            <p className="muted">Select a plan on the left, or generate one above.</p>
          )}
        </section>
      </div>

      {selectedVisit && (
        <VisitDetailPanel
          visit={selectedVisit}
          customer={customerLookup[selectedVisit.customerId] ?? null}
          salesman={salesmanLookup[selectedVisit.salesmanId] ?? null}
          onClose={() => setSelectedVisit(null)}
        />
      )}

      {suggestOpen && (
        <SuggestTeamSizeDialog
          onClose={() => setSuggestOpen(false)}
          onCreated={() => {
            void window.api.listSalesmen().then(setSalesmen);
          }}
        />
      )}
    </div>
  );
}

interface PlanDetailProps {
  detail: PlanWithVisits;
  salesmanLookup: SalesmanLookup;
  customerNames: Record<number, string>;
  customerAddresses: Record<number, string>;
  readOnly: boolean;
  onMoved: () => void;
  onSelectVisit: (v: Visit) => void;
}

function PlanDetail({
  detail,
  salesmanLookup,
  customerNames,
  customerAddresses,
  readOnly,
  onMoved,
  onSelectVisit,
}: PlanDetailProps) {
  const log = detail.solverLog;

  return (
    <div>
      <div style={{ marginBottom: 12, display: 'flex', gap: 12, alignItems: 'center' }}>
        <span className={`badge status-${detail.plan.status}`}>{detail.plan.status}</span>
        {log && (
          <span style={{ fontSize: 13 }}>
            Status: <strong>{log.solverStatus}</strong> · {detail.visits.length} visits ·{' '}
            {Math.round(log.runtimeSeconds * 10) / 10}s · matrix:{' '}
            {log.matrixCellsRequested} fetched / {log.matrixCellsCached} cached
          </span>
        )}
      </div>

      <PlanCalendarGrid
        detail={detail}
        salesmanLookup={salesmanLookup}
        customerNames={customerNames}
        customerAddresses={customerAddresses}
        readOnly={readOnly}
        onMoved={onMoved}
        onSelectVisit={onSelectVisit}
      />

      {log && (log.unassignedCustomers.length > 0 || log.skippedCustomers.length > 0) && (
        <details style={{ marginTop: 16 }}>
          <summary>
            Skipped customers ({log.unassignedCustomers.length + log.skippedCustomers.length})
          </summary>
          <ul>
            {log.skippedCustomers.map((s) => (
              <li key={`sk-${s.customerId}`}>
                {customerNames[s.customerId] ?? `#${s.customerId}`}: {s.reason}
              </li>
            ))}
            {log.unassignedCustomers.map((u) => (
              <li key={`un-${u.customerId}`}>
                {customerNames[u.customerId] ?? `#${u.customerId}`}: {u.reason}
              </li>
            ))}
          </ul>
        </details>
      )}
    </div>
  );
}

interface VisitDetailPanelProps {
  visit: Visit;
  customer: Customer | null;
  salesman: Salesman | null;
  onClose: () => void;
}

function VisitDetailPanel({ visit, customer, salesman, onClose }: VisitDetailPanelProps) {
  return (
    <aside className="side-panel">
      <header>
        <h3>{customer?.name ?? `Customer #${visit.customerId}`}</h3>
        <button onClick={onClose}>×</button>
      </header>
      <dl>
        <dt>Salesman</dt>
        <dd>{salesman?.name ?? `#${visit.salesmanId}`}</dd>
        <dt>Date</dt>
        <dd>
          {visit.scheduledDate} ({DAY_LABELS[dowOf(visit.scheduledDate)]})
        </dd>
        <dt>Sequence</dt>
        <dd>#{visit.sequence}</dd>
        <dt>Start time</dt>
        <dd>{visit.scheduledStartTime}</dd>
        <dt>Drive to</dt>
        <dd>{visit.driveMinutesTo ?? '—'} min</dd>
        <dt>Facetime</dt>
        <dd>{visit.facetimeMinutes ?? '—'} min</dd>
        {customer && (
          <>
            <dt>Code</dt>
            <dd>{customer.externalCode}</dd>
            <dt>Address</dt>
            <dd>{customer.address ?? '—'}</dd>
            <dt>Area</dt>
            <dd>{customer.area ?? '—'}</dd>
          </>
        )}
      </dl>
    </aside>
  );
}

interface SuggestDialogProps {
  onClose: () => void;
  onCreated: () => void;
}

function SuggestTeamSizeDialog({ onClose, onCreated }: SuggestDialogProps) {
  const [template, setTemplate] = useState<TeamSizeTemplate>({
    workingDays: [0, 1, 2, 3, 4],
    workingHoursStart: '08:00',
    workingHoursEnd: '17:00',
    maxCustomersPerDay: null,
    targetUtilizationPct: 85,
  });
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<SuggestTeamSizeResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  const toggleDay = (d: number) => {
    setTemplate((t) => {
      const set = new Set(t.workingDays);
      if (set.has(d)) set.delete(d);
      else set.add(d);
      return { ...t, workingDays: Array.from(set).sort() };
    });
  };

  const run = async () => {
    setBusy(true);
    setError(null);
    try {
      const r = await window.api.suggestTeamSize(template);
      setResult(r);
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setBusy(false);
    }
  };

  const createRoster = async () => {
    if (!result) return;
    setBusy(true);
    setError(null);
    let created = 0;
    try {
      // Two suggested salesmen may share an area (e.g. 3 salesmen for "Muscat").
      // Number the per-area duplicates so each salesman has a unique name —
      // otherwise the Excel export collides on sheet names.
      const perAreaCount = new Map<string, number>();
      const perAreaSeen = new Map<string, number>();
      for (const s of result.recommendedSalesmen) {
        perAreaCount.set(
          s.suggestedAreaLabel,
          (perAreaCount.get(s.suggestedAreaLabel) ?? 0) + 1,
        );
      }
      for (const s of result.recommendedSalesmen) {
        const seen = (perAreaSeen.get(s.suggestedAreaLabel) ?? 0) + 1;
        perAreaSeen.set(s.suggestedAreaLabel, seen);
        const total = perAreaCount.get(s.suggestedAreaLabel) ?? 1;
        const suffix = total > 1 ? ` #${seen}` : '';
        await window.api.upsertSalesman({
          id: 0,
          name: `Suggested — ${s.suggestedAreaLabel}${suffix}`,
          startLocationLat: s.suggestedHomeLat,
          startLocationLng: s.suggestedHomeLng,
          workingDays: template.workingDays,
          workingHoursStart: template.workingHoursStart,
          workingHoursEnd: template.workingHoursEnd,
          assignedAreas: s.suggestedAreaLabel.startsWith('Cluster ') ? [] : [s.suggestedAreaLabel],
          assignedRegions: [],
          channelSkills: [],
          includeCommute: false,
        });
        created += 1;
      }
      onCreated();
      onClose();
    } catch (err) {
      // Without this the dialog stayed open showing the same result after a
      // partial failure, so clicking "Create N salesmen" again duplicated
      // everyone who had already been written.
      const reason = err instanceof Error ? err.message : String(err);
      setError(
        created > 0
          ? `Created ${created} of ${result.recommendedSalesmen.length} salesmen, then failed: ${reason}. Check the Salesmen screen before retrying — retrying now would duplicate the ones already created.`
          : `Could not create the roster: ${reason}`,
      );
      onCreated();
    } finally {
      setBusy(false);
    }
  };

  return (
    <aside className="side-panel" style={{ width: 480 }}>
      <header>
        <h3>Suggest team size</h3>
        <button onClick={onClose}>×</button>
      </header>

      <fieldset className="day-picker">
        <legend>Working days</legend>
        {DAY_LABELS.map((label, value) => (
          <label key={value} className="day-toggle">
            <input
              type="checkbox"
              checked={template.workingDays.includes(value)}
              onChange={() => toggleDay(value)}
            />
            {label}
          </label>
        ))}
      </fieldset>

      <div className="row">
        <label>
          Start
          <input
            type="time"
            value={template.workingHoursStart}
            onChange={(e) =>
              setTemplate((t) => ({ ...t, workingHoursStart: e.target.value }))
            }
          />
        </label>
        <label>
          End
          <input
            type="time"
            value={template.workingHoursEnd}
            onChange={(e) =>
              setTemplate((t) => ({ ...t, workingHoursEnd: e.target.value }))
            }
          />
        </label>
      </div>

      <label style={{ display: 'block', marginTop: 12 }}>
        Target utilization: <strong>{template.targetUtilizationPct ?? 85}%</strong>
        <input
          type="range"
          min={50}
          max={100}
          step={5}
          value={template.targetUtilizationPct ?? 85}
          onChange={(e) =>
            setTemplate((t) => ({ ...t, targetUtilizationPct: Number(e.target.value) }))
          }
          style={{ width: '100%', marginTop: 4 }}
        />
        <small className="muted">
          Higher = fewer, busier salesmen (some customers may be flagged unassigned).
          Lower = more salesmen, lower per-head workload.
        </small>
      </label>

      <div className="panel-actions">
        <button onClick={run} disabled={busy}>
          {busy ? 'Calculating…' : 'Calculate'}
        </button>
      </div>

      {error && <p className="result err">{error}</p>}

      {result && (
        <div style={{ marginTop: 16 }}>
          <p>
            <strong>Recommended: {result.recommendedSalesmen.length}</strong> salesmen ·{' '}
            {result.runtimeSeconds.toFixed(1)}s · status: {result.solverStatus}
          </p>
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Area</th>
                <th>Customers</th>
                <th>Home (lat, lng)</th>
              </tr>
            </thead>
            <tbody>
              {result.recommendedSalesmen.map((s) => (
                <tr key={s.index}>
                  <td>{s.index}</td>
                  <td>{s.suggestedAreaLabel}</td>
                  <td>{s.assignedCustomerIds.length}</td>
                  <td>
                    {s.suggestedHomeLat.toFixed(4)}, {s.suggestedHomeLng.toFixed(4)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {result.unassignedCustomers.length > 0 && (
            <p className="muted">
              {result.unassignedCustomers.length} customers could not be assigned.
            </p>
          )}
          <div className="panel-actions" style={{ marginTop: 12 }}>
            <button onClick={createRoster} disabled={busy}>
              {busy ? 'Creating…' : `Create ${result.recommendedSalesmen.length} salesmen`}
            </button>
          </div>
        </div>
      )}
    </aside>
  );
}

function OsrmBadge({ health }: { health: OsrmHealthResult | null }) {
  if (health === null) {
    return (
      <span className="osrm-badge osrm-badge--unknown" title="Checking OSRM…">
        <span className="osrm-dot" /> OSRM checking…
      </span>
    );
  }
  if (!health.ok) {
    // Distinguish the transient sidecar-boot window (yellow, expected on
    // first launch) from a real error talking to the sidecar (red).
    if (health.reason === 'sidecar-not-ready') {
      return (
        <span
          className="osrm-badge osrm-badge--unknown"
          title="The Python sidecar is still starting up. OSRM status will appear shortly."
        >
          <span className="osrm-dot" /> Initialising…
        </span>
      );
    }
    return (
      <span className="osrm-badge osrm-badge--down" title={health.error}>
        <span className="osrm-dot" /> Sidecar error
      </span>
    );
  }
  if (!health.reachable) {
    const detail = health.lastFailureReason
      ? `${health.lastFailureReason}\n\nSidecar cannot reach ${health.baseUrl}.`
      : `Sidecar cannot reach ${health.baseUrl}. Run 'pnpm osrm:setup' or check osrm-routed.`;
    return (
      <span className="osrm-badge osrm-badge--down" title={detail}>
        <span className="osrm-dot" /> OSRM down — plans will fail
      </span>
    );
  }
  return (
    <span
      className="osrm-badge osrm-badge--up"
      title={`Matrix backend live at ${health.baseUrl}. Solver drive-times come from self-hosted OSRM (no Google Distance Matrix billing).`}
    >
      <span className="osrm-dot" /> OSRM live
    </span>
  );
}
