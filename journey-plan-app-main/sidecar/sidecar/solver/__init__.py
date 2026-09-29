"""OR-Tools VRP solver and supporting layers.

Public surface:
    - `run_optimize(request)` → `OptimizeResponse`
    - `run_team_size_suggestion(request)` → `SuggestTeamSizeResponse`
    - `resequence_day(request)` → `ResequenceDayResponse`
    - `run_assign_salesmen(request)` → `AssignSalesmenResponse`  (Phase 7a)
"""

from sidecar.solver.assemble import run_optimize
from sidecar.solver.assign_salesmen import run_assign_salesmen
from sidecar.solver.resequence_day import resequence_day
from sidecar.solver.team_sizing import run_team_size_suggestion

__all__ = [
    "resequence_day",
    "run_assign_salesmen",
    "run_optimize",
    "run_team_size_suggestion",
]
