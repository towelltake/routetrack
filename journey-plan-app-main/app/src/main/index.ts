import { app, BrowserWindow, dialog, ipcMain, shell } from 'electron';
import { join } from 'node:path';
import { startSidecar, stopSidecar, getSidecarUrl } from './sidecar';
import { getOsrmLastFailureReason, getOsrmUrl, startOsrm, stopOsrm } from './osrm';
import { backupDb, closeDb, integrityCheckQuick, quarantineDb, runMigrations } from './db';
import {
  appendError,
  clearErrors,
  flushPendingDiagnostics,
  installMainProcessHandlers,
  readErrors,
} from './diagnostics';
import {
  activateDataset,
  deleteDataset,
  getActiveDataset,
  listDatasets,
} from './repo/datasets';
import {
  clearAllOverridesForDataset,
  deleteCustomer,
  distinctAreasForDataset,
  distinctRegionsForDataset,
  listCustomers,
  setUserAssignmentOverride,
  upsertCustomer,
} from './repo/customers';
import { getLatestRun, getStaleness } from './repo/assignmentRuns';
import {
  clearAllSalesmen,
  deleteSalesman,
  getSalesman,
  listSalesmen,
  upsertSalesman,
} from './repo/salesmen';
import { listPlanSalesmen } from './repo/planSalesmen';
import {
  deletePlan,
  getPlan,
  getSolverLog,
  listPlansForDataset,
  setPlanStatus,
} from './repo/journeyPlans';
import { listVisitsForPlan } from './repo/visits';
import { getSettings, updateSettings } from './repo/settings';
import { buildPreview } from './import/excel';
import { commitImport } from './import/commit';
import {
  assertValid,
  customerUpsertSchema,
  salesmanUpsertSchema,
} from './import/validate';
import {
  computeAssignments,
  defaultPeriodEnd,
  defaultPeriodStart,
  generatePlan,
  osrmRouteForVisits,
  reassignVisit,
  suggestTeamSize,
} from './planner';
import { exportPlanToExcel } from './export/excel';
import { renderAnalyticsPdfToFile } from './export/pdf';
import { distanceForPlan } from './planDistance';
import type {
  AppSettings,
  AssignmentStatus,
  Customer,
  HealthResponse,
  ImportCommitRequest,
  MatrixPoint,
  OsrmHealthResult,
  PlanStatus,
  PlanWithVisits,
  Salesman,
  SidecarPingResult,
  TeamSizeTemplate,
} from '@journey/shared';

const isDev = !app.isPackaged;

// Split userData between dev and the packaged installer so an HMR restart,
// schema migration experiment, or destructive debug flow during development
// can't touch the user's real journey-plan DB / error.log / db-backups. The
// two share the same package.json `name` (`@journey/app`), which is what
// Electron derives userData from by default — without this override, both
// resolve to `%APPDATA%\@journey\app\`. Must run BEFORE any other code calls
// `app.getPath('userData')` (e.g. installMainProcessHandlers below subscribes
// uncaughtException to appendError, and appendError reads the userData path
// to know where error.log lives).
if (isDev) {
  app.setPath('userData', join(app.getPath('appData'), '@journey', 'app-dev'));
}

type IpcHandler = Parameters<typeof ipcMain.handle>[1];

function safeHandle(channel: string, handler: IpcHandler): void {
  ipcMain.handle(channel, async (event, ...args) => {
    try {
      return await handler(event, ...args);
    } catch (err) {
      const e = err instanceof Error ? err : new Error(String(err));
      appendError({ source: 'ipc', message: `${channel}: ${e.message}`, stack: e.stack });
      throw err;
    }
  });
}

interface ListCustomersFilter {
  search?: string;
  limit?: number;
  offset?: number;
}

async function createWindow() {
  const win = new BrowserWindow({
    width: 1400,
    height: 900,
    minWidth: 1024,
    minHeight: 700,
    show: false,
    autoHideMenuBar: true,
    webPreferences: {
      preload: join(__dirname, '../preload/index.js'),
      sandbox: false,
      contextIsolation: true,
      nodeIntegration: false,
    },
  });

  win.on('ready-to-show', () => win.show());

  win.webContents.setWindowOpenHandler((details) => {
    shell.openExternal(details.url);
    return { action: 'deny' };
  });

  if (isDev && process.env['ELECTRON_RENDERER_URL']) {
    await win.loadURL(process.env['ELECTRON_RENDERER_URL']);
    win.webContents.openDevTools({ mode: 'detach' });
  } else {
    await win.loadFile(join(__dirname, '../renderer/index.html'));
  }
}

