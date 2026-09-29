import { spawn, type ChildProcessWithoutNullStreams } from 'node:child_process';
import { copyFileSync, existsSync } from 'node:fs';
import { basename, dirname, join } from 'node:path';
import { app } from 'electron';
import { appendError } from './diagnostics';
import { killTree } from './processTree';

// OSRM-routed is bundled as a second sidecar process. It handles ALL solver
// matrix calls AND all map polylines (Google Directions removed 2026-05-22) —
// the only remaining external network call this app makes is to OSM tile
// servers for map tiles. Everything else is local.
//
// Graph files (gcc.osrm + ~12 sidecar files) live under resources/osrm/ in
// prod and under sidecar/osrm/ in dev. If the graph is missing the OSRM
// spawner logs a clear error and /matrix/batch returns 503 — there is no
// Google fallback (removed 2026-05-18 after the $4k cost incident).

let proc: ChildProcessWithoutNullStreams | null = null;
let osrmUrl: string | null = null;
// Tracks the most recent reason OSRM is in a bad state, so the user-visible
// `osrm:health` badge can show WHY plans will fail (binary missing vs graph
// missing vs DLL missing vs crashed-post-boot). Cleared on successful start.
let lastFailureReason: string | null = null;
// Set by stopOsrm() so the 'exit' handler can tell a deliberate teardown from a
// crash — without it every normal quit writes a bogus "osrm-routed exited with
// code 1" into the diagnostics log and buries real failures.
let stopping = false;

// Packaged installer uses 5050 (matches CLAUDE.md / installer docs). Dev runs
// on 5051 so a developer session can coexist with the installed app — without
// this, both bind 5050 and the second-launched one fails to start OSRM. The
// Python sidecar always picks up the right URL via the OSRM_BASE_URL env var
// that index.ts sets from getOsrmUrl() right before startSidecar().
const OSRM_PORT = app.isPackaged ? 5050 : 5051;
const STARTUP_TIMEOUT_MS = 30_000;
const HANDSHAKE_REGEX = /running and waiting for requests/i;

export function getOsrmUrl(): string | null {
  return osrmUrl;
}

export function getOsrmLastFailureReason(): string | null {
  return lastFailureReason;
}

// The OSRM graph is a basename — `gcc.osrm` — with ~25 sidecar files
// (`gcc.osrm.cells`, `gcc.osrm.partition`, …). There is NO file literally
// named `gcc.osrm`, so `existsSync(graph)` would always return false. Check
// a known-required sidecar file instead (`.cells` is always present for an
// MLD-preprocessed graph), and pass the basename to osrm-routed unchanged.
const GRAPH_PROBE_SUFFIX = '.cells';

function resolvePaths(): { binary: string; graph: string; graphProbe: string } | null {
  const isDev = !app.isPackaged;
  const isWin = process.platform === 'win32';
  const exe = isWin ? 'osrm-routed.exe' : 'osrm-routed';
  // In dev `app.getAppPath()` resolves to the app/ workspace, so go up one
  // level to the repo root and then into sidecar/. In prod everything sits
  // under resourcesPath. Don't use process.cwd() — electron-vite spawns the
  // main process with a cwd that depends on how the user launched pnpm.
  const root = isDev
    ? join(app.getAppPath(), '..', 'sidecar', 'osrm')
    : join(process.resourcesPath, 'osrm');
  const graph = join(root, 'graph', 'gcc.osrm');
  return {
    binary: join(root, 'bin', exe),
    graph,
    graphProbe: graph + GRAPH_PROBE_SUFFIX,
  };
}

