import { spawnSync, type ChildProcessWithoutNullStreams } from 'node:child_process';

// Force-kill a child process and every descendant, synchronously.
//
// Why: on Windows `proc.kill()` calls TerminateProcess on the immediate
// handle and does NOT propagate to children. PyInstaller's onefile
// `sidecar.exe` is a bootloader that re-spawns the real Python interpreter
// as a separate child; in dev `cmd /c uv run python -m sidecar.main` is the
// same shape (cmd.exe → uv → python). Terminating the parent leaves the
// Python child as an orphan that keeps holding the random sidecar port and
// — more importantly for the next launch — keeps a handle on the OSRM port
// neighbourhood that breaks `osrm-routed` startup.
//
// `taskkill /T /F /PID <pid>` (built into Windows) walks the descendant
// tree and force-terminates every process. We invoke it via spawnSync so
// `before-quit` blocks until the tree is reaped before closeDb() runs.
export function killTree(proc: ChildProcessWithoutNullStreams | null): void {
  if (!proc || proc.pid === undefined) return;
  if (process.platform === 'win32') {
    try {
      spawnSync('taskkill', ['/T', '/F', '/PID', String(proc.pid)], {
        windowsHide: true,
        stdio: 'ignore',
      });
    } catch {
      // fall through to proc.kill()
    }
  }
  try {
    proc.kill();
  } catch {
    // ignore — process may already be gone
  }
}