function registerIpc() {
  // ---- Existing channels ----
  safeHandle('sidecar:ping', async (): Promise<SidecarPingResult> => {
    try {
      const url = getSidecarUrl();
      if (!url) return { ok: false, error: 'sidecar not ready' };
      const res = await fetch(`${url}/health`);
      if (!res.ok) return { ok: false, error: `HTTP ${res.status}` };
      const data = (await res.json()) as HealthResponse;
      return { ok: true, data };
    } catch (err) {
      return { ok: false, error: err instanceof Error ? err.message : String(err) };
    }
  });

  safeHandle('osrm:health', async (): Promise<OsrmHealthResult> => {
    const url = getSidecarUrl();
    if (!url) {
      // Sidecar still booting — the renderer surfaces this as a transient
      // "initialising" badge rather than the red "OSRM unreachable" state.
      return { ok: false, reason: 'sidecar-not-ready', error: 'sidecar not ready' };
    }
    try {
      const res = await fetch(`${url}/health/osrm`);
      if (!res.ok) {
        return { ok: false, reason: 'sidecar-error', error: `HTTP ${res.status}` };
      }
      const data = (await res.json()) as { reachable: boolean; baseUrl: string };
      // When OSRM is reported as unreachable, attach the main-process reason
      // (set in osrm.ts on startup failure or post-handshake exit) so the
      // badge tooltip can tell the user WHY — binary missing, graph missing,
      // crashed post-boot — instead of just "OSRM down".
      const failureReason = data.reachable ? null : getOsrmLastFailureReason();
      return {
        ok: true,
        reachable: data.reachable,
        baseUrl: data.baseUrl,
        ...(failureReason ? { lastFailureReason: failureReason } : {}),
      };
    } catch (err) {
      return {
        ok: false,
        reason: 'sidecar-error',
        error: err instanceof Error ? err.message : String(err),
      };
    }
  });

  safeHandle('app:version', () => app.getVersion());

  safeHandle('diagnostics:read', (_evt, limit?: number) => readErrors(limit));
  safeHandle('diagnostics:clear', () => {
    clearErrors();
    return true;
  });
  safeHandle(
    'diagnostics:appendFromRenderer',
    (_evt, entry: { message: string; stack?: string }) => {
      appendError({ source: 'renderer', message: entry.message, stack: entry.stack });
      return true;
    },
  );

  // ---- Datasets ----
  safeHandle('datasets:list', () => listDatasets());
  safeHandle('datasets:active', () => getActiveDataset());
  safeHandle('datasets:activate', (_evt, id: number) => {
    activateDataset(id);
    return getActiveDataset();
  });
  safeHandle('datasets:delete', (_evt, id: number) => {
    return deleteDataset(id);
  });

  // ---- Customers ----
  safeHandle('customers:list', (_evt, filter: ListCustomersFilter) => {
    const dataset = getActiveDataset();
    if (!dataset) return [];
    return listCustomers({ datasetId: dataset.id, ...filter });
  });
  safeHandle('customers:upsert', (_evt, c: Customer) => {
    assertValid(customerUpsertSchema, c, 'Customer');
    return upsertCustomer({
      id: c.id || undefined,
      datasetId: c.datasetId,
      externalCode: c.externalCode,
      name: c.name,
      address: c.address,
      lat: c.lat,
      lng: c.lng,
      facetimeMinutes: c.facetimeMinutes,
      monthlyFrequency: c.monthlyFrequency,
      allowedDays: c.allowedDays,
      pinnedSalesmanId: c.pinnedSalesmanId,
      area: c.area,
      region: c.region,
      channel: c.channel,
      visitWindowStart: c.visitWindowStart,
      visitWindowEnd: c.visitWindowEnd,
    });
  });
  safeHandle('customers:delete', (_evt, id: number) => deleteCustomer(id));
  safeHandle('customers:distinctAreas', () => {
    const dataset = getActiveDataset();
    if (!dataset) return [];
    return distinctAreasForDataset(dataset.id);
  });
  safeHandle('customers:distinctRegions', () => {
    const dataset = getActiveDataset();
    if (!dataset) return [];
    return distinctRegionsForDataset(dataset.id);
  });

  // ---- Salesmen ----
  safeHandle('salesmen:list', () => listSalesmen());
  safeHandle('salesmen:upsert', (_evt, s: Salesman) => {
    assertValid(salesmanUpsertSchema, s, 'Salesman');
    return upsertSalesman({
      id: s.id || undefined,
      name: s.name,
      startLocationLat: s.startLocationLat,
      startLocationLng: s.startLocationLng,
      workingDays: s.workingDays,
      workingHoursStart: s.workingHoursStart,
      workingHoursEnd: s.workingHoursEnd,
      assignedAreas: s.assignedAreas ?? [],
      assignedRegions: s.assignedRegions ?? [],
      channelSkills: s.channelSkills ?? [],
      includeCommute: s.includeCommute ?? false,
    });
  });
  safeHandle('salesmen:delete', (_evt, id: number) => {
    // Safe since migration 0013: plans snapshot their salesmen and visits no
    // longer cascade from the roster, so deleting here only affects FUTURE
    // plan generation. Existing plans (and their analytics) are untouched.
    const s = getSalesman(id);
    if (!s) throw new Error('salesman not found');
    deleteSalesman(id);
  });
  safeHandle('salesmen:clearAll', () => clearAllSalesmen());

  // ---- Assignments (Phase 7a) ----
  safeHandle('assignments:compute', async (_evt, opts?: { balanceLambda?: number }) => {
    const dataset = getActiveDataset();
    if (!dataset) throw new Error('no active dataset');
    // Don't substitute a default here — let undefined flow through so the
    // canonical default in sidecar/models.py:AssignSalesmenRequest fires. One
    // source of truth means future tuning passes only touch the Pydantic line.
    return computeAssignments({
      datasetId: dataset.id,
      balanceLambda: opts?.balanceLambda,
    });
  });
  safeHandle(
    'assignments:setOverride',
    (_evt, customerId: number, salesmanId: number | null) => {
      setUserAssignmentOverride(customerId, salesmanId);
      return true;
    },
  );
  safeHandle('assignments:clearAllOverrides', () => {
    const dataset = getActiveDataset();
    if (!dataset) return 0;
    return clearAllOverridesForDataset(dataset.id);
  });
  safeHandle('assignments:status', (): AssignmentStatus | null => {
    const dataset = getActiveDataset();
    if (!dataset) return null;
    return {
      lastRun: getLatestRun(dataset.id),
      staleness: getStaleness(dataset.id),
    };
  });

  // ---- Importer ----
  safeHandle('import:open', async () => {
    const result = await dialog.showOpenDialog({
      title: 'Choose a customer master file',
      properties: ['openFile'],
      filters: [
        { name: 'Spreadsheets', extensions: ['xlsx', 'xlsm', 'xls', 'csv'] },
      ],
    });
    if (result.canceled || result.filePaths.length === 0) return null;
    return buildPreview(result.filePaths[0]);
  });
  safeHandle('import:reparse', (_evt, filePath: string, sheet: string) =>
    buildPreview(filePath, sheet),
  );
  safeHandle('import:commit', (_evt, req: ImportCommitRequest) => commitImport(req));

  // ---- Settings ----
  safeHandle('settings:get', () => getSettings());
  safeHandle('settings:set', (_evt, s: AppSettings) => updateSettings(s));

  // ---- Plans ----
  safeHandle(
    'plan:generate',
    async (
      evt,
      input: { name: string; periodStart: string; periodEnd: string },
    ) => {
      const dataset = getActiveDataset();
      if (!dataset) throw new Error('no active dataset');
      const sender = evt.sender;
      return generatePlan(
        {
          datasetId: dataset.id,
          name: input.name,
          periodStart: input.periodStart,
          periodEnd: input.periodEnd,
        },
        (progress) => {
          if (!sender.isDestroyed()) sender.send('plan:progress', progress);
        },
      );
    },
  );
  safeHandle('plan:listForDataset', () => {
    const dataset = getActiveDataset();
    if (!dataset) return [];
    return listPlansForDataset(dataset.id);
  });
  safeHandle('plan:get', (_evt, id: number): PlanWithVisits | null => {
    const plan = getPlan(id);
    if (!plan) return null;
    const visits = listVisitsForPlan(id);
    const salesmen = listPlanSalesmen(id);
    const solverLog = getSolverLog(id);
    return { plan, visits, salesmen, solverLog };
  });
  safeHandle('plan:distance', (_evt, id: number) => distanceForPlan(id));
  safeHandle(
    'plan:suggestTeamSize',
    async (_evt, template: TeamSizeTemplate) => {
      const dataset = getActiveDataset();
      if (!dataset) throw new Error('no active dataset');
      return suggestTeamSize({ datasetId: dataset.id, template });
    },
  );
  safeHandle('plan:defaultPeriod', () => {
    const start = defaultPeriodStart();
    return { periodStart: start, periodEnd: defaultPeriodEnd(start) };
  });
  safeHandle('plan:setStatus', (_evt, id: number, status: PlanStatus) => {
    setPlanStatus(id, status);
    return getPlan(id);
  });
  safeHandle('plan:delete', (_evt, id: number) => {
    // Safety: 'final' plans must be unlocked before delete. The user can still
    // toggle them back to draft from the Plan screen; this just prevents an
    // accidental click from wiping a plan that was deliberately locked.
    const plan = getPlan(id);
    if (!plan) throw new Error('plan not found');
    if (plan.status === 'final') {
      throw new Error('Cannot delete a final plan. Unlock it first.');
    }
    deletePlan(id);
  });
  safeHandle(
    'plan:reassignVisit',
    async (
      _evt,
      input: { planId: number; visitId: number; toSalesmanId: number; toDate: string },
    ) => {
      await reassignVisit(input);
    },
  );
  safeHandle(
    'plan:exportExcel',
    async (_evt, planId: number): Promise<{ savedPath: string } | null> => {
      const plan = getPlan(planId);
      if (!plan) throw new Error('plan not found');
      const safeName = plan.name.replace(/[\\/:*?"<>|]/g, '_');
      const result = await dialog.showSaveDialog({
        title: 'Export plan to Excel',
        defaultPath: `${safeName}.xlsx`,
        filters: [{ name: 'Excel workbook', extensions: ['xlsx'] }],
      });
      if (result.canceled || !result.filePath) return null;
      await exportPlanToExcel(planId, result.filePath);
      return { savedPath: result.filePath };
    },
  );

  // ---- Analytics PDF snapshot ----
  safeHandle(
    'analytics:exportPdf',
    async (_evt, planId: number): Promise<{ savedPath: string } | null> => {
      const plan = getPlan(planId);
      if (!plan) throw new Error('plan not found');
      const safeName = plan.name.replace(/[\\/:*?"<>|]/g, '_');
      const result = await dialog.showSaveDialog({
        title: 'Export analytics to PDF',
        defaultPath: `${safeName} — Analytics.pdf`,
        filters: [{ name: 'PDF document', extensions: ['pdf'] }],
      });
      if (result.canceled || !result.filePath) return null;
      await renderAnalyticsPdfToFile({
        planId,
        planName: plan.name,
        filePath: result.filePath,
      });
      return { savedPath: result.filePath };
    },
  );

  // ---- Route geometry (OSRM) ----
  safeHandle(
    'osrm:routeForWaypoints',
    async (_evt, waypoints: MatrixPoint[]) => osrmRouteForVisits(waypoints),
  );
}

installMainProcessHandlers();

// Single-instance lock: if a second copy is launched (e.g. user double-clicks
// the desktop shortcut while the first instance is still starting up), focus
// the existing window instead of spawning a duplicate sidecar + Electron tree.
// Critically: app.quit() is async, so if we DON'T gate whenReady on the lock
// check, the losing instance would still run startSidecar() before quitting
// and leave an orphan sidecar.exe behind.
if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on('second-instance', () => {
    const windows = BrowserWindow.getAllWindows();
    if (windows.length > 0) {
      const win = windows[0]!;
      if (win.isMinimized()) win.restore();
      win.focus();
    }
  });

  app.whenReady().then(async () => {
    // A migration failure means the schema is not what the repo layer expects,
    // so accepting writes would compound the damage. Tell the user and quit
    // cleanly rather than continuing into a half-open app.
    try {
      await runMigrations();
    } catch (err) {
      const reason = err instanceof Error ? err.message : String(err);
      appendError({ source: 'main', message: `DB migrations failed at startup: ${reason}` });
      flushPendingDiagnostics();
      dialog.showErrorBox(
        'Journey Plan App cannot start',
        `The local database could not be migrated:\n\n${reason}\n\n` +
          'Your data has not been changed. Restore a snapshot from the db-backups folder in the app data directory, or contact support.',
      );
      app.quit();
      return;
    }

    // Integrity check + auto-backup at startup. Both run BEFORE any IPC
    // handler is registered, so we never accept writes against a known-bad
    // DB. Backup is a rotating 7-day local snapshot in <userData>/db-backups.
    try {
      const corruption = integrityCheckQuick();
      if (corruption) {
        const quarantined = quarantineDb();
        appendError({
          source: 'main',
          message: `DB integrity_check failed at startup: ${corruption}. Quarantined to ${quarantined}; a fresh DB will be created. Restore from <userData>/db-backups if needed.`,
        });
        // Re-run migrations against the now-fresh DB so the rest of boot proceeds.
        await runMigrations();
      } else {
        const backupPath = await backupDb();
        if (backupPath) {
          appendError({
            source: 'main',
            message: `DB integrity ok. Startup backup written: ${backupPath}`,
          });
        }
      }
    } catch (err) {
      appendError({
        source: 'main',
        message: `DB integrity/backup step failed: ${err instanceof Error ? err.message : String(err)}`,
      });
    }

    // OSRM must come up before the Python sidecar so its env has OSRM_BASE_URL
    // pointing at a live instance. If OSRM fails to start, log and continue —
    // /matrix/batch will return a hard 503 (Google Distance Matrix was removed
    // 2026-05-18 after the $4k incident; there is no fallback path).
    try {
      await startOsrm();
    } catch (err) {
      appendError({
        source: 'main',
        message: `OSRM failed to start: ${err instanceof Error ? err.message : String(err)}`,
      });
    }
    const osrmUrl = getOsrmUrl();
    if (osrmUrl) process.env['OSRM_BASE_URL'] = osrmUrl;

    // A sidecar that won't boot is NOT fatal: the window must open either way
    // so the user can read Diagnostics and see why. Previously this await was
    // unguarded, so a quarantined sidecar.exe aborted the rest of the callback
    // — no window, no IPC, and osrm-routed left running with no way to reach
    // it except Task Manager.
    try {
      await startSidecar();
    } catch (err) {
      appendError({
        source: 'main',
        message: `Sidecar failed to start: ${err instanceof Error ? err.message : String(err)}. Optimizer features will not work this session.`,
      });
    }
    registerIpc();
    await createWindow();

    app.on('activate', () => {
      if (BrowserWindow.getAllWindows().length === 0) void createWindow();
    });
  }).catch((err: unknown) => {
    // Last resort: anything still escaping boot leaves the process alive with
    // no window and both child processes running. Surface it and exit.
    const reason = err instanceof Error ? err.message : String(err);
    appendError({ source: 'main', message: `Startup failed: ${reason}` });
    flushPendingDiagnostics();
    stopSidecar();
    stopOsrm();
    dialog.showErrorBox('Journey Plan App cannot start', reason);
    app.quit();
  });
}

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

app.on('before-quit', () => {
  // Order matters: kill the sidecars first (they only talk over HTTP, no
  // SQLite handles), then checkpoint + close the DB so the WAL is fully
  // flushed into the main file before the process exits. Without closeDb()
  // here, an ungraceful quit can leave the WAL inconsistent with the main
  // file — the corruption pattern we hit on 2026-05-21.
  stopSidecar();
  stopOsrm();
  try {
    closeDb();
  } catch (err) {
    appendError({
      source: 'main',
      message: `closeDb on quit failed: ${err instanceof Error ? err.message : String(err)}`,
    });
  }
  // Drain any queued diagnostics so entries blocked on a transient file lock
  // don't disappear into the exit. Runs LAST so the closeDb error above (if
  // any) has a chance to enqueue first.
  flushPendingDiagnostics();
});
