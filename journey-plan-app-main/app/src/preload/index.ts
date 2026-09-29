import { contextBridge, ipcRenderer } from 'electron';
import type {
  AppSettings,
  AssignmentComputeResult,
  AssignmentStatus,
  Customer,
  CustomerDataset,
  DiagnosticEntry,
  ImportCommitRequest,
  ImportCommitResult,
  ImportPreview,
  JourneyPlan,
  MatrixPoint,
  OsrmHealthResult,
  OsrmRouteResponse,
  PlanDistance,
  PlanStatus,
  PlanWithVisits,
  Salesman,
  SidecarPingResult,
  SuggestTeamSizeResponse,
  TeamSizeTemplate,
} from '@journey/shared';

export interface ListCustomersFilter {
  search?: string;
  limit?: number;
  offset?: number;
}

export interface GeneratePlanInput {
  name: string;
  periodStart: string;
  periodEnd: string;
}

export interface PlanProgressEvent {
  phase: 'loading' | 'clustering' | 'matrix' | 'optimizing' | 'saving' | 'done';
  message: string;
  percent: number;
}

const api = {
  pingSidecar: (): Promise<SidecarPingResult> => ipcRenderer.invoke('sidecar:ping'),
  osrmHealth: (): Promise<OsrmHealthResult> => ipcRenderer.invoke('osrm:health'),
  appVersion: (): Promise<string> => ipcRenderer.invoke('app:version'),

  // Datasets
  listDatasets: (): Promise<CustomerDataset[]> => ipcRenderer.invoke('datasets:list'),
  activateDataset: (id: number): Promise<CustomerDataset | null> =>
    ipcRenderer.invoke('datasets:activate', id),
  getActiveDataset: (): Promise<CustomerDataset | null> => ipcRenderer.invoke('datasets:active'),
  deleteDataset: (id: number): Promise<{ plans: number; customers: number }> =>
    ipcRenderer.invoke('datasets:delete', id),

  // Customers
  listCustomers: (opts: ListCustomersFilter): Promise<Customer[]> =>
    ipcRenderer.invoke('customers:list', opts),
  upsertCustomer: (c: Customer): Promise<number> => ipcRenderer.invoke('customers:upsert', c),
  deleteCustomer: (id: number): Promise<void> => ipcRenderer.invoke('customers:delete', id),
  distinctAreas: (): Promise<string[]> => ipcRenderer.invoke('customers:distinctAreas'),
  distinctRegions: (): Promise<string[]> => ipcRenderer.invoke('customers:distinctRegions'),

  // Salesmen
  listSalesmen: (): Promise<Salesman[]> => ipcRenderer.invoke('salesmen:list'),
  upsertSalesman: (s: Salesman): Promise<number> => ipcRenderer.invoke('salesmen:upsert', s),
  deleteSalesman: (id: number): Promise<void> => ipcRenderer.invoke('salesmen:delete', id),
  clearAllSalesmen: (): Promise<number> => ipcRenderer.invoke('salesmen:clearAll'),

  // Assignments (Phase 7a)
  computeAssignments: (opts?: { balanceLambda?: number }): Promise<AssignmentComputeResult> =>
    ipcRenderer.invoke('assignments:compute', opts),
  setAssignmentOverride: (customerId: number, salesmanId: number | null): Promise<boolean> =>
    ipcRenderer.invoke('assignments:setOverride', customerId, salesmanId),
  clearAllAssignmentOverrides: (): Promise<number> =>
    ipcRenderer.invoke('assignments:clearAllOverrides'),
  getAssignmentStatus: (): Promise<AssignmentStatus | null> =>
    ipcRenderer.invoke('assignments:status'),

  // Importer
  openImportFile: (): Promise<ImportPreview | null> => ipcRenderer.invoke('import:open'),
  reparseSheet: (filePath: string, sheet: string): Promise<ImportPreview> =>
    ipcRenderer.invoke('import:reparse', filePath, sheet),
  commitImport: (req: ImportCommitRequest): Promise<ImportCommitResult> =>
    ipcRenderer.invoke('import:commit', req),

  // Settings
  getSettings: (): Promise<AppSettings> => ipcRenderer.invoke('settings:get'),
  setSettings: (s: AppSettings): Promise<void> => ipcRenderer.invoke('settings:set', s),

  // Plans
  generatePlan: (input: GeneratePlanInput): Promise<{ planId: number }> =>
    ipcRenderer.invoke('plan:generate', input),
  onPlanProgress: (callback: (evt: PlanProgressEvent) => void): (() => void) => {
    const listener = (_: unknown, payload: PlanProgressEvent) => callback(payload);
    ipcRenderer.on('plan:progress', listener);
    return () => ipcRenderer.removeListener('plan:progress', listener);
  },
  listPlans: (): Promise<JourneyPlan[]> => ipcRenderer.invoke('plan:listForDataset'),
  getPlan: (id: number): Promise<PlanWithVisits | null> => ipcRenderer.invoke('plan:get', id),
  planDistance: (id: number): Promise<PlanDistance> => ipcRenderer.invoke('plan:distance', id),
  suggestTeamSize: (template: TeamSizeTemplate): Promise<SuggestTeamSizeResponse> =>
    ipcRenderer.invoke('plan:suggestTeamSize', template),
  defaultPeriod: (): Promise<{ periodStart: string; periodEnd: string }> =>
    ipcRenderer.invoke('plan:defaultPeriod'),
  setPlanStatus: (id: number, status: PlanStatus): Promise<JourneyPlan | null> =>
    ipcRenderer.invoke('plan:setStatus', id, status),
  deletePlan: (id: number): Promise<void> => ipcRenderer.invoke('plan:delete', id),
  reassignVisit: (input: {
    planId: number;
    visitId: number;
    toSalesmanId: number;
    toDate: string;
  }): Promise<void> => ipcRenderer.invoke('plan:reassignVisit', input),
  exportPlanExcel: (planId: number): Promise<{ savedPath: string } | null> =>
    ipcRenderer.invoke('plan:exportExcel', planId),

  // Analytics PDF snapshot
  exportAnalyticsPdf: (planId: number): Promise<{ savedPath: string } | null> =>
    ipcRenderer.invoke('analytics:exportPdf', planId),
  // Fired by the analytics screen in print mode once charts have painted, so
  // the hidden export window (main/export/pdf.ts) knows when to printToPDF.
  analyticsPrintReady: (): void => ipcRenderer.send('analytics:print-ready'),

  // Route geometry (OSRM)
  osrmRouteForWaypoints: (waypoints: MatrixPoint[]): Promise<OsrmRouteResponse> =>
    ipcRenderer.invoke('osrm:routeForWaypoints', waypoints),

  // Diagnostics
  readDiagnostics: (limit?: number): Promise<DiagnosticEntry[]> =>
    ipcRenderer.invoke('diagnostics:read', limit),
  clearDiagnostics: (): Promise<boolean> => ipcRenderer.invoke('diagnostics:clear'),
  appendDiagnostic: (entry: { message: string; stack?: string }): Promise<boolean> =>
    ipcRenderer.invoke('diagnostics:appendFromRenderer', entry),
};

contextBridge.exposeInMainWorld('api', api);

export type JourneyApi = typeof api;