export async function startOsrm(): Promise<void> {
  if (proc) return;
  stopping = false;
  const paths = resolvePaths();
  if (!paths) return;

  if (!existsSync(paths.binary)) {
    const msg = `OSRM binary missing at ${paths.binary} — matrix calls will fail until OSRM is set up. Run 'pnpm osrm:setup'.`;
    console.error(`[osrm] ${msg}`);
    appendError({ source: 'main', message: msg });
    lastFailureReason = msg;
    return;
  }
  if (!existsSync(paths.graphProbe)) {
    const msg = `OSRM graph missing (probed ${paths.graphProbe}) — run 'pnpm osrm:setup' to download the GCC-states OSM extract and preprocess it.`;
    console.error(`[osrm] ${msg}`);
    appendError({ source: 'main', message: msg });
    lastFailureReason = msg;
    return;
  }
  // osrm-routed.exe is linked against Intel oneTBB. Without tbb12.dll +
  // tbbmalloc.dll alongside the .exe, the process exits immediately with no
  // useful stderr. We saw a fresh 2026-05-18 installer ship without these
  // (suspected Defender / NSIS interference with unsigned DLLs at
  // resources/osrm/bin/). Self-heal: keep a backup copy inside
  // app.asar.unpacked/runtime-deps/osrm/ (see electron-builder.yml files +
  // asarUnpack) and restore from it when the primary location is empty.
  const binDir = dirname(paths.binary);
  for (const dll of ['tbb12.dll', 'tbbmalloc.dll']) {
    const target = join(binDir, dll);
    if (existsSync(target)) continue;
    // app.asar.unpacked lives next to app.asar — `app.getAppPath()` returns the
    // asar path in prod, so its parent is the resources/ dir that holds both.
    const unpackedBackup = join(
      app.getAppPath().replace(/app\.asar$/, 'app.asar.unpacked'),
      'runtime-deps',
      'osrm',
      dll,
    );
    if (existsSync(unpackedBackup)) {
      try {
        copyFileSync(unpackedBackup, target);
        console.log(`[osrm] restored ${dll} from asar-unpacked backup`);
        appendError({
          source: 'main',
          message: `Recovered missing OSRM dependency ${dll} from asar-unpacked backup. Install likely got tampered with at extract time.`,
        });
        continue;
      } catch (err) {
        const reason = err instanceof Error ? err.message : String(err);
        appendError({
          source: 'main',
          message: `Failed to restore ${dll} from backup: ${reason}`,
        });
      }
    }
    const msg =
      `OSRM dependency ${dll} missing next to osrm-routed.exe (looked in ${binDir}) ` +
      `and no asar-unpacked backup found at ${unpackedBackup}. osrm-routed will not start.`;
    console.error(`[osrm] ${msg}`);
    appendError({ source: 'main', message: msg });
    lastFailureReason = msg;
    return;
  }

  // Pass the graph as a basename (`gcc.osrm`) and set cwd to the graph dir.
  // osrm-routed uses Boost.program_options which mis-parses positional paths
  // containing spaces, even when Node.js correctly quotes the argv —
  // verified 2026-05-18: same .exe + same DLLs against the dev path
  // (no spaces) works, against the installed path under
  // `C:\…\Programs\Journey Plan App\…` fails with
  // `argument for option '--base' is invalid`. The basename avoids any
  // path-string-with-spaces in the cmdline. DLL resolution still works
  // because Windows' DLL search order looks in the .exe's own directory
  // FIRST regardless of cwd (the bin dir holds the TBB DLLs).
  // --max-table-size lifts OSRM's per-side cap on /table queries. Default is
  // 100; we routinely send self-matrix calls of 200+ same-region customers
  // (Muscat has 200+ outlets in the Towell dataset), so the default rejects
  // the request with HTTP 400 "TooBig". 5000 is plenty of headroom — OSRM
  // imposes the cap as DOS protection for public instances, which doesn't
  // apply when we're the only client on localhost.
  const args = [
    '--algorithm', 'mld',
    '--ip', '127.0.0.1',
    '--port', String(OSRM_PORT),
    '--max-table-size', '5000',
    basename(paths.graph),
  ];
  const graphDir = dirname(paths.graph);
  console.log(`[osrm] spawning ${paths.binary} ${args.join(' ')} (cwd=${graphDir})`);

  proc = spawn(paths.binary, args, {
    cwd: graphDir,
    env: { ...process.env },
    shell: false,
  });

  return new Promise<void>((resolve, reject) => {
    const timeout = setTimeout(() => {
      // Kill the child on timeout. Leaving it alive meant a late handshake
      // could flip osrmUrl/lastFailureReason long after the sidecar had
      // already been started against the default base URL, leaving
      // osrm:health reporting unreachable with no reason attached.
      stopOsrm();
      lastFailureReason = 'OSRM startup timed out';
      reject(new Error('OSRM startup timed out'));
    }, STARTUP_TIMEOUT_MS);

    const onReady = () => {
      if (osrmUrl) return;
      osrmUrl = `http://127.0.0.1:${OSRM_PORT}`;
      lastFailureReason = null;
      clearTimeout(timeout);
      resolve();
    };

    // osrm-routed prints the readiness line to stdout. Some builds print to
    // stderr — watch both.
    const watch = (stream: 'stdout' | 'stderr') => (chunk: Buffer) => {
      const text = chunk.toString();
      process.stdout.write(`[osrm ${stream}] ${text}`);
      if (HANDSHAKE_REGEX.test(text)) onReady();
    };
    proc!.stdout.on('data', watch('stdout'));
    proc!.stderr.on('data', (chunk: Buffer) => {
      watch('stderr')(chunk);
      const text = chunk.toString().trim();
      // OSRM logs everything to stderr at INFO level — only forward genuine
      // errors to the diagnostics log to avoid filling it with noise.
      if (/error/i.test(text)) {
        appendError({ source: 'main', message: `[osrm] ${text}` });
      }
    });

    // Without an 'error' listener a failed spawn raises an unhandled 'error'
    // event that takes down the main process.
    proc!.on('error', (err) => {
      clearTimeout(timeout);
      proc = null;
      osrmUrl = null;
      lastFailureReason = `osrm-routed spawn failed: ${err.message}`;
      appendError({ source: 'main', message: lastFailureReason });
      reject(err);
    });

    proc!.on('exit', (code) => {
      console.log(`[osrm] exited with code ${code}`);
      const wasReady = osrmUrl !== null;
      const wasStopping = stopping;
      proc = null;
      osrmUrl = null;
      if (wasStopping) return;
      // Distinguish pre-handshake failure (rejects the startup promise) from
      // post-handshake death (promise already resolved — we just record the
      // reason so `osrm:health` can surface it to the user). The osrm:health
      // badge consumer reads `getOsrmLastFailureReason()` and shows it in the
      // tooltip when the sidecar reports reachable=false.
      if (code !== 0 && code !== null) {
        const msg = wasReady
          ? `osrm-routed crashed after startup with code ${code}`
          : `osrm-routed exited with code ${code}`;
        lastFailureReason = msg;
        appendError({ source: 'main', message: msg });
        if (!wasReady) {
          clearTimeout(timeout);
          reject(new Error(msg));
        }
      } else if (wasReady) {
        // Clean exit AFTER handshake is unexpected — log it so the user can
        // see why plans suddenly started 503ing.
        lastFailureReason = 'osrm-routed exited cleanly after startup';
        appendError({ source: 'main', message: lastFailureReason });
      }
    });
  });
}

export function stopOsrm(): void {
  if (!proc) return;
  stopping = true;
  // Tree-kill in case osrm-routed ever spawns workers (current builds don't,
  // but it's the same Windows-TerminateProcess class of problem the sidecar
  // hits — kept symmetric so neither path can leak). See processTree.ts.
  killTree(proc);
  proc = null;
  osrmUrl = null;
}
