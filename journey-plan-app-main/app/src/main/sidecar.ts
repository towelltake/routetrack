import { spawn, type ChildProcessWithoutNullStreams } from 'node:child_process';
import { join } from 'node:path';
import { app } from 'electron';
import { appendError } from './diagnostics';
import { killTree } from './processTree';

let proc: ChildProcessWithoutNullStreams | null = null;
let sidecarUrl: string | null = null;
// Set by stopSidecar() so the 'exit' handler can tell a deliberate teardown
// from a crash — without it every normal quit writes a bogus "sidecar exited
// with code 1" into the diagnostics log and buries real failures.
let stopping = false;

const STARTUP_TIMEOUT_MS = 20_000;
const HANDSHAKE_PREFIX = 'SIDECAR_READY port=';

export function getSidecarUrl(): string | null {
  return sidecarUrl;
}

export async function startSidecar(): Promise<void> {
  if (proc) return;
  stopping = false;

  const isDev = !app.isPackaged;
  const osrmBaseUrl = process.env['OSRM_BASE_URL'] ?? 'http://127.0.0.1:5050';

  // In dev we run via uv against sidecar/. In prod we spawn the PyInstaller binary
  // bundled at resources/sidecar/sidecar.exe (set up in Phase 5).
  const [cmd, args, cwd] = isDev
    ? ['uv', ['run', 'python', '-m', 'sidecar.main'], join(process.cwd(), '..', 'sidecar')]
    : [
        join(process.resourcesPath, 'sidecar', process.platform === 'win32' ? 'sidecar.exe' : 'sidecar'),
        [],
        process.resourcesPath,
      ];

  proc = spawn(cmd as string, args as string[], {
    cwd: cwd as string,
    env: {
      ...process.env,
      OSRM_BASE_URL: osrmBaseUrl,
      PYTHONUNBUFFERED: '1',
    },
    // Dev on Windows needs shell:true so `uv` resolves from PATH. Prod must
    // NOT use shell — the bundled sidecar.exe path is absolute, and shell:true
    // routes the spawn through cmd.exe /c, which fails to quote paths with
    // spaces (e.g. Programs\Journey Plan App\...). Without quoting cmd splits
    // at the first space and the spawn fails with "X is not recognized as an
    // internal or external command".
    shell: isDev && process.platform === 'win32',
  });

  return new Promise<void>((resolve, reject) => {
    const timeout = setTimeout(() => {
      reject(new Error('sidecar startup timed out'));
    }, STARTUP_TIMEOUT_MS);

    proc!.stdout.on('data', (chunk: Buffer) => {
      const text = chunk.toString();
      process.stdout.write(`[sidecar] ${text}`);
      const match = text.split(/\r?\n/).find((line) => line.startsWith(HANDSHAKE_PREFIX));
      if (match && !sidecarUrl) {
        const port = match.slice(HANDSHAKE_PREFIX.length).trim();
        sidecarUrl = `http://127.0.0.1:${port}`;
        clearTimeout(timeout);
        resolve();
      }
    });

    proc!.stderr.on('data', (chunk: Buffer) => {
      const text = chunk.toString();
      process.stderr.write(`[sidecar err] ${text}`);
      // uvicorn logs its startup banner at INFO on stderr. Recording those as
      // diagnostics errors adds four bogus entries per launch and pushes real
      // failures off the Diagnostics screen.
      const meaningful = text
        .split(/\r?\n/)
        .filter((line) => line.trim() && !/^\s*(INFO|DEBUG)[:\s]/.test(line))
        .join('\n')
        .trim();
      if (meaningful) appendError({ source: 'sidecar', message: meaningful });
    });

    // Without an 'error' listener a failed spawn (ENOENT — e.g. Defender
    // quarantined sidecar.exe, or `uv` missing from PATH in dev) raises an
    // unhandled 'error' event that takes down the main process.
    proc!.on('error', (err) => {
      clearTimeout(timeout);
      proc = null;
      sidecarUrl = null;
      appendError({ source: 'sidecar', message: `sidecar spawn failed: ${err.message}` });
      reject(err);
    });

    proc!.on('exit', (code) => {
      console.log(`[sidecar] exited with code ${code}`);
      const wasStopping = stopping;
      proc = null;
      sidecarUrl = null;
      if (code !== 0 && code !== null && !wasStopping) {
        appendError({ source: 'sidecar', message: `sidecar exited with code ${code}` });
        clearTimeout(timeout);
        reject(new Error(`sidecar exited with code ${code}`));
      }
    });
  });
}

export function stopSidecar(): void {
  if (!proc) return;
  stopping = true;
  // Kill the whole descendant tree — PyInstaller's bootloader (prod) and the
  // cmd/uv chain (dev) both spawn the Python interpreter as a separate child
  // that survives a plain proc.kill() on Windows. See processTree.ts.
  killTree(proc);
  proc = null;
  sidecarUrl = null;
}
