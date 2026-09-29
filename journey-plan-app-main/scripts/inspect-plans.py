#!/usr/bin/env python3
"""Read-only inspector for journey-plan.db — counts plans + visits per plan."""
from __future__ import annotations
import os
import sqlite3
import sys
from pathlib import Path

DB_PATH = Path(os.environ["APPDATA"]) / "@journey" / "app" / "journey-plan.db"

if not DB_PATH.exists():
    print(f"DB not found at {DB_PATH}", file=sys.stderr)
    sys.exit(1)

# Read-only URI so we don't fight the dev app's write lock.
uri = f"file:{DB_PATH.as_posix()}?mode=ro"
con = sqlite3.connect(uri, uri=True)
con.row_factory = sqlite3.Row

plans = list(
    con.execute(
        "SELECT id, name, status, period_start, period_end "
        "FROM journey_plans ORDER BY id"
    )
)
total_visits = con.execute("SELECT COUNT(*) FROM visits").fetchone()[0]
by_plan = {
    row["journey_plan_id"]: row["c"]
    for row in con.execute(
        "SELECT journey_plan_id, COUNT(*) AS c FROM visits GROUP BY journey_plan_id"
    )
}

print(f"DB: {DB_PATH}")
print(f"Total plans: {len(plans)}")
print(f"Total visits: {total_visits}")
print("---")
for p in plans:
    v = by_plan.get(p["id"], 0)
    print(
        f"  plan#{p['id']:>3}  [{p['status']:>5}]  "
        f"{p['period_start']} -> {p['period_end']}  visits={v}  "
        f"\"{p['name']}\""
    )

con.close()
