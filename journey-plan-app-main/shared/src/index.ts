/**
 * Shared types between Electron (TS) and Python sidecar (Pydantic).
 * Python models in sidecar/sidecar/models.py are the source of truth for wire types —
 * keep these in sync manually for now (Phase 5 will codegen these).
 */

export interface HealthResponse {
  status: 'ok' | 'starting' | 'error';
  version: string;
  sidecarPid: number;
}

// FMCG channel taxonomy. MT = Modern Trade (hypermarkets, supermarkets, chains).
// TT = Traditional Trade (small grocers, kiosks, neighborhood stores).
// WS = Wholesale (bulk-buying distributors, cash-and-carry). A customer carries
// at most one channel; a salesman can carry any subset as skills.
export type SalesChannel = 'MT' | 'TT' | 'WS';

export interface Salesman {
  id: number;
  name: string;
  startLocationLat: number;
  startLocationLng: number;
  workingDays: number[]; // 0=Sun, 1=Mon, ..., 6=Sat
  workingHoursStart: string; // "08:00"
  workingHoursEnd: string; // "17:00"
  assignedAreas: string[]; // empty = no area restriction
  // Phase 9 — broader region coverage (e.g. "Nizwa") satisfied by customers
  // whose `region` is in this list, even when their narrow `area` (a sub-wilayat
  // like "Dhank" or "Ibri") doesn't appear in assignedAreas. Both empty = no
  // area/region restriction at all.
  assignedRegions: string[];
  channelSkills: SalesChannel[]; // empty = no channel restriction
  // Opt-in: price + schedule the home→first and last→home commute legs from
  // the start location. False = legacy behavior (day starts at first customer).
  includeCommute: boolean;
}

export interface CustomerDataset {
  id: number;
  name: string;
  sourceFilename: string;
  importedAt: string; // ISO datetime
  isActive: boolean;
  rowCount: number;
}

export interface Customer {
  id: number;
  datasetId: number;
  externalCode: string;
  name: string;
  address: string | null;
  lat: number | null;
  lng: number | null;
  facetimeMinutes: number;
  monthlyFrequency: number;
  allowedDays: number[] | null; // null = any working day
  pinnedSalesmanId: number | null;
  // Phase 7a — algorithm-computed salesman assignment, distinct from the
  // manual pin. Effective salesman for the solver (Phase 7b) =
  // pinnedSalesmanId ?? assignedSalesmanId.
  assignedSalesmanId: number | null;
  area: string | null;
  // Phase 9 — broader free-text region (typically a parent of `area`). Used as
  // a second eligibility match against salesman.assignedRegions. Null = no
  // region constraint to satisfy.
  region: string | null;
  channel: SalesChannel | null; // null = no channel restriction
  // Optional visit time-of-day window ("HH:MM"), e.g. MT receiving hours.
  // Soft solver constraint — out-of-window visits paint red, never drop.
  // Both set or both null.
  visitWindowStart: string | null;
  visitWindowEnd: string | null;
}

export interface Visit {
  id: number;
  journeyPlanId: number;
  salesmanId: number;
  customerId: number;
  scheduledDate: string; // ISO date
  scheduledStartTime: string; // "HH:MM"
  sequence: number; // order within the day
  driveMinutesTo: number | null;
  facetimeMinutes: number | null;
}

export type PlanStatus = 'draft' | 'final';

export interface JourneyPlan {
  id: number;
  name: string;
  datasetId: number | null;
  periodStart: string; // ISO date
  periodEnd: string; // ISO date
  status: PlanStatus;
  createdAt: string; // ISO datetime
}

export interface SolverLog {
  objectiveValue: number;
  runtimeSeconds: number;
  solverStatus: 'success' | 'partial' | 'infeasible' | 'timeout' | 'error';
  unassignedCustomers: { customerId: number; reason: string }[];
  skippedCustomers: { customerId: number; reason: string }[]; // e.g. no geocode
  matrixCellsRequested: number;
  matrixCellsCached: number;
}

export interface PlanWithVisits {
  plan: JourneyPlan;
  visits: Visit[];
  // Plan-scoped snapshot of the roster at generation time (migration 0013).
  // Screens must resolve visit.salesmanId against THIS list, not the live
  // roster — roster edits/deletes never change an existing plan.
  salesmen: Salesman[];
  solverLog: SolverLog | null;
}

