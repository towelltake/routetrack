"""Quick targeted look at per-(salesman, week) day distribution for the latest plan.

Lists which salesmen are consolidating into fewer days than they have available,
and the per-day load for Nizwa/Rumais/Salalah specifically.
"""

from __future__ import annotations

import os
import sqlite3
import sys
from collections import defaultdict
from datetime import date
from pathlib import Path

DB_PATH = Path(os.environ["APPDATA"]) / "@journey" / "app" / "journey-plan.db"
uri = f"file:{DB_PATH.as_posix()}?mode=ro"
conn = sqlite3.connect(uri, uri=True)
conn.row_factory = sqlite3.Row

# Latest plan
plan = conn.execute(
    "SELECT id, name, period_start, period_end FROM journey_plans ORDER BY created_at DESC LIMIT 1"
).fetchone()
if plan is None:
    sys.stderr.write("No plans found.\n")
    sys.exit(1)
print(f"Plan #{plan['id']} — {plan['name']}")
print(f"  Period: {plan['period_start']} → {plan['period_end']}")
print()

# Salesmen + their working day count
salesmen = {
    r["id"]: dict(r)
    for r in conn.execute(
        "SELECT id, name, working_days_csv, working_hours_start, working_hours_end FROM salesmen"
    )
}

# Visits grouped by (salesman, date)
visits = conn.execute(
    """SELECT v.salesman_id, v.scheduled_date, v.scheduled_start_time, v.sequence,
              v.drive_minutes_to, v.facetime_minutes
       FROM visits v
       WHERE v.journey_plan_id = ?
       ORDER BY v.salesman_id, v.scheduled_date, v.sequence""",
    (plan["id"],),
).fetchall()

per_sm_day = defaultdict(lambda: defaultdict(list))
for v in visits:
    per_sm_day[v["salesman_id"]][v["scheduled_date"]].append(dict(v))


def hhmm_to_min(s: str) -> int:
    h, m = s.split(":")
    return int(h) * 60 + int(m)


def dow_name(iso_date: str) -> str:
    d = date.fromisoformat(iso_date)
    return ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"][d.weekday()]


# Print per-salesman summary; flag salesmen whose working_days exceed days_used
print("Per-salesman day usage")
print("=" * 80)
print(f"{'Salesman':<30} {'Wd':<3} {'Used':<5} {'Vis':<5} {'Empty days':<20}")
print("-" * 80)
for sid, days_map in sorted(per_sm_day.items(), key=lambda x: salesmen.get(x[0], {}).get("name", "")):
    sm = salesmen.get(sid)
    if not sm:
        continue
    work_dows = set(int(x) for x in sm["working_days_csv"].split(","))
    used_dates = sorted(days_map.keys())
    used_count = len(used_dates)

    # Count "potential" days in the plan period that fall on working DOWs.
    # Cap at 28 days because the VRP only solves 4 weeks regardless of period_end.
    from datetime import timedelta
    pstart = date.fromisoformat(plan["period_start"])
    pend = min(date.fromisoformat(plan["period_end"]), pstart + timedelta(days=27))
    potential_days = []
    d = pstart
    while d <= pend:
        our_dow = (d.weekday() + 1) % 7  # Sun=0..Sat=6
        if our_dow in work_dows:
            potential_days.append(d.isoformat())
        d += timedelta(days=1)

    empty_days = sorted(set(potential_days) - set(used_dates))
    total_visits = sum(len(vs) for vs in days_map.values())

    print(
        f"{sm['name']:<30} {len(work_dows):<3} {used_count:<5} {total_visits:<5} "
        f"{(str(len(empty_days)) + ' empty (' + ', '.join(empty_days[:3]) + ('…' if len(empty_days) > 3 else '') + ')') if empty_days else '—':<40}"
    )

# Deep dive on Nizwa, Rumais, Salalah
print()
print("Per-day load detail (Nizwa / Rumais / Salalah salesmen)")
print("=" * 80)
for sid, sm in salesmen.items():
    name_lc = sm["name"].lower()
    if not any(k in name_lc for k in ("nizwa", "rumais", "salalah")):
        continue
    print()
    print(f"## {sm['name']} (id {sid})")
    work_start = hhmm_to_min(sm["working_hours_start"])
    work_end = hhmm_to_min(sm["working_hours_end"])
    print(f"   Working hours: {sm['working_hours_start']}-{sm['working_hours_end']} ({work_end - work_start} min window)")
    print(f"   Working DOWs: {sm['working_days_csv']}")
    days_map = per_sm_day.get(sid, {})
    if not days_map:
        print("   (no visits)")
        continue
    for d_iso in sorted(days_map.keys()):
        vs = days_map[d_iso]
        face_sum = sum(v["facetime_minutes"] or 0 for v in vs)
        drive_sum = sum(v["drive_minutes_to"] or 0 for v in vs)
        last = vs[-1]
        last_start = hhmm_to_min(last["scheduled_start_time"]) if ":" in last["scheduled_start_time"] else 0
        last_end_offset = last_start + (last["facetime_minutes"] or 0)
        print(
            f"   {d_iso} ({dow_name(d_iso)}): {len(vs)} visits · "
            f"face {face_sum}m · drive {drive_sum}m · total {face_sum + drive_sum}m / {work_end - work_start}m window · "
            f"ends ~{last_end_offset // 60:02d}:{last_end_offset % 60:02d} (clock)"
        )
