"""Cycle (week) assignment — runs BEFORE the per-week VRP.

Decides which of the 4 calendar weeks (W0..W3) each customer must be visited in,
honouring the locked frequency-spread rules from CLAUDE.md / MEMORY.md and the
2026-05-17 session decisions:

- freq=1: any one of W0..W3 — prefer a cluster-hot week, capped so no week
  runs away with the cluster; ties break on that salesman's per-week load.
- freq=2: hard constraint — exactly {W0,W2} or {W1,W3}. Pick per balance.
- freq=3: hard constraint (2026-06-10) — {W0,W1,W3} or {W0,W2,W3}, the only
  3-of-4 patterns with a gap week inside the month. Consecutive runs like
  {W0,W1,W2} (then a 2-week hole) are excluded. Pick per heat then balance.
- freq=4: all four weeks (one visit each).
- freq>4: multi-visit per week. floor(freq/4) visits per week, with the
  remainder distributed to the lightest weeks. E.g. freq=16 → 4/week.

Return value carries multiplicity: `assignments[c.id]` is a list of week
indices with each index repeated as many times as there are visits in that
week. assemble.py then expands each entry into a separate VRP node.

This is a greedy load-balancer (heaviest customer first) rather than a MIP; it's
deterministic, fast, and good enough for the workloads we're targeting.
Lexicographic priorities: hard frequency-pattern rules are absolute; balance is
the soft objective.

Cluster-aware tiebreak (2026-05-20): for customers with both a (effective)
salesman pin and a non-empty area, we track per-(salesman, area) cluster heat —
which weeks the salesman is already committed to visit that area in. When
choosing weeks for a flexible-frequency customer (freq=1/2/3 and the extras of
freq>4), prefer weeks where the same cluster is already touched, breaking ties
by load. This prevents `cycle_assignment` from scattering same-cluster customers
across weeks where the salesman has to make a fresh trip to the area instead of
piggy-backing on a visit they were already making. The VRP's per-day fixed cost
already bundles same-cluster customers onto the same day WITHIN a week; this
extension extends the same logic to the WEEK selection. Effective salesman =
`pinned_salesman_id` — planner.ts already coalesces `assignedSalesmanId` into
this field before sending.

Per-salesman balance + freq=1 bundle cap (2026-09-21): two fixes to the same
class of bug the freq=2 cap below addressed.
1. Week load is tracked PER effective salesman (unpinned customers share one
   pool), not roster-wide. The old global counter could read perfectly flat
   while individual salesmen sat idle two weeks out of four.
2. freq=1 bundling is capped the same way freq=2 is — a week may run at most
   one customer ahead of the cluster's lightest week. Uncapped, the heat
   preference was absorbing: the first freq=1 customer made its week hot and
   every later one in the cluster piled onto it, so a territory of 40 freq=1
   customers collapsed into W0.

freq=2 bundle cap (2026-05-21): a cluster with many freq=2 customers and no
counter-heat (e.g. Rumais: 18 freq=2 customers in one (salesman, area) cluster)
used to pile every freq=2 onto the FIRST pattern picked, leaving the opposite
pair starved (weeks 2 & 4 got +18 visits, weeks 1 & 3 got 0). We now cap the
freq=2 bundling so the hot side may be at most one customer ahead of the cold
side per cluster. Small clusters (≤2 freq=2) stay fully bundled; large clusters
alternate cleanly. No other branch is affected.
"""

from __future__ import annotations

from sidecar.models import OptimizeCustomer

NUM_WEEKS = 4


def _cluster_key(c: OptimizeCustomer) -> tuple[int, str] | None:
    """Return the (salesman_id, area) cluster key, or None if the customer has
    no effective salesman pin or no area tag (cluster bundling doesn't apply)."""
    if c.pinned_salesman_id is None:
        return None
    if c.area is None or c.area == "":
        return None
    return (c.pinned_salesman_id, c.area)