// Road distance driven per plan, reconstructed at render time from the
// persisted route legs + distance_cache (no distance column is stored on
// visits). Mirrors the drive-TIME semantics: covers every customer→customer
// hop plus the home→first commute leg when the salesman had commute enabled;
// the final leg home is excluded, exactly like the drive-time total.
export interface PlanDistance {
  totalMeters: number;
  perSalesman: { salesmanId: number; meters: number }[];
  legsFound: number;
  legsMissing: number; // legs with no cache hit (excluded from the totals)
}

export interface AppSettings {
  defaultFacetimeMinutes: number;
  defaultMonthlyFrequency: number;
  defaultWorkingDays: number[];
  defaultWorkingHoursStart: string;
  defaultWorkingHoursEnd: string;
}

// ---- Matrix wire types (POST /matrix/batch) — backed by OSRM ----

export type MatrixCellStatus = 'ok' | 'zero_results' | 'not_found' | 'error';

export interface MatrixPoint {
  lat: number;
  lng: number;
}

export interface MatrixBatchRequest {
  origins: MatrixPoint[];
  destinations: MatrixPoint[];
}

export interface MatrixCell {
  originIndex: number;
  destIndex: number;
  durationSeconds: number | null;
  distanceMeters: number | null;
  status: MatrixCellStatus;
}

export interface MatrixBatchResponse {
  cells: MatrixCell[];
}

// ---- Optimizer wire types (POST /optimize) ----

export type SolverStatus = 'success' | 'partial' | 'infeasible' | 'timeout' | 'error';

export interface OptimizeCustomer {
  id: number;
  lat: number;
  lng: number;
  facetimeMinutes: number;
  monthlyFrequency: number;
  allowedDays?: number[] | null;
  pinnedSalesmanId?: number | null;
  area?: string | null;
  region?: string | null;
  channel?: SalesChannel | null;
  visitWindowStart?: string | null;
  visitWindowEnd?: string | null;
}

export interface OptimizeSalesman {
  id: number;
  workingDays: number[];
  workingHoursStart: string;
  workingHoursEnd: string;
  assignedAreas: string[];
  assignedRegions?: string[];
  channelSkills?: SalesChannel[];
  // Used by /assign-salesmen for iter-0 medoid seeding, and by the VRP when
  // includeCommute is set. Existing callers that don't populate them keep
  // working.
  startLocationLat?: number;
  startLocationLng?: number;
  // Opt-in commute legs (home→first, last→home). Default false.
  includeCommute?: boolean;
}

export interface OptimizeRequest {
  periodStart: string;
  periodEnd: string;
  salesmen: OptimizeSalesman[];
  customers: OptimizeCustomer[];
  matrixCells: MatrixCell[];
}

export interface OptimizeVisit {
  customerId: number;
  salesmanId: number;
  scheduledDate: string;
  scheduledStartTime: string;
  sequence: number;
  driveMinutesTo: number;
}

export interface UnassignedCustomer {
  customerId: number;
  reason: string;
}

export interface OptimizeResponse {
  visits: OptimizeVisit[];
  unassignedCustomers: UnassignedCustomer[];
  objectiveValue: number;
  runtimeSeconds: number;
  solverStatus: SolverStatus;
}

// ---- Team-size suggestion (POST /optimize/suggest-team-size) ----

export interface TeamSizeTemplate {
  workingDays: number[];
  workingHoursStart: string;
  workingHoursEnd: string;
  maxCustomersPerDay?: number | null;
  // 0-100. Stop growing the team once avg utilization drops below this.
  // Higher = fewer, busier salesmen + some unassigned customers.
  // Lower (or 0) = grow until everyone fits, lower per-head utilization.
  targetUtilizationPct?: number;
}

export interface SuggestTeamSizeRequest {
  customers: OptimizeCustomer[];
  matrixCells: MatrixCell[];
  template: TeamSizeTemplate;
}

export interface SuggestedSalesman {
  index: number;
  assignedCustomerIds: number[];
  suggestedAreaLabel: string;
  suggestedHomeLat: number;
  suggestedHomeLng: number;
}

export interface SuggestTeamSizeResponse {
  recommendedSalesmen: SuggestedSalesman[];
  unassignedCustomers: UnassignedCustomer[];
  runtimeSeconds: number;
  solverStatus: SolverStatus;
}

// ---- Route waypoints (POST /route/osrm) ----

export interface DirectionsRequest {
  waypoints: MatrixPoint[];
}

// OSRM route — sole polyline source after Google Directions was removed
// 2026-05-22. Plain coord list, ready to feed straight into react-leaflet's
// <Polyline positions={...}>.
export interface OsrmRouteResponse {
  coordinates: MatrixPoint[];
  totalDistanceMeters: number;
  totalDurationSeconds: number;
}

// ---- Suggest Team Size template (POST /optimize/suggest-team-size) ----

