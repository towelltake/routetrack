"""Audit how the latest plan distributes freq=1/2 customers across weeks
within each (salesman, area) cluster — i.e., whether the 2026-05-20
cluster-aware tiebreak in cycle_assignment had visible effect.

For each cluster:
  - 'hot week' = week in which the salesman has any visit to this cluster
  - count freq=1 customers landing in hot vs cold weeks
  - count freq=2 customers with both weeks hot, mixed, or both cold

Cold landings are not bugs in themselves — they're unavoidable when a cluster
has only freq=1 customers (each one creates its own hot week). The signal to
watch: in clusters that already had a freq=4/2 anchor, are the freq=1 long-
tail customers bundling onto the anchor's weeks?

Read-only WAL access — safe while the Electron dev process is running.

Usage: uv run python scripts/audit-cluster-bundling.py
"""

from __future__ import annotations

import os
import sqlite3
import sys

# Force UTF-8 stdout so em-dashes in salesman names and arrows in the report
# survive the default cp1252 console encoding on Windows.
sys.stdout.reconfigure(encoding="utf-8")  # type: ignore[attr-defined]
from collections import defaultdict
from datetime import date
from pathlib import Path

DB_PATH = Path(os.environ["APPDATA"]) / "@journey" / "app" / "journey-plan.db"


def open_db() -> sqlite3.Connection:
    if not DB_PATH.exists():
        sys.stderr.write(f"DB not found at {DB_PATH}\n")
        sys.exit(1)
    uri = f"file:{DB_PATH.as_posix()}?mode=ro"
    conn = sqlite3.connect(uri, uri=True)
    conn.row_factory = sqlite3.Row
    return conn


def week_index(visit_date: str, period_start: str) -> int:
    d = date.fromisoformat(visit_date)
    ps = date.fromisoformat(period_start)
    return (d - ps).days // 7


