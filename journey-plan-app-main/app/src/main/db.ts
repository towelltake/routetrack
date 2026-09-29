import { app } from 'electron';
import Database from 'better-sqlite3';
import { existsSync, mkdirSync, readFileSync, readdirSync, renameSync, rmSync, statSync } from 'node:fs';
import { join } from 'node:path';

let db: Database.Database | null = null;
let dbPathOverride: string | null = null;
let migrationsDirOverride: string | null = null;

export function configureDb(opts: { dbPath?: string; migrationsDir?: string }): void {
  // Test seam: lets tests run migrations against a temp DB without
  // booting Electron. Production code never calls this.
  if (opts.dbPath !== undefined) dbPathOverride = opts.dbPath;
  if (opts.migrationsDir !== undefined) migrationsDirOverride = opts.migrationsDir;
  if (db) {
    db.close();
    db = null;
  }
}

function resolveDbPath(): string {
  if (dbPathOverride) return dbPathOverride;
  return join(app.getPath('userData'), 'journey-plan.db');
}

function resolveMigrationsDir(): string {
  if (migrationsDirOverride) return migrationsDirOverride;
  return app.isPackaged
    ? join(process.resourcesPath, 'migrations')
    : join(__dirname, '../../src/main/migrations');
}

export function getDb(): Database.Database {
  if (!db) {
    db = new Database(resolveDbPath());
    db.pragma('journal_mode = WAL');
    db.pragma('foreign_keys = ON');
    // FULL flushes the WAL frame to disk before the commit returns. Slower than
    // NORMAL (the WAL-mode default) but eliminates the mid-checkpoint torn-page
    // class of corruption that can happen when the process is killed (Ctrl+C in
    // dev, taskkill, OS reboot) mid-write. We don't issue enough writes per
    // second for the perf delta to matter.
    db.pragma('synchronous = FULL');
  }
  return db;
}

/**
 * Close the SQLite handle cleanly. Checkpoints the WAL into the main DB file
 * first so the WAL/SHM don't carry uncommitted frames past process exit.
 *
 * Must be called from Electron `before-quit`. Without this, ungraceful exits
 * (dev HMR restart, Ctrl+C, taskkill, OS shutdown mid-write) can leave the
 * WAL inconsistent with the main file — the exact corruption pattern we hit
 * on 2026-05-21 (out-of-order rowids, invalid page numbers in customers and
 * visits trees).
 */
export function closeDb(): void {
  if (!db) return;
  try {
    db.pragma('wal_checkpoint(TRUNCATE)');
  } catch {
    // Best-effort. Even if checkpoint fails (e.g. another reader still has a
    // shared lock), close() below will still flush and release.
  }
  db.close();
  db = null;
}

/**
 * Verify the main DB is intact. Runs PRAGMA integrity_check (the cheap one,
 * stops at first error). Returns null on clean, or the first error line.
 * Called at startup so we can surface a corrupt DB to the user instead of
 * letting the next IPC handler crash with a malformed-image error.
 */
export function integrityCheckQuick(): string | null {
  const row = getDb()
    .prepare("PRAGMA quick_check(1)")
    .get() as { quick_check?: string; integrity_check?: string };
  const value = row.quick_check ?? row.integrity_check ?? '';
  return value === 'ok' ? null : value;
}

/**
 * Rotating local backup of the main DB. We keep up to KEEP files in
 * <userData>/db-backups; older ones are pruned by mtime. Called once at
 * startup (before any writes) so even if today's session corrupts the DB,
 * yesterday's snapshot survives.
 *
 * Uses better-sqlite3's online backup API rather than fs.copyFile so we get
 * a consistent point-in-time copy even if writers later append to the WAL.
 */
export async function backupDb(): Promise<string | null> {
  const src = resolveDbPath();
  if (!existsSync(src)) return null;
  const backupsDir = join(app.getPath('userData'), 'db-backups');
  mkdirSync(backupsDir, { recursive: true });

  const stamp = new Date().toISOString().replace(/[:.]/g, '-');
  const target = join(backupsDir, `journey-plan-${stamp}.db`);
  const tmp = `${target}.partial`;

  // Online backup via SQLite's backup API. Writes to <tmp>, then atomically
  // renames to <target> so partial files never look complete.
  await getDb().backup(tmp);
  renameSync(tmp, target);

  // Retain the most recent KEEP backups.
  const KEEP = 7;
  const files = readdirSync(backupsDir)
    .filter((f) => f.startsWith('journey-plan-') && f.endsWith('.db'))
    .map((f) => ({ name: f, mtime: statSync(join(backupsDir, f)).mtimeMs }))
    .sort((a, b) => b.mtime - a.mtime);
  for (const f of files.slice(KEEP)) {
    try {
      rmSync(join(backupsDir, f.name));
    } catch {
      // best-effort retention prune
    }
  }
  return target;
}

/**
 * Move a corrupted DB out of the way so a fresh one is created on next start.
 * Renames to journey-plan.corrupt-<stamp>.db in the same folder. Returns the
 * new path so the caller can surface it to the user.
 */
export function quarantineDb(): string {
  closeDb();
  const src = resolveDbPath();
  const stamp = new Date().toISOString().replace(/[:.]/g, '-');
  const dest = src.replace(/\.db$/, `.corrupt-${stamp}.db`);
  renameSync(src, dest);
  // WAL/SHM are tied to the old DB; remove so a clean one starts fresh.
  for (const suffix of ['-wal', '-shm']) {
    const p = src + suffix;
    if (existsSync(p)) {
      try {
        rmSync(p);
      } catch {
        // best-effort
      }
    }
  }
  return dest;
}

export async function runMigrations(): Promise<void> {
  const conn = getDb();

  conn.exec(`
    CREATE TABLE IF NOT EXISTS _migrations (
      filename TEXT PRIMARY KEY,
      applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
  `);

  const migrationsDir = resolveMigrationsDir();

  let files: string[];
  try {
    files = readdirSync(migrationsDir).filter((f) => f.endsWith('.sql')).sort();
  } catch {
    console.warn(`[db] no migrations directory at ${migrationsDir}`);
    return;
  }

  const applied = new Set(
    conn.prepare('SELECT filename FROM _migrations').all().map((r) => (r as { filename: string }).filename),
  );

  for (const file of files) {
    if (applied.has(file)) continue;
    const sql = readFileSync(join(migrationsDir, file), 'utf-8');
    console.log(`[db] applying migration ${file}`);
    const tx = conn.transaction(() => {
      conn.exec(sql);
      conn.prepare('INSERT INTO _migrations (filename) VALUES (?)').run(file);
    });
    tx();
  }
}
