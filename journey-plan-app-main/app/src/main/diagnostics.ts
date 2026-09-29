import { app } from 'electron';
import {
  appendFileSync,
  existsSync,
  mkdirSync,
  readFileSync,
  renameSync,
  statSync,
  truncateSync,
  writeFileSync,
} from 'node:fs';
import { dirname, join } from 'node:path';
import type { DiagnosticEntry } from '@journey/shared';

const MAX_BYTES = 1_000_000;
const ROTATE_SUFFIX = '.1';

// Retry strategy for the primary error.log when a transient writer (Windows
// Defender first-launch scan, antivirus quarantine, brief permission flip)
// holds an exclusive lock on the file. The 2026-05-23 forensic session
// confirmed the post-DB-backup "integrity ok" line was silently dropping on
// every installer launch — the queue + secondary-path fallback below ensures
// the entry lands eventually instead of disappearing to stderr (which is a
// black hole in a non-console Electron build).
const RETRY_BACKOFF_MS = [200, 500, 1000, 3000];
const MAX_QUEUE_LEN = 50;

let logPathOverride: string | null = null;

interface PendingEntry {
  serialized: string;
  attempts: number;
}

const pendingQueue: PendingEntry[] = [];
let drainTimer: NodeJS.Timeout | null = null;

export function configureDiagnostics(opts: { logPath?: string }): void {
  if (opts.logPath !== undefined) logPathOverride = opts.logPath;
}

function logPath(): string {
  if (logPathOverride) return logPathOverride;
  return join(app.getPath('userData'), 'error.log');
}

function fallbackLogPath(): string {
  // app.getPath('logs') resolves to %APPDATA%\<appName>\logs on Windows —
  // a different parent than userData/error.log, so a Defender lock that
  // targets the primary file by path doesn't necessarily affect this one.
  return join(app.getPath('logs'), 'error-fallback.log');
}

function rotateIfNeeded(path: string): void {
  if (!existsSync(path)) return;
  let size: number;
  try {
    size = statSync(path).size;
  } catch {
    return;
  }
  if (size < MAX_BYTES) return;
  const rotated = path + ROTATE_SUFFIX;
  try {
    if (existsSync(rotated)) truncateSync(rotated);
    renameSync(path, rotated);
  } catch {
    // If rotation fails, fall back to truncating in place so we never grow
    // without bound. This must not throw either: the rotation failure mode we
    // actually see is Defender holding the file, where truncate fails the same
    // way — and an exception here escapes appendError, which is called from
    // safeHandle's catch and from the uncaughtException handler.
    try {
      truncateSync(path);
    } catch {
      // Leave the file oversized; the caller's write will fail and fall
      // through to the secondary log path.
    }
  }
}

function tryWritePrimary(serialized: string): boolean {
  const path = logPath();
  rotateIfNeeded(path);
  try {
    appendFileSync(path, serialized, 'utf-8');
    return true;
  } catch {
    return false;
  }
}

function writeToFallback(serialized: string, primaryReason: string): void {
  // Last-resort sink. Use a separate parent dir so a path-targeted lock on
  // error.log doesn't necessarily affect this one. If even this throws, we
  // give up to stderr (status quo before this fix).
  const fp = fallbackLogPath();
  const annotated =
    serialized.trimEnd() +
    `  /* fallback_reason: ${primaryReason.replace(/[\r\n]+/g, ' ')} */\n`;
  try {
    mkdirSync(dirname(fp), { recursive: true });
    appendFileSync(fp, annotated, 'utf-8');
  } catch (err) {
    process.stderr.write(
      `[diagnostics] primary+fallback both failed (${String(err)}): ${serialized}`,
    );
  }
}

function scheduleDrain(): void {
  if (drainTimer !== null) return;
  drainTimer = setTimeout(drainOnce, RETRY_BACKOFF_MS[0]!);
}

function drainOnce(): void {
  drainTimer = null;
  const head = pendingQueue[0];
  if (!head) return;
  if (tryWritePrimary(head.serialized)) {
    pendingQueue.shift();
    if (pendingQueue.length > 0) scheduleDrain();
    return;
  }
  head.attempts += 1;
  if (head.attempts >= RETRY_BACKOFF_MS.length) {
    writeToFallback(head.serialized, `primary locked after ${head.attempts} retries`);
    pendingQueue.shift();
    if (pendingQueue.length > 0) scheduleDrain();
    return;
  }
  const delay = RETRY_BACKOFF_MS[head.attempts]!;
  drainTimer = setTimeout(drainOnce, delay);
}

export function appendError(entry: Omit<DiagnosticEntry, 'ts'>): void {
  const row: DiagnosticEntry = { ts: new Date().toISOString(), ...entry };
  const serialized = JSON.stringify(row) + '\n';
  if (tryWritePrimary(serialized)) return;
  // Primary path is locked or otherwise unwritable right now. Queue an async
  // retry with backoff. Cap the queue so a persistent lock doesn't balloon
  // memory — overflow drops the oldest to the fallback path immediately.
  if (pendingQueue.length >= MAX_QUEUE_LEN) {
    const dropped = pendingQueue.shift();
    if (dropped) writeToFallback(dropped.serialized, 'queue overflow');
  }
  pendingQueue.push({ serialized, attempts: 0 });
  scheduleDrain();
}

export function readErrors(limit = 500): DiagnosticEntry[] {
  const path = logPath();
  if (!existsSync(path)) return [];
  let text: string;
  try {
    text = readFileSync(path, 'utf-8');
  } catch {
    return [];
  }
  const lines = text.split(/\r?\n/).filter((l) => l.length > 0);
  const tail = lines.slice(-limit);
  const out: DiagnosticEntry[] = [];
  for (const line of tail) {
    try {
      out.push(JSON.parse(line) as DiagnosticEntry);
    } catch {
      out.push({ ts: '', source: 'main', message: line });
    }
  }
  return out;
}

export function clearErrors(): void {
  const path = logPath();
  try {
    writeFileSync(path, '', 'utf-8');
  } catch {
    // ignore
  }
}

// Drain any queued entries synchronously. Called from `before-quit` so we
// don't lose in-flight diagnostics when the process exits before the async
// retry timer fires. Each entry tries the primary path once, then writes to
// the fallback. The drain timer is cleared regardless.
export function flushPendingDiagnostics(): void {
  if (drainTimer !== null) {
    clearTimeout(drainTimer);
    drainTimer = null;
  }
  while (pendingQueue.length > 0) {
    const head = pendingQueue.shift()!;
    if (!tryWritePrimary(head.serialized)) {
      writeToFallback(head.serialized, 'flushed on quit');
    }
  }
}

export function installMainProcessHandlers(): void {
  process.on('uncaughtException', (err) => {
    appendError({
      source: 'main',
      message: err.message ?? String(err),
      stack: err.stack,
    });
  });
  process.on('unhandledRejection', (reason) => {
    const err = reason instanceof Error ? reason : new Error(String(reason));
    appendError({
      source: 'main',
      message: err.message,
      stack: err.stack,
    });
  });
}
