"""Phase 5 — bundles the sidecar into a single Windows executable.

Run via `pnpm sidecar:build` from the repo root. The output is copied into
`app/resources/sidecar/sidecar.exe` by electron-builder via the extraResources
entry in `app/electron-builder.yml`.
"""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path


def main() -> int:
    here = Path(__file__).resolve().parent
    project = here.parent
    return subprocess.call(
        [
            sys.executable,
            "-m",
            "PyInstaller",
            "--onefile",
            "--noconsole",
            "--name",
            "sidecar",
            "--distpath",
            str(project / "dist"),
            "--workpath",
            str(project / "build"),
            # ortools bundles native DLLs (ortools.dll, libprotobuf.dll, abseil_dll.dll)
            # that PyInstaller's static analysis misses. uvicorn lazy-imports its
            # loop and HTTP backends; certifi ships the CA bundle (cacert.pem)
            # that httpx needs to establish HTTPS — without it, every outbound
            # Google Maps call fails with FileNotFoundError inside ssl.py.
            "--collect-all",
            "ortools",
            "--collect-all",
            "uvicorn",
            "--collect-all",
            "certifi",
            "--collect-all",
            "httpx",
            "--collect-all",
            "httpcore",
            # anyio's async backend is loaded via importlib.import_module — static
            # analysis misses it. sniffio is a small detect-which-async-lib helper
            # used by httpcore. Both are required for any httpx call to succeed.
            "--collect-all",
            "anyio",
            "--collect-all",
            "sniffio",
            str(here / "main.py"),
        ]
    )


if __name__ == "__main__":
    raise SystemExit(main())