def assign_weeks(customers: list[OptimizeCustomer]) -> dict[int, list[int]]:
    """Return a {customer_id → sorted list of week indices [0..NUM_WEEKS-1]} mapping.

    For freq>4 customers, the list contains repeated week indices — one per
    scheduled visit in that week. So len(assignments[c.id]) == monthly_frequency.
    """
    # PER-SALESMAN week load (2026-09-21), keyed by effective salesman id with
    # None for the unpinned pool. This used to be one roster-wide list, which
    # balanced the wrong quantity: with two pinned salesmen and interleaved
    # input, A could end up on weeks {0,0,2,2} and B on {1,1,3,3} — each idle
    # two weeks out of four — while the global totals looked perfectly flat.
    # Balancing each salesman's own weeks implies the global balance too, since
    # a sum of flat sequences is flat.
    week_load: dict[int | None, list[int]] = {}
    # cluster_visits[(salesman_id, area)][week] = number of visits already
    # committed to that (salesman, area) pair in that week. Drives the
    # cluster-aware tiebreak; updated after each customer is placed.
    cluster_visits: dict[tuple[int, str], list[int]] = {}
    # 2026-09-21: per-cluster freq=1 week counters, the freq=1 counterpart of
    # cluster_freq2 below. Without a cap the heat preference is absorbing: the
    # first freq=1 customer makes its week hot, every later freq=1 customer in
    # the cluster then sees hot[w]==0 only for that week and piles on. A
    # cluster of 40 freq=1 customers collapsed entirely into W0, leaving the
    # salesman with three empty weeks in that territory. Same rule as freq=2 —
    # a week may run at most one customer ahead of the cluster's lightest week,
    # so small clusters stay fully bundled and large ones rotate.
    cluster_freq1: dict[tuple[int, str], list[int]] = {}
    # 2026-05-21: per-cluster freq=2 pattern counters. The cluster-bundling
    # heat preference for freq=2 (prefer the already-hot {W0,W2} or {W1,W3}
    # pattern) would otherwise pile every freq=2 customer in a cluster onto
    # the SAME pattern. Pathological when a cluster has many freq=2 customers
    # (Rumais: 18 freq=2 → all on {W1,W3} → weeks 1&3 received 0 freq=2 visits,
    # weeks 2&4 got all 18; +20 visit swing per week). We cap the bundling so
    # the hot side is at most 1 customer ahead — small clusters stay fully
    # bundled (2 → 2/0), large clusters alternate (18 → ~10/8).
    cluster_freq2: dict[tuple[int, str], tuple[int, int]] = {}

    # Heaviest workload first so they pick the freest week pattern. High-freq
    # customers also process first, which is what we want for cluster heat:
    # freq=4 establishes "all weeks are hot for this cluster"; freq=2 customers
    # then pick a pattern based on their own pair's balance; the long tail of
    # freq=1/2 then bundles into existing hot weeks.
    ordered = sorted(
        customers,
        key=lambda c: c.facetime_minutes * max(c.monthly_frequency, 1),
        reverse=True,
    )

    assignments: dict[int, list[int]] = {}
    for c in ordered:
        freq = max(c.monthly_frequency, 1)
        ftime = c.facetime_minutes
        ckey = _cluster_key(c)
        # Balance against THIS salesman's weeks; unpinned customers share one
        # pool because the VRP is still free to hand them to anyone.
        loads = week_load.setdefault(c.pinned_salesman_id, [0] * NUM_WEEKS)
        heat = cluster_visits.get(ckey) if ckey is not None else None
        # Lexicographic tiebreak key: 0 if the cluster is already touched in
        # week w, else 1. `min(..., key=...)` and `sorted(...)` prefer smaller,
        # so 0 (hot) wins. Precomputed as a list to avoid closure-over-loop-var
        # issues (ruff B023) and to keep the key callables in the comprehensions
        # below pure index lookups.
        hot = [
            0 if (heat is not None and heat[w] > 0) else 1 for w in range(NUM_WEEKS)
        ]

        if freq == 1:
            # Prefer a cluster-hot week so the salesman piggy-backs on a trip
            # they are already making, but only among weeks that are not
            # already more than one customer ahead of this cluster's lightest
            # week — otherwise the first hot week absorbs the entire cluster.
            f1 = cluster_freq1.get(ckey) if ckey is not None else None
            if f1 is not None:
                floor_count = min(f1)
                eligible = [w for w in range(NUM_WEEKS) if f1[w] <= floor_count + 1]
            else:
                eligible = list(range(NUM_WEEKS))
            picked = [min(eligible, key=lambda w: (hot[w], loads[w], w))]
            if ckey is not None:
                counts = cluster_freq1.setdefault(ckey, [0] * NUM_WEEKS)
                counts[picked[0]] += 1
        elif freq == 2:
            # Two valid patterns: {W0,W2} and {W1,W3}. Prefer the pattern that
            # overlaps more with the existing cluster heat (so the salesman's
            # fortnightly cadence aligns with the rest of the cluster), capped
            # so the hot side stays at most one customer ahead of the cold side
            # in this cluster — see cluster_freq2 comment above. Fall back to
            # load-balance when heat ties.
            heat_a = (heat[0] + heat[2]) if heat is not None else 0
            heat_b = (heat[1] + heat[3]) if heat is not None else 0
            a_count, b_count = (
                cluster_freq2.get(ckey, (0, 0)) if ckey is not None else (0, 0)
            )
            if heat_a > heat_b:
                # Bundle if not yet 2+ ahead; otherwise flip to relieve imbalance.
                picked = [0, 2] if a_count <= b_count + 1 else [1, 3]
            elif heat_b > heat_a:
                picked = [1, 3] if b_count <= a_count + 1 else [0, 2]
            else:
                load_a = loads[0] + loads[2]
                load_b = loads[1] + loads[3]
                picked = [0, 2] if load_a <= load_b else [1, 3]
            if ckey is not None:
                if picked == [0, 2]:
                    cluster_freq2[ckey] = (a_count + 1, b_count)
                else:
                    cluster_freq2[ckey] = (a_count, b_count + 1)
        elif freq == 3:
            # 3 of 4 weeks WITH at least one gap week inside the pattern
            # (2026-06-10): the only non-consecutive 3-of-4 patterns are
            # {W0,W1,W3} and {W0,W2,W3}. Free pick used to allow {W0,W1,W2},
            # which strings 3 consecutive visits then a 2-week hole into the
            # next month — bad cadence for the outlet. Prefer the pattern
            # touching more cluster-hot weeks; tie-break on total load.
            pattern_a = [0, 1, 3]
            pattern_b = [0, 2, 3]
            heat_a3 = sum(1 for w in pattern_a if hot[w] == 0)
            heat_b3 = sum(1 for w in pattern_b if hot[w] == 0)
            if heat_a3 != heat_b3:
                picked = pattern_a if heat_a3 > heat_b3 else pattern_b
            else:
                load_a3 = sum(loads[w] for w in pattern_a)
                load_b3 = sum(loads[w] for w in pattern_b)
                picked = pattern_a if load_a3 <= load_b3 else pattern_b
        elif freq == NUM_WEEKS:
            picked = list(range(NUM_WEEKS))
        else:
            # freq > NUM_WEEKS: multi-visit per week. Base = floor(freq/NUM_WEEKS)
            # for every week, then sprinkle the remainder onto the currently-
            # lightest weeks. Cluster heat steers the remainder onto already-hot
            # weeks where available — same logic as freq=3. freq is clamped to
            # >= 1 above and the freq==1/2/3/NUM_WEEKS branches cover the rest,
            # so this branch only fires for freq > NUM_WEEKS.
            base = freq // NUM_WEEKS
            extras = freq % NUM_WEEKS
            picked = []
            for w in range(NUM_WEEKS):
                picked.extend([w] * base)
            for w in sorted(
                range(NUM_WEEKS), key=lambda w: (hot[w], loads[w], w)
            )[:extras]:
                picked.append(w)

        for w in picked:
            loads[w] += ftime
        if ckey is not None:
            cluster_heat = cluster_visits.setdefault(ckey, [0] * NUM_WEEKS)
            for w in picked:
                cluster_heat[w] += 1
        assignments[c.id] = sorted(picked)

    return assignments
