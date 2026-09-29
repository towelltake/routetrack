"""Audit the latest journey plan against the active customer dataset.

Reads journey-plan.db in read-only WAL mode so it can run while the Electron
dev process holds a write lock. Prints a Markdown report to stdout — pipe to
a file or copy from the terminal.

Checks:
  1. Coverage — how many dataset customers got at least one visit
  2. Frequency conformance — visits per customer vs monthlyFrequency
  3. Allowed-days violations — visit scheduled on a day not in customer.allowed_days
  4. Working-day violations — visit scheduled on a day not in salesman.working_days
  5. Pin violations — visit.salesman_id != customer.pinned_salesman_id (when pin set)
  6. Area violations — visit.salesman has assigned_areas but visit.customer.area not in them
  7. Daily capacity — sum(facetime + drive_minutes_to) vs working window
  8. Sequence sanity — sequences 1..N per (salesman, day), no gaps, no dupes
  9. Coordinate sanity — visit pointing at a customer with NULL lat/lng

Usage: uv run python scripts/audit-latest-plan.py
"""

from __future__ import annotations

import os
import sqlite3
import sys
from collections import defaultdict
from datetime import date, datetime
from pathlib import Path

# `JOURNEY_DB_PATH` overrides the default for non-prod runs — set it to
# `%APPDATA%\@journey\app-dev\journey-plan.db` when auditing a `pnpm dev`
# session (the 2026-05-23 userData split sends dev to that path).
_DEFAULT_DB = Path(os.environ["APPDATA"]) / "@journey" / "app" / "journey-plan.db"
DB_PATH = Path(os.environ.get("JOURNEY_DB_PATH", str(_DEFAULT_DB)))


def open_db() -> sqlite3.Connection:
    if not DB_PATH.exists():
        sys.stderr.write(f"DB not found at {DB_PATH}\n")
        sys.exit(1)
    # URI mode + mode=ro = read-only; nolock=0 keeps WAL coherent with the writer.
    uri = f"file:{DB_PATH.as_posix()}?mode=ro"
    conn = sqlite3.connect(uri, uri=True)
    conn.row_factory = sqlite3.Row
    return conn


def parse_int_csv(s: str | None) -> list[int]:
    if not s:
        return []
    return [int(x) for x in s.split(",") if x.strip()]


def parse_str_csv(s: str | None) -> list[str]:
    if not s:
        return []
    return [x.strip() for x in s.split(",") if x.strip()]


def hhmm_to_min(s: str) -> int:
    h, m = s.split(":")
    return int(h) * 60 + int(m)


def dow(iso_date: str) -> int:
    # 0=Sun .. 6=Sat per the app's convention.
    d = datetime.fromisoformat(iso_date).date()
    # Python weekday: Mon=0..Sun=6. App: Sun=0..Sat=6.
    return (d.weekday() + 1) % 7