// ---- Single-day re-sequencer (POST /optimize/resequence-day) ----

export interface ResequenceDayRequest {
  salesman: OptimizeSalesman;
  customers: OptimizeCustomer[];
  matrixCells: MatrixCell[];
  date: string; // ISO date
}

export interface ResequenceDayResponse {
  visits: OptimizeVisit[];
  unassignedCustomers: UnassignedCustomer[];
  solverStatus: SolverStatus;
  runtimeSeconds: number;
}

// ---- Salesman assignment (POST /assign-salesmen) — Phase 7a ----

export interface AssignSalesmenRequest {
  customers: OptimizeCustomer[];
  salesmen: OptimizeSalesman[];
  matrixCells: MatrixCell[];
  balanceLambda?: number;
}

export interface CustomerAssignment {
  customerId: number;
  salesmanId: number;
  travelTimeSeconds: number;
}

export interface UnassignableCustomer {
  customerId: number;
  reason: string; // 'no_eligible_salesman' | 'no_area' | 'no_coordinates'
}

export interface AssignSalesmenResponse {
  assignments: CustomerAssignment[];
  unassignable: UnassignableCustomer[];
  perSalesmanLoadMinutes: Record<number, number>;
  targetLoadMinutes: number;
  runtimeSeconds: number;
  solverStatus: SolverStatus;
  medoidIterations: number;
}

export interface AssignmentRun {
  datasetId: number;
  computedAt: string;
  customerCount: number;
  salesmanCount: number;
  unassignableCount: number;
  runtimeSeconds: number;
  solverStatus: SolverStatus;
}

export interface AssignmentStaleness {
  stale: boolean;
  reason: string;
}

export interface AssignmentStatus {
  lastRun: AssignmentRun | null;
  staleness: AssignmentStaleness;
}

export interface AssignmentComputeResult {
  assigned: number;
  unassignable: UnassignableCustomer[];
  perSalesmanLoadMinutes: Record<number, number>;
  targetLoadMinutes: number;
  runtimeSeconds: number;
  solverStatus: SolverStatus;
}

// ---- Importer wire types (renderer ↔ main process IPC) ----

export interface ImportPreview {
  filePath: string;
  sheets: string[];
  selectedSheet: string;
  headers: string[];
  preview: (string | number | null)[][]; // first 50 data rows
  rowCount: number;
}

export type ImportFieldName =
  | 'external_code'
  | 'name'
  | 'address'
  | 'lat'
  | 'lng'
  | 'facetime_minutes'
  | 'monthly_frequency'
  | 'allowed_days'
  | 'pinned_salesman_name'
  | 'area'
  | 'region'
  | 'channel'
  | 'visit_window_start'
  | 'visit_window_end';

export type ImportMapping = Partial<Record<ImportFieldName, string>>;

export interface ImportCommitRequest {
  filePath: string;
  sheet: string;
  mapping: ImportMapping;
  datasetName: string;
}

export interface ImportRowError {
  rowNumber: number; // 1-indexed including header
  externalCode: string | null;
  errors: string[]; // human-readable messages
}

export interface ImportCommitResult {
  datasetId: number;
  inserted: number;
  errors: ImportRowError[];
  // Non-fatal warnings — the row was still imported but something was
  // surprising (e.g. `pinned_salesman_name` didn't match any existing
  // salesman, so the pin was silently cleared to NULL).
  warnings: ImportRowError[];
}

export type SidecarPingResult =
  | { ok: true; data: HealthResponse }
  | { ok: false; error: string };

// OSRM health check result. The `ok: false` variant carries a `reason`
// discriminator so the renderer can distinguish a transient sidecar-boot
// window (expected on first launch, shows as yellow "initialising") from a
// real sidecar/HTTP error (shows as red "down"). Pre-split, both surfaced
// as "OSRM unreachable" which misled the user during the normal 1-2s boot.
// `lastFailureReason` on the ok-but-unreachable variant carries the main-
// process explanation of WHY OSRM is down (binary missing, graph missing,
// crashed-post-boot, etc.) so the badge tooltip can show actionable detail
// instead of just "OSRM down".
export type OsrmHealthResult =
  | {
      ok: true;
      reachable: boolean;
      baseUrl: string;
      lastFailureReason?: string;
    }
  | { ok: false; reason: 'sidecar-not-ready' | 'sidecar-error'; error: string };

export type DiagnosticSource = 'main' | 'sidecar' | 'renderer' | 'ipc';

export interface DiagnosticEntry {
  ts: string;
  source: DiagnosticSource;
  message: string;
  stack?: string;
}
