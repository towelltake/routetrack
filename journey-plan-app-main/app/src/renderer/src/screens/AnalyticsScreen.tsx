import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import type { Customer, JourneyPlan, PlanDistance, PlanWithVisits } from '@journey/shared';
import { PRINT_MODE } from '../printMode';
import { computeMetrics } from './analytics/computeMetrics';
import {
  DowHeatmapPanel,
  FacetimeDrivePanel,
  FrequencyPanel,
  KpiStrip,
  LoadUtilizationPanel,
  RedVisitsPanel,
  SolverHealthPanel,
  WeekBalancePanel,
} from './analytics/panels';

export function AnalyticsScreen() {
  const [searchParams] = useSearchParams();
  const planIdParam = searchParams.get('planId');
  const [plans, setPlans] = useState<JourneyPlan[]>([]);
  const [selectedPlanId, setSelectedPlanId] = useState<number | null>(null);
  const [detail, setDetail] = useState<PlanWithVisits | null>(null);
  const [distance, setDistance] = useState<PlanDistance | null>(null);
  // True once the distance fetch settles (resolve OR reject) for the current
  // plan — gates the print-ready signal so the PDF never snapshots before the
  // km column has its data, while a failed lookup still lets the export proceed.
  const [distanceReady, setDistanceReady] = useState(false);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [exportBusy, setExportBusy] = useState(false);
  const [info, setInfo] = useState<string | null>(null);

  useEffect(() => {
    void (async () => {
      try {
        const [list, c] = await Promise.all([
          window.api.listPlans(),
          window.api.listCustomers({ limit: 100000 }),
        ]);
        setPlans(list);
        setCustomers(c);
        // Only honor ?planId= when it's in the dataset-scoped list — a plan
        // from another dataset would be charted against the wrong customer
        // set (coverage/frequency use the ACTIVE dataset's customers). In
        // print mode there is no fallback: exporting a different plan than
        // asked would be silent data corruption, so let the export time out.
        const fromParam = planIdParam ? Number(planIdParam) : NaN;
        if (list.some((p) => p.id === fromParam)) {
          setSelectedPlanId(fromParam);
        } else if (!PRINT_MODE && list.length > 0) {
          setSelectedPlanId(list[0]!.id);
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : String(err));
      }
    })();
  }, [planIdParam]);

  useEffect(() => {
    if (selectedPlanId === null) {
      setDetail(null);
      return;
    }
    let cancelled = false;
    setLoading(true);
    setError(null);
    void window.api
      .getPlan(selectedPlanId)
      .then((d) => {
        if (!cancelled) setDetail(d);
      })
      .catch((err) => {
        if (!cancelled) setError(err instanceof Error ? err.message : String(err));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [selectedPlanId]);

  // Distance driven is reconstructed main-side from the route legs + cache, so
  // it's a separate fetch from the plan detail. Best-effort: a failure leaves
  // the km column blank but never blocks the dashboard or the PDF export.
  useEffect(() => {
    setDistance(null);
    setDistanceReady(false);
    if (selectedPlanId === null) {
      setDistanceReady(true);
      return;
    }
    let cancelled = false;
    void window.api
      .planDistance(selectedPlanId)
      .then((d) => {
        if (!cancelled) setDistance(d);
      })
      .catch(() => {
        /* leave distance null — km column shows "—" */
      })
      .finally(() => {
        if (!cancelled) setDistanceReady(true);
      });
    return () => {
      cancelled = true;
    };
  }, [selectedPlanId]);

  // Each plan's analytics are computed against ITS salesman snapshot, so two
  // plans stay independent and roster deletes never blank out old dashboards.
  const metrics = useMemo(() => {
    if (!detail) return null;
    return computeMetrics(detail, detail.salesmen, customers);
  }, [detail, customers]);

  // Print mode: tell the hidden export window the charts have painted.
  // ResponsiveContainer measures via ResizeObserver and renders a frame later,
  // so wait two frames + a settle delay before signalling; the main process
  // guards the whole dance with its own 20s timeout.
  useEffect(() => {
    if (!PRINT_MODE || !metrics || !distanceReady) return;
    let timer: number | undefined;
    const raf = requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        timer = window.setTimeout(() => window.api.analyticsPrintReady(), 300);
      });
    });
    return () => {
      cancelAnimationFrame(raf);
      if (timer !== undefined) clearTimeout(timer);
    };
  }, [metrics, distanceReady]);

  const exportPdf = async () => {
    if (!detail) return;
    setExportBusy(true);
    setError(null);
    setInfo(null);
    try {
      const r = await window.api.exportAnalyticsPdf(detail.plan.id);
      if (r) setInfo(`Saved to ${r.savedPath}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setExportBusy(false);
    }
  };

  return (
    <div className="screen analytics-screen">
      {!PRINT_MODE && (
        <header className="screen-header">
          <h2>Analytics</h2>
          <p className="muted">
            Pick a plan on the left to visualize load balance, week distribution, capacity utilization, and over-capacity visits.
          </p>
          <button onClick={exportPdf} disabled={!detail || loading || exportBusy}>
            {exportBusy ? 'Exporting…' : 'Download PDF'}
          </button>
        </header>
      )}

      {PRINT_MODE && detail && (
        <header className="print-header">
          <h1>{detail.plan.name}</h1>
          <p>
            Journey plan analytics · {detail.plan.periodStart} → {detail.plan.periodEnd} ·{' '}
            {detail.salesmen.length} salesmen · {detail.plan.status} · generated{' '}
            {new Date().toLocaleString('en-GB', {
              dateStyle: 'medium',
              timeStyle: 'short',
              hour12: false,
            })}
          </p>
        </header>
      )}

      {error && <p className="result err">{error}</p>}
      {info && <p className="result">{info}</p>}

      <div className="analytics-layout">
        {!PRINT_MODE && (
          <aside className="analytics-plan-list">
            <h3>Plans</h3>
            {plans.length === 0 && (
              <p className="muted">No plans yet. Generate one on the Plan screen first.</p>
            )}
            <ul>
              {plans.map((p) => (
                <li key={p.id}>
                  <button
                    onClick={() => setSelectedPlanId(p.id)}
                    className={p.id === selectedPlanId ? 'active' : ''}
                  >
                    <strong>{p.name}</strong>{' '}
                    <span className={`badge status-${p.status}`}>{p.status}</span>
                    <br />
                    <small className="muted">
                      {p.periodStart} → {p.periodEnd}
                    </small>
                  </button>
                </li>
              ))}
            </ul>
          </aside>
        )}

        <section className="analytics-body">
          {loading && !PRINT_MODE && <p className="muted">Loading plan…</p>}
          {!loading && !detail && plans.length > 0 && !PRINT_MODE && (
            <p className="muted">Select a plan on the left.</p>
          )}
          {metrics && detail && (
            <>
              <KpiStrip m={metrics} distance={distance} />
              <div className="chart-grid">
                <LoadUtilizationPanel m={metrics} distance={distance} />
                <RedVisitsPanel m={metrics} />
                <WeekBalancePanel m={metrics} />
                <DowHeatmapPanel m={metrics} />
                <FacetimeDrivePanel m={metrics} />
                <FrequencyPanel m={metrics} />
                {detail.solverLog && (
                  <SolverHealthPanel m={metrics} solverLog={detail.solverLog} />
                )}
              </div>
            </>
          )}
        </section>
      </div>
    </div>
  );
}