def main() -> int:  # noqa: C901  # long-by-design audit driver
    conn = open_db()

    # Pick the latest plan by created_at desc.
    plan_row = conn.execute(
        "SELECT id, name, period_start, period_end, dataset_id, status, created_at "
        "FROM journey_plans ORDER BY created_at DESC LIMIT 1"
    ).fetchone()
    if plan_row is None:
        print("# Audit — no journey plans found.")
        return 0

    plan_id = plan_row["id"]
    period_start = plan_row["period_start"]
    period_end = plan_row["period_end"]
    dataset_id = plan_row["dataset_id"]

    out: list[str] = []
    out.append(f"# Audit — Plan #{plan_id} `{plan_row['name']}`")
    out.append(
        f"_Period **{period_start}** → **{period_end}**, status **{plan_row['status']}**, "
        f"created {plan_row['created_at']}_\n"
    )

    # Visits, customers, salesmen scoped to this plan.
    visits = conn.execute(
        """SELECT v.id, v.salesman_id, v.customer_id, v.scheduled_date,
                  v.scheduled_start_time, v.sequence, v.drive_minutes_to,
                  v.facetime_minutes
           FROM visits v WHERE v.journey_plan_id = ? ORDER BY v.scheduled_date,
                                                              v.salesman_id, v.sequence""",
        (plan_id,),
    ).fetchall()
    if not visits:
        out.append("**No visits in this plan.** Was it deleted, or did the solver fail?")
        print("\n".join(out))
        return 0

    # geocode_status was dropped in migration 0010; coordinate presence is the
    # current "is this row usable" proxy (importer enforces lat+lng required).
    cust_rows = conn.execute(
        """SELECT id, name, external_code, lat, lng,
                  facetime_minutes, monthly_frequency, allowed_days_csv,
                  pinned_salesman_id, assigned_salesman_id, area
           FROM customers WHERE dataset_id = ?""",
        (dataset_id,),
    ).fetchall()
    customers = {c["id"]: c for c in cust_rows}

    sales_rows = conn.execute(
        """SELECT id, name, start_location_lat, start_location_lng,
                  working_days_csv, working_hours_start, working_hours_end,
                  assigned_areas_csv
           FROM salesmen"""
    ).fetchall()
    salesmen = {s["id"]: s for s in sales_rows}

    # -- Summary table --
    out.append("## Summary\n")
    customers_with_visits = {v["customer_id"] for v in visits}
    dataset_with_coords = sum(
        1 for c in cust_rows if c["lat"] is not None and c["lng"] is not None
    )
    out.append(f"- Visits: **{len(visits):,}**")
    out.append(
        f"- Customers in active dataset: **{len(customers):,}** "
        f"({dataset_with_coords:,} with coordinates)"
    )
    out.append(
        f"- Customers with ≥1 visit: **{len(customers_with_visits):,}** "
        f"({100 * len(customers_with_visits) / max(len(customers), 1):.1f}% coverage)"
    )
    out.append(f"- Salesmen used: **{len({v['salesman_id'] for v in visits}):,} / {len(salesmen):,}**")
    days_used = {v["scheduled_date"] for v in visits}
    out.append(f"- Distinct working days covered: **{len(days_used):,}**")

    # -- 1. Unassigned customers (in dataset but no visits in this plan) --
    unassigned = [c for cid, c in customers.items() if cid not in customers_with_visits]
    out.append(f"\n## 1. Unassigned customers — {len(unassigned)}\n")
    if not unassigned:
        out.append("None. Every dataset customer received at least one visit. ")
    else:
        # Break down by why we think they didn't fit.
        no_coords = [c for c in unassigned if c["lat"] is None or c["lng"] is None]
        with_coords = [c for c in unassigned if c["lat"] is not None and c["lng"] is not None]
        out.append(f"- {len(no_coords)} missing lat/lng (cannot route)")
        out.append(f"- {len(with_coords)} have coordinates but no visits")
        if with_coords[:10]:
            out.append("\nFirst 10 unassigned with coords (likely solver-dropped):\n")
            out.append("| Code | Name | Freq | Area | Pinned to |")
            out.append("|---|---|---|---|---|")
            for c in with_coords[:10]:
                pin = salesmen.get(c["pinned_salesman_id"], {})
                pin_name = pin["name"] if pin else "—"
                out.append(
                    f"| {c['external_code']} | {c['name']} | {c['monthly_frequency']}× | "
                    f"{c['area'] or '—'} | {pin_name} |"
                )

    # -- 2. Frequency conformance --
    visits_per_cust: dict[int, int] = defaultdict(int)
    for v in visits:
        visits_per_cust[v["customer_id"]] += 1
    freq_violations = []
    for cid, c in customers.items():
        n = visits_per_cust.get(cid, 0)
        want = c["monthly_frequency"]
        if n == 0 and want > 0:
            continue  # captured in section 1
        if n != want:
            freq_violations.append((c, n, want))
    out.append(f"\n## 2. Frequency conformance — {len(freq_violations)} mismatched\n")
    if not freq_violations:
        out.append(
            "Every assigned customer received exactly `monthly_frequency` visits. "
        )
    else:
        over = [x for x in freq_violations if x[1] > x[2]]
        under = [x for x in freq_violations if x[1] < x[2]]
        out.append(f"- Over-visited: **{len(over)}**, under-visited: **{len(under)}**")
        if freq_violations[:10]:
            out.append("\nFirst 10:\n| Code | Name | Got | Want | Delta |")
            out.append("|---|---|---|---|---|")
            for c, got, want in sorted(
                freq_violations, key=lambda x: abs(x[1] - x[2]), reverse=True
            )[:10]:
                out.append(
                    f"| {c['external_code']} | {c['name']} | {got} | {want} | "
                    f"{got - want:+d} |"
                )

    # -- 3. Allowed-days violations --
    allowed_violations = []
    for v in visits:
        c = customers.get(v["customer_id"])
        if not c:
            continue
        allowed = parse_int_csv(c["allowed_days_csv"])
        if not allowed:
            continue  # no constraint
        if dow(v["scheduled_date"]) not in allowed:
            allowed_violations.append((v, c))
    out.append(f"\n## 3. Allowed-days violations — {len(allowed_violations)}\n")
    if not allowed_violations:
        out.append(
            "No visit lands on a weekday the customer's `allowed_days` excludes. "
        )
    else:
        out.append("Visits scheduled on a day the customer says they're closed/unavailable:\n")
        out.append("| Code | Name | Visit date | DOW | Allowed |")
        out.append("|---|---|---|---|---|")
        day_names = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]
        for v, c in allowed_violations[:15]:
            d = dow(v["scheduled_date"])
            allowed = parse_int_csv(c["allowed_days_csv"])
            out.append(
                f"| {c['external_code']} | {c['name']} | {v['scheduled_date']} | "
                f"{day_names[d]} | {','.join(day_names[i] for i in allowed)} |"
            )

    # -- 4. Working-day violations --
    sd_violations = []
    for v in visits:
        s = salesmen.get(v["salesman_id"])
        if not s:
            continue
        working = parse_int_csv(s["working_days_csv"])
        if dow(v["scheduled_date"]) not in working:
            sd_violations.append((v, s))
    out.append(f"\n## 4. Salesman working-day violations — {len(sd_violations)}\n")
    if not sd_violations:
        out.append("No visit assigned to a salesman on their day off. ")
    else:
        out.append("| Salesman | Visit date | DOW | Working days |")
        out.append("|---|---|---|---|")
        day_names = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]
        for v, s in sd_violations[:15]:
            d = dow(v["scheduled_date"])
            working = parse_int_csv(s["working_days_csv"])
            out.append(
                f"| {s['name']} | {v['scheduled_date']} | {day_names[d]} | "
                f"{','.join(day_names[i] for i in working)} |"
            )

    # -- 5. Pin violations --
    pin_violations = []
    for v in visits:
        c = customers.get(v["customer_id"])
        if not c or c["pinned_salesman_id"] is None:
            continue
        if c["pinned_salesman_id"] != v["salesman_id"]:
            pin_violations.append((v, c))
    out.append(f"\n## 5. Pin violations — {len(pin_violations)}\n")
    if not pin_violations:
        out.append("Every pinned customer's visits went to the salesman they were pinned to. ")
    else:
        out.append("Customer is pinned but the solver routed the visit through a different salesman:\n")
        out.append("| Code | Name | Pinned to | Visited by | Date |")
        out.append("|---|---|---|---|---|")
        for v, c in pin_violations[:15]:
            pin = salesmen.get(c["pinned_salesman_id"], {})
            visited = salesmen.get(v["salesman_id"], {})
            out.append(
                f"| {c['external_code']} | {c['name']} | {pin.get('name', '—')} | "
                f"{visited.get('name', '—')} | {v['scheduled_date']} |"
            )

    # -- 6. Area violations --
    area_violations = []
    for v in visits:
        s = salesmen.get(v["salesman_id"])
        c = customers.get(v["customer_id"])
        if not s or not c:
            continue
        sa_areas = parse_str_csv(s["assigned_areas_csv"])
        if not sa_areas:
            continue  # catch-all salesman
        if c["area"] and c["area"] not in sa_areas:
            area_violations.append((v, s, c))
    out.append(f"\n## 6. Area violations — {len(area_violations)}\n")
    if not area_violations:
        out.append(
            "No visit places a customer outside their salesman's assigned areas "
            "(area-null customers and catch-all salesmen are allowed). "
        )
    else:
        out.append("| Salesman | Areas | Customer | Customer area |")
        out.append("|---|---|---|---|")
        for v, s, c in area_violations[:15]:
            sa = ",".join(parse_str_csv(s["assigned_areas_csv"]))
            out.append(f"| {s['name']} | {sa} | {c['name']} | {c['area']} |")

    # -- 7. Daily capacity --
    # Sum facetime + drive_minutes_to per (salesman, date). Compare vs working
    # window. The solver allows a 10 min daily slack — anything more is bleed.
    DAILY_SLACK = 10
    by_day: dict[tuple[int, str], list[sqlite3.Row]] = defaultdict(list)
    for v in visits:
        by_day[(v["salesman_id"], v["scheduled_date"])].append(v)

    day_loads = []
    for (sid, d), day_visits in by_day.items():
        s = salesmen.get(sid)
        if not s:
            continue
        window_min = hhmm_to_min(s["working_hours_end"]) - hhmm_to_min(s["working_hours_start"])
        face = sum((v["facetime_minutes"] or 0) for v in day_visits)
        drive = sum((v["drive_minutes_to"] or 0) for v in day_visits)
        load = face + drive
        day_loads.append((s["name"], d, load, window_min, face, drive, len(day_visits)))

    over = [x for x in day_loads if x[2] > x[3] + DAILY_SLACK]
    out.append(f"\n## 7. Daily capacity — {len(over)} day(s) over budget\n")
    out.append(
        f"_Budget = `working_hours_end − working_hours_start` + {DAILY_SLACK} min slack. "
        f"Load = facetime + drive minutes._\n"
    )

    if day_loads:
        total = sum(x[2] for x in day_loads)
        total_budget = sum(x[3] for x in day_loads)
        util_pct = 100.0 * total / max(total_budget, 1)
        full_days = sum(1 for x in day_loads if x[2] >= 0.9 * x[3])
        light_days = sum(1 for x in day_loads if x[2] < 0.5 * x[3])
        out.append(f"- Working days planned: **{len(day_loads):,}**")
        out.append(f"- Avg utilization: **{util_pct:.1f}%**")
        out.append(
            f"- Days ≥90% loaded (heavy): **{full_days}** "
            f"({100 * full_days / len(day_loads):.1f}%)"
        )
        out.append(
            f"- Days <50% loaded (light): **{light_days}** "
            f"({100 * light_days / len(day_loads):.1f}%)"
        )

    if over:
        out.append("\nDays over budget (sorted by overrun):\n")
        out.append("| Salesman | Date | Load (min) | Budget | Overrun | Visits | Face | Drive |")
        out.append("|---|---|---|---|---|---|---|---|")
        for name, d, load, budget, face, drive, n in sorted(
            over, key=lambda x: x[2] - x[3], reverse=True
        )[:15]:
            out.append(
                f"| {name} | {d} | {load} | {budget} | +{load - budget} | "
                f"{n} | {face} | {drive} |"
            )
    else:
        out.append("\nNo day overruns the budget. ")

    # -- 8. Sequence sanity --
    seq_issues = []
    for (sid, d), day_visits in by_day.items():
        seqs = sorted(v["sequence"] for v in day_visits)
        # Expect 1..N contiguous.
        expected = list(range(1, len(seqs) + 1))
        if seqs != expected:
            seq_issues.append((salesmen.get(sid, {}).get("name", f"sid={sid}"), d, seqs))
    out.append(f"\n## 8. Sequence sanity — {len(seq_issues)} day(s) with gaps/dupes\n")
    if not seq_issues:
        out.append("All days have contiguous 1..N sequences. ")
    else:
        out.append("| Salesman | Date | Sequences |")
        out.append("|---|---|---|")
        for name, d, seqs in seq_issues[:10]:
            out.append(f"| {name} | {d} | {seqs} |")

    # -- 9. Coordinate sanity --
    nocoord_visits = [
        v for v in visits
        if (c := customers.get(v["customer_id"]))
        and (c["lat"] is None or c["lng"] is None)
    ]
    out.append(f"\n## 9. Visits to customers with no coordinates — {len(nocoord_visits)}\n")
    if not nocoord_visits:
        out.append("Every visited customer has a lat/lng. ")
    else:
        out.append(
            "These visits exist on the plan but the customer has NULL lat/lng — "
            "the OSRM matrix cannot have routed them, so drive times are haversine fallback only.\n"
        )
        out.append("| Code | Name | Salesman | Date |")
        out.append("|---|---|---|---|")
        for v in nocoord_visits[:10]:
            c = customers[v["customer_id"]]
            s = salesmen.get(v["salesman_id"], {})
            out.append(
                f"| {c['external_code']} | {c['name']} | {s.get('name', '—')} | "
                f"{v['scheduled_date']} |"
            )

    # -- 10. Assignment coverage (Phase 7a) --
    # Reads the customers table columns from this dataset; doesn't depend on the
    # plan itself. Useful when verifying the Assignments screen produced something
    # sensible BEFORE rolling forward to a plan generation.
    assigned_cnt = sum(1 for c in cust_rows if c["assigned_salesman_id"] is not None)
    pinned_cnt = sum(1 for c in cust_rows if c["pinned_salesman_id"] is not None)
    neither = [
        c
        for c in cust_rows
        if c["pinned_salesman_id"] is None and c["assigned_salesman_id"] is None
    ]
    out.append(f"\n## 10. Assignment coverage (Phase 7a) — {len(neither)} with no effective salesman\n")
    out.append(
        f"- Customers with `assigned_salesman_id` set (algorithm output): **{assigned_cnt:,}**"
    )
    out.append(
        f"- Customers with `pinned_salesman_id` set (user override or import pin): **{pinned_cnt:,}**"
    )
    out.append(
        f"- Customers with NEITHER set (no effective salesman): **{len(neither):,}**"
    )
    if neither:
        out.append("\nFirst 10 with no effective salesman:\n")
        out.append("| Code | Name | Area | Freq | Coords |")
        out.append("|---|---|---|---|---|")
        for c in neither[:10]:
            coords = "ok" if c["lat"] is not None and c["lng"] is not None else "—"
            out.append(
                f"| {c['external_code']} | {c['name']} | {c['area'] or '—'} | "
                f"{c['monthly_frequency']} | {coords} |"
            )

    # Per-salesman load using COALESCE(pinned, assigned) — the effective mapping
    # the solver will see in Phase 7b. Shows the load distribution the user has
    # locked in so far.
    per_salesman_load: dict[int, dict[str, int]] = {}
    for c in cust_rows:
        effective = c["pinned_salesman_id"] or c["assigned_salesman_id"]
        if effective is None:
            continue
        slot = per_salesman_load.setdefault(effective, {"count": 0, "load": 0})
        slot["count"] += 1
        slot["load"] += c["facetime_minutes"] * max(c["monthly_frequency"], 1)

    if per_salesman_load:
        out.append("\nPer-salesman effective load (`COALESCE(pin, assigned)`):\n")
        out.append("| Salesman | Customer count | Load (min) |")
        out.append("|---|---|---|")
        for sid, slot in sorted(
            per_salesman_load.items(), key=lambda kv: -kv[1]["load"]
        ):
            srow = salesmen.get(sid)
            sname = srow["name"] if srow is not None else f"#{sid}"
            out.append(f"| {sname} | {slot['count']:,} | {slot['load']:,} |")

    print("\n".join(out))
    return 0


if __name__ == "__main__":
    sys.exit(main())
