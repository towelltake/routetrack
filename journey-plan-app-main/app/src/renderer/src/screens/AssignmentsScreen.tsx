import { useCallback, useEffect, useMemo, useState } from 'react';
import type {
  AssignmentComputeResult,
  AssignmentStatus,
  Customer,
  Salesman,
} from '@journey/shared';
import { formatDbTimestamp } from './planMath';

type FilterMode = 'all' | 'overrides' | 'unassignable';

export function AssignmentsScreen() {
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [salesmen, setSalesmen] = useState<Salesman[]>([]);
  const [areas, setAreas] = useState<string[]>([]);
  const [status, setStatus] = useState<AssignmentStatus | null>(null);
  const [lastCompute, setLastCompute] = useState<AssignmentComputeResult | null>(null);

  const [filterSalesmanId, setFilterSalesmanId] = useState<number | ''>('');
  const [filterArea, setFilterArea] = useState<string>('');
  const [filterMode, setFilterMode] = useState<FilterMode>('all');
  const [search, setSearch] = useState('');

  const [computing, setComputing] = useState(false);
  const [computeError, setComputeError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    const [c, s, a, st] = await Promise.all([
      window.api.listCustomers({ limit: 5000 }),
      window.api.listSalesmen(),
      window.api.distinctAreas(),
      window.api.getAssignmentStatus(),
    ]);
    setCustomers(c);
    setSalesmen(s);
    setAreas(a);
    setStatus(st);
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const salesmanById = useMemo(() => {
    const m = new Map<number, Salesman>();
    for (const s of salesmen) m.set(s.id, s);
    return m;
  }, [salesmen]);

  // The set of customers we hold an assignment-related state for (i.e., either
  // algorithm-assigned, user-overridden, or geocoded-but-neither). The latter
  // are valid candidates for the next compute but currently unassigned.
  const visibleCustomers = useMemo(() => {
    const needle = search.trim().toLowerCase();
    return customers.filter((c) => {
      if (needle) {
        const hay = `${c.externalCode} ${c.name}`.toLowerCase();
        if (!hay.includes(needle)) return false;
      }
      if (filterArea && c.area !== filterArea) return false;
      const effective = c.pinnedSalesmanId ?? c.assignedSalesmanId;
      if (filterSalesmanId !== '' && effective !== filterSalesmanId) return false;
      if (filterMode === 'overrides' && c.pinnedSalesmanId === null) return false;
      if (filterMode === 'unassignable' && effective !== null) return false;
      return true;
    });
  }, [customers, search, filterArea, filterSalesmanId, filterMode]);

  const counts = useMemo(() => {
    let assigned = 0;
    let pinned = 0;
    let unassigned = 0;
    for (const c of customers) {
      if (c.pinnedSalesmanId !== null) {
        pinned += 1;
      } else if (c.assignedSalesmanId !== null) {
        assigned += 1;
      } else {
        unassigned += 1;
      }
    }
    return { total: customers.length, assigned, pinned, unassigned };
  }, [customers]);

  // Eligibility-tag audit — catch-all rows (empty area AND empty region) bypass
  // the hard cross-region wall in the solver and fall back to soft distance-only
  // matching, which under high load can produce surprising cross-region
  // assignments. Surface this BEFORE the user clicks Compute.
  const tagAudit = useMemo(() => {
    const catchAllCustomers = customers.filter(
      (c) => !c.area?.trim() && !c.region?.trim(),
    );
    const catchAllSalesmen = salesmen.filter(
      (s) =>
        (s.assignedAreas?.length ?? 0) === 0 &&
        (s.assignedRegions?.length ?? 0) === 0,
    );
    return { catchAllCustomers, catchAllSalesmen };
  }, [customers, salesmen]);

  const compute = async () => {
    if (computing) return;
    setComputing(true);
    setComputeError(null);
    try {
      const result = await window.api.computeAssignments();
      setLastCompute(result);
      await reload();
    } catch (err) {
      setComputeError(err instanceof Error ? err.message : String(err));
    } finally {
      setComputing(false);
    }
  };

  const setOverride = async (customerId: number, salesmanId: number | null) => {
    await window.api.setAssignmentOverride(customerId, salesmanId);
    // Optimistic local update so the row tints immediately; reload picks up
    // staleness/load changes shortly after.
    setCustomers((prev) =>
      prev.map((c) => (c.id === customerId ? { ...c, pinnedSalesmanId: salesmanId } : c)),
    );
    void window.api.getAssignmentStatus().then(setStatus);
  };

  const clearAllOverrides = async () => {
    if (counts.pinned === 0) return;
    // Two-confirm — matches the SalesmenScreen clear-all pattern. Cascade is
    // less severe (no visits deleted) but the cross-import pin question
    // documented in the plan warrants a real warning.
    if (
      !confirm(
        `Clear all ${counts.pinned} salesman overrides?\n\n` +
          `This removes user pins set both at import time and via this screen — ` +
          `they're indistinguishable in storage. The algorithm assignments stay.`,
      )
    ) {
      return;
    }
    if (!confirm('Are you sure? This cannot be undone.')) return;
    const removed = await window.api.clearAllAssignmentOverrides();
    await reload();
    alert(`Cleared ${removed} override(s).`);
  };

  return (
    <div className="screen assignments-screen">
      <header className="screen-header">
        <h2>Salesman assignments</h2>
        <div className="header-actions">
          <button onClick={compute} disabled={computing} className="primary">
            {computing ? 'Computing…' : 'Compute assignments'}
          </button>
          <button
            onClick={clearAllOverrides}
            disabled={counts.pinned === 0}
            className="danger"
            title="Clear all user overrides — algorithm assignments stay"
          >
            Clear all overrides
          </button>
        </div>
      </header>

      {computeError && (
        <div className="banner banner-error">
          Compute failed: <code>{computeError}</code>
        </div>
      )}

      {(tagAudit.catchAllCustomers.length > 0 || tagAudit.catchAllSalesmen.length > 0) && (
        <div className="banner banner-stale">
          <strong>Eligibility tag warning.</strong>{' '}
          {tagAudit.catchAllCustomers.length > 0 && (
            <>
              {tagAudit.catchAllCustomers.length.toLocaleString()} customer
              {tagAudit.catchAllCustomers.length === 1 ? '' : 's'} have no area
              and no region.{' '}
            </>
          )}
          {tagAudit.catchAllSalesmen.length > 0 && (
            <>
              {tagAudit.catchAllSalesmen.length.toLocaleString()} salesm
              {tagAudit.catchAllSalesmen.length === 1 ? 'an' : 'en'} have no
              assigned areas and no assigned regions (catch-all).{' '}
            </>
          )}
          <span className="muted">
            These rows fall back to soft distance matching, which can produce
            cross-region assignments under load. Tag them on the Customers /
            Salesmen screens to enforce the hard region wall.
          </span>
        </div>
      )}

      {status?.staleness.stale && (
        <div className="banner banner-stale">
          <strong>Assignments may be stale.</strong> {status.staleness.reason}{' '}
          {status.lastRun && (
            <span className="muted">
              Last computed {formatDbTimestamp(status.lastRun.computedAt)}
              {' · '}
              {status.lastRun.runtimeSeconds.toFixed(2)} s
              {' · '}
              status: {status.lastRun.solverStatus}
            </span>
          )}
        </div>
      )}

      {lastCompute && !computeError && (
        <div className="banner banner-info">
          Computed {lastCompute.assigned.toLocaleString()} assignment(s) in{' '}
          {lastCompute.runtimeSeconds.toFixed(2)} s ·{' '}
          {lastCompute.unassignable.length} unassignable · status:{' '}
          {lastCompute.solverStatus}
        </div>
      )}

      <div className="stats-strip">
        <span className="stat-chip">
          <strong>{counts.total.toLocaleString()}</strong> total
        </span>
        <span className="stat-chip ok">
          <strong>{counts.assigned.toLocaleString()}</strong> assigned
        </span>
        <span className="stat-chip override">
          <strong>{counts.pinned.toLocaleString()}</strong> override
        </span>
        <span className="stat-chip warn">
          <strong>{counts.unassigned.toLocaleString()}</strong> unassigned
        </span>
      </div>

      {lastCompute && lastCompute.targetLoadMinutes > 0 && (
        <details className="load-table">
          <summary>
            Per-salesman load — target {lastCompute.targetLoadMinutes.toLocaleString()} min
          </summary>
          <table className="load-mini">
            <thead>
              <tr>
                <th>Salesman</th>
                <th>Load (min)</th>
                <th>% of target</th>
              </tr>
            </thead>
            <tbody>
              {Object.entries(lastCompute.perSalesmanLoadMinutes).map(([sid, mins]) => {
                const s = salesmanById.get(Number(sid));
                const pct =
                  lastCompute.targetLoadMinutes > 0
                    ? Math.round((mins / lastCompute.targetLoadMinutes) * 100)
                    : 0;
                return (
                  <tr key={sid}>
                    <td>{s?.name ?? `#${sid}`}</td>
                    <td>{mins.toLocaleString()}</td>
                    <td>{pct}%</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </details>
      )}

      <div className="filters">
        <input
          type="search"
          placeholder="Search name or code"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <select
          value={filterSalesmanId}
          onChange={(e) =>
            setFilterSalesmanId(e.target.value === '' ? '' : Number(e.target.value))
          }
        >
          <option value="">All salesmen</option>
          {salesmen.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </select>
        <select value={filterArea} onChange={(e) => setFilterArea(e.target.value)}>
          <option value="">All areas</option>
          {areas.map((a) => (
            <option key={a} value={a}>
              {a}
            </option>
          ))}
        </select>
        <select value={filterMode} onChange={(e) => setFilterMode(e.target.value as FilterMode)}>
          <option value="all">Show all</option>
          <option value="overrides">Overrides only</option>
          <option value="unassignable">Unassigned only</option>
        </select>
        <span className="muted">{visibleCustomers.length.toLocaleString()} shown</span>
      </div>

      <div className="table-wrap">
        <table className="assignments-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Area</th>
              <th>Freq</th>
              <th>Facetime</th>
              <th>Assigned (algorithm)</th>
              <th>Override (user)</th>
            </tr>
          </thead>
          <tbody>
            {visibleCustomers.map((c) => {
              const assigned = c.assignedSalesmanId !== null
                ? salesmanById.get(c.assignedSalesmanId)
                : null;
              const overridden = c.pinnedSalesmanId !== null;
              const unassigned = c.pinnedSalesmanId === null && c.assignedSalesmanId === null;
              return (
                <tr
                  key={c.id}
                  className={
                    overridden
                      ? 'row-override'
                      : unassigned
                        ? 'row-unassigned'
                        : ''
                  }
                >
                  <td>{c.externalCode}</td>
                  <td>{c.name}</td>
                  <td>{c.area ?? '—'}</td>
                  <td>{c.monthlyFrequency}</td>
                  <td>{c.facetimeMinutes}m</td>
                  <td>{assigned ? assigned.name : <span className="muted">—</span>}</td>
                  <td>
                    <select
                      value={c.pinnedSalesmanId ?? ''}
                      onChange={(e) =>
                        void setOverride(
                          c.id,
                          e.target.value === '' ? null : Number(e.target.value),
                        )
                      }
                    >
                      <option value="">(none)</option>
                      {salesmen.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.name}
                        </option>
                      ))}
                    </select>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