def main() -> int:  # noqa: C901
    conn = open_db()

    plan_row = conn.execute(
        "SELECT id, name, period_start, period_end, dataset_id, created_at "
        "FROM journey_plans ORDER BY created_at DESC LIMIT 1"
    ).fetchone()
    if plan_row is None:
        print("# Cluster bundling audit — no plans found.")
        return 0

    plan_id = plan_row["id"]
    period_start = plan_row["period_start"]
    print(f"# Cluster bundling audit — Plan #{plan_id} ({plan_row['name']})")
    print(f"_Created: {plan_row['created_at']}, period start {period_start}_\n")

    visits = conn.execute(
        "SELECT v.salesman_id, v.customer_id, v.scheduled_date "
        "FROM visits v WHERE v.journey_plan_id = ?",
        (plan_id,),
    ).fetchall()
    customers = conn.execute(
        "SELECT id, name, external_code, area, monthly_frequency, pinned_salesman_id, "
        "       assigned_salesman_id "
        "FROM customers WHERE dataset_id = ?",
        (plan_row["dataset_id"],),
    ).fetchall()
    salesmen = {
        s["id"]: s["name"]
        for s in conn.execute("SELECT id, name FROM salesmen").fetchall()
    }

    cust_by_id = {c["id"]: c for c in customers}

    # cluster_visits[(salesman_id, area)] = {week_index: count of visits}
    cluster_visits: dict[tuple[int, str], dict[int, int]] = defaultdict(
        lambda: defaultdict(int)
    )
    # cust_weeks[customer_id] = sorted list of week_indices this customer was visited in
    cust_weeks: dict[int, list[int]] = defaultdict(list)

    for v in visits:
        c = cust_by_id.get(v["customer_id"])
        if c is None or not c["area"]:
            continue
        w = week_index(v["scheduled_date"], period_start)
        cluster_visits[(v["salesman_id"], c["area"])][w] += 1
        cust_weeks[v["customer_id"]].append(w)

    # Bucket customers into their cluster, group by frequency.
    # cluster_customers[(s_id, area)] = list[customer rows]
    cluster_customers: dict[tuple[int, str], list[sqlite3.Row]] = defaultdict(list)
    for c in customers:
        # Effective salesman = pin else assigned (mirrors planner.ts coalescing).
        eff_s = c["pinned_salesman_id"] or c["assigned_salesman_id"]
        if eff_s is None or not c["area"]:
            continue
        if not cust_weeks.get(c["id"]):  # never visited — unassigned
            continue
        cluster_customers[(eff_s, c["area"])].append(c)

    total_low = total_low_hot = total_low_cold = 0
    f2_pair_both_hot = f2_pair_mixed = f2_pair_both_cold = 0
    cold_examples: list[str] = []
    hot_examples: list[str] = []

    # Sort clusters by size descending so the most interesting come first.
    sorted_clusters = sorted(
        cluster_customers.items(), key=lambda kv: -len(kv[1])
    )

    cluster_lines: list[str] = []
    for (s_id, area), custs in sorted_clusters:
        weeks_visited = cluster_visits[(s_id, area)]
        hot_weeks = {w for w, n in weeks_visited.items() if n > 0}
        if len(custs) < 2:
            continue  # single-customer cluster: bundling is undefined

        anchor_freqs = sorted({c["monthly_frequency"] for c in custs}, reverse=True)
        f1s = [c for c in custs if c["monthly_frequency"] == 1]
        f2s = [c for c in custs if c["monthly_frequency"] == 2]
        if not f1s and not f2s:
            continue  # no flexible-frequency customers to evaluate

        cluster_hot_count = 0
        cluster_cold_count = 0
        for c in f1s:
            cust_w = cust_weeks[c["id"]]
            # For freq=1, exactly one week. Is it hot beyond just this customer?
            # We need to know: does the cluster have OTHER visits in this week
            # besides this customer's own? Subtract 1 for this customer's visit.
            w = cust_w[0]
            other_visits_this_week = weeks_visited[w] - 1
            total_low += 1
            if other_visits_this_week > 0:
                total_low_hot += 1
                cluster_hot_count += 1
                if len(hot_examples) < 8 and len(custs) >= 4:
                    hot_examples.append(
                        f"  - **{salesmen.get(s_id, s_id)} / {area}** — "
                        f"{c['name']} (freq=1, code {c['external_code']}) → W{w}, "
                        f"cluster has {other_visits_this_week} other visit(s) that week"
                    )
            else:
                total_low_cold += 1
                cluster_cold_count += 1
                # Flag freq=1s that landed alone DESPITE the cluster having other
                # hot weeks they could have bundled into.
                other_hot = hot_weeks - {w}
                if other_hot and len(cold_examples) < 12:
                    cold_examples.append(
                        f"  - **{salesmen.get(s_id, s_id)} / {area}** — "
                        f"{c['name']} (freq=1, code {c['external_code']}) → "
                        f"W{w} (alone), but cluster is also active in "
                        f"W{sorted(other_hot)} — could have bundled"
                    )
        for c in f2s:
            cust_w = sorted(cust_weeks[c["id"]])
            hot_count = 0
            for w in cust_w:
                if weeks_visited[w] - 1 > 0:
                    hot_count += 1
            if hot_count == len(cust_w):
                f2_pair_both_hot += 1
            elif hot_count == 0:
                f2_pair_both_cold += 1
            else:
                f2_pair_mixed += 1

        if cluster_hot_count + cluster_cold_count > 0:
            cluster_lines.append(
                f"| {salesmen.get(s_id, s_id)} | {area} | {len(custs)} "
                f"({','.join(str(f) for f in anchor_freqs)}) | "
                f"{sorted(hot_weeks)} | "
                f"{cluster_hot_count} | {cluster_cold_count} |"
            )

    print("## Headline numbers\n")
    total_f1 = total_low_hot + total_low_cold
    if total_f1:
        pct = 100 * total_low_hot / total_f1
        print(
            f"- **freq=1 customers in multi-customer clusters: {total_f1}**\n"
            f"  - Landed in a week where the salesman has other visits to the "
            f"same area: **{total_low_hot} ({pct:.1f}%)** [OK] bundled\n"
            f"  - Landed alone (no other cluster activity that week): "
            f"**{total_low_cold} ({100-pct:.1f}%)**"
        )
    else:
        print("- No freq=1 customers in multi-customer clusters to evaluate.")

    total_f2 = f2_pair_both_hot + f2_pair_mixed + f2_pair_both_cold
    if total_f2:
        print(
            f"- **freq=2 customers in multi-customer clusters: {total_f2}**\n"
            f"  - Both weeks aligned with cluster heat: **{f2_pair_both_hot}** [OK]\n"
            f"  - One week aligned, one not: {f2_pair_mixed}\n"
            f"  - Both weeks alone: {f2_pair_both_cold}"
        )
    print()

    print("## Cluster-by-cluster (multi-customer clusters with freq=1/2)\n")
    print(
        "| Salesman | Area | Custs (freqs) | Hot weeks | freq=1 bundled | freq=1 alone |"
    )
    print("|---|---|---|---|---:|---:|")
    for line in cluster_lines[:30]:
        print(line)
    if len(cluster_lines) > 30:
        print(f"\n_…and {len(cluster_lines) - 30} more clusters not shown._")
    print()

    # Honesty check: a cluster where all 4 weeks are hot (because of a freq=4+
    # anchor) makes the tiebreak a no-op — there's no cold week to avoid. The
    # logic matters most on clusters with cold weeks. Surface those separately.
    print("## Clusters with cold weeks (where the tiebreak has counterfactual bite)\n")
    print("| Salesman | Area | Custs | Max freq | Hot weeks | Cold weeks |")
    print("|---|---|---|---:|---|---|")
    cold_cluster_rows = 0
    for (s_id, area), custs in sorted_clusters:
        if len(custs) < 2:
            continue
        weeks_visited = cluster_visits[(s_id, area)]
        hot_weeks = sorted(w for w, n in weeks_visited.items() if n > 0)
        cold_weeks = sorted(set(range(4)) - set(hot_weeks))
        if not cold_weeks:
            continue
        max_freq = max(c["monthly_frequency"] for c in custs)
        print(
            f"| {salesmen.get(s_id, s_id)} | {area} | {len(custs)} | "
            f"{max_freq} | {hot_weeks} | {cold_weeks} |"
        )
        cold_cluster_rows += 1
    if cold_cluster_rows == 0:
        print("| _(none)_ | | | | | |")
        print(
            "\n_All multi-customer clusters in this dataset have a freq≥4 anchor, "
            "so every week is hot. The cluster-aware tiebreak still ran, but its "
            "counterfactual is empty — any week the freq=1/2 picked is already hot. "
            "The tiebreak's measurable benefit will only show up on datasets that "
            "include clusters of mostly freq=1/2/3 customers without a weekly anchor._"
        )
    print()

    if hot_examples:
        print("## Sample bundled freq=1s (spot-check these in the UI)\n")
        for ex in hot_examples:
            print(ex)
        print()

    if cold_examples:
        print(
            "## freq=1s that landed alone DESPITE other hot weeks available\n"
            "_If this list is long, the cluster-aware tiebreak isn't reaching "
            "these placements — possible reasons: allowed_days restriction, "
            "capacity overflow forcing alternative weeks, or the customer's "
            "ftime*freq sort rank put it ahead of the anchor._\n"
        )
        for ex in cold_examples:
            print(ex)
        print()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
