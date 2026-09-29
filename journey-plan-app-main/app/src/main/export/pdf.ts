import { app, BrowserWindow, ipcMain } from 'electron';
import { writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const READY_CHANNEL = 'analytics:print-ready';
const READY_TIMEOUT_MS = 20_000;

// A4 portrait printable width at 96 CSS px/in with 0.4 in side margins is
// ~717 px. The hidden window must already be that wide when the charts mount,
// because recharts' ResponsiveContainer bakes the measured pixel width into
// each SVG — printToPDF reflows text to the paper width but never re-measures
// the charts. 740 leaves room for a classic scrollbar.
const PRINT_CONTENT_WIDTH = 740;

export async function renderAnalyticsPdfToFile(opts: {
  planId: number;
  planName: string;
  filePath: string;
}): Promise<void> {
  const win = new BrowserWindow({
    show: false,
    useContentSize: true,
    width: PRINT_CONTENT_WIDTH,
    height: 1100,
    webPreferences: {
      preload: join(__dirname, '../preload/index.js'),
      sandbox: false,
      contextIsolation: true,
      nodeIntegration: false,
      // Hidden windows get their timers/rAF throttled, which would stall
      // recharts' measure pass and the renderer's ready signal forever.
      backgroundThrottling: false,
    },
  });
  try {
    const ready = waitForReadySignal(win);
    const hash = `/analytics?planId=${opts.planId}&print=1`;
    const devUrl = process.env['ELECTRON_RENDERER_URL'];
    if (!app.isPackaged && devUrl) {
      await win.loadURL(`${devUrl}#${hash}`);
    } else {
      await win.loadFile(join(__dirname, '../renderer/index.html'), { hash });
    }
    await ready;
    const pdf = await win.webContents.printToPDF({
      pageSize: 'A4',
      printBackground: true,
      margins: { top: 0.45, bottom: 0.55, left: 0.4, right: 0.4 },
      displayHeaderFooter: true,
      headerTemplate: '<span></span>',
      footerTemplate: pdfFooter(opts.planName),
    });
    await writeFile(opts.filePath, pdf);
  } finally {
    if (!win.isDestroyed()) win.destroy();
  }
}

function waitForReadySignal(win: BrowserWindow): Promise<void> {
  return new Promise<void>((resolve, reject) => {
    const onReady = (evt: Electron.IpcMainEvent): void => {
      if (evt.sender.id !== win.webContents.id) return;
      cleanup();
      resolve();
    };
    const timer = setTimeout(() => {
      cleanup();
      reject(
        new Error(
          `Analytics view did not finish rendering within ${READY_TIMEOUT_MS / 1000}s`,
        ),
      );
    }, READY_TIMEOUT_MS);
    function cleanup(): void {
      clearTimeout(timer);
      ipcMain.removeListener(READY_CHANNEL, onReady);
    }
    ipcMain.on(READY_CHANNEL, onReady);
  });
}

// Chromium header/footer templates render in an isolated document: every
// style must be inline and font-size set explicitly or nothing shows.
function pdfFooter(planName: string): string {
  const safe = planName
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
  return (
    '<div style="width:100%;font-size:8px;font-family:Arial,sans-serif;color:#8a8378;' +
    'padding:0 38px;display:flex;justify-content:space-between;">' +
    `<span>${safe} — Journey Plan analytics</span>` +
    '<span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>' +
    '</div>'
  );
}
