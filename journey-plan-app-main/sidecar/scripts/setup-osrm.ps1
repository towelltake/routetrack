# setup-osrm.ps1 — one-time OSRM bootstrap for the Journey Plan App.
#
# Source switched 2026-05-18 (late) from conda-forge to the official OSRM
# project + Intel oneTBB releases. Original premise was wrong: conda-forge
# does NOT ship osrm-backend for Windows. The project itself ships compiled
# Windows .exes inside the node_osrm release tarball; tbb12.dll has to come
# separately from Intel oneTBB.
#
# Downloads:
#   1. OSRM v26.5.0 Windows binaries from github.com/Project-OSRM/osrm-backend
#      (osrm-routed.exe + osrm-extract.exe + osrm-partition.exe +
#      osrm-customize.exe inside `node_osrm-...-win32-x64-Release.tar.gz`).
#   2. Intel oneTBB v2023.0.0 Windows release from github.com/uxlfoundation/oneTBB
#      (tbb12.dll + tbbmalloc.dll — runtime deps for the OSRM binaries).
#   3. car.lua + profiles/lib/*.lua from the Project-OSRM source at the same tag.
#   4. Oman OSM extract (.osm.pbf) from Geofabrik.
#
# Then preprocesses the OSM extract into the MLD graph format used by
# osrm-routed at runtime.
#
# Output layout (matches what app/src/main/osrm.ts expects):
#   sidecar/osrm/bin/osrm-routed.exe   (+ extract/partition/customize + tbb DLLs)
#   sidecar/osrm/profiles/car.lua
#   sidecar/osrm/profiles/lib/*.lua
#   sidecar/osrm/graph/oman.osrm       (+ ~12 sidecar files from preprocessing)
#
# Run from the repo root:  pnpm osrm:setup
# Re-run safely. Pass -Force to redo everything from scratch.

[CmdletBinding()]
param(
  [switch]$Force,
  [string]$OsrmVersion = "v26.5.0",
  [string]$OsrmAssetName = "node_osrm-v26.5.0-8-win32-x64-Release.tar.gz",
  [string]$TbbVersion  = "v2023.0.0",
  # Geofabrik doesn't publish an Oman-only extract — Oman is bundled into the
  # gcc-states pack (Oman + UAE + KSA + Bahrain + Qatar + Kuwait, ~240 MB
  # compressed). We use the full pack so cross-border customers (UAE, KSA)
  # also resolve. The graph is named gcc.osrm to match its actual contents.
  [string]$OmanPbfUrl  = "https://download.geofabrik.de/asia/gcc-states-latest.osm.pbf"
)

$ErrorActionPreference = "Stop"

$repoRoot   = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$osrmRoot   = Join-Path $repoRoot "sidecar\osrm"

# Use Windows' built-in BSD tar from System32 to avoid PATH pollution from
# Git Bash / MSYS GNU tar, which mis-parses `C:\path` as a remote
# `host:path` and fails with "Cannot connect to C: resolve failed".
$tarExe = Join-Path $env:WINDIR "System32\tar.exe"
if (-not (Test-Path $tarExe)) { throw "Windows tar.exe not found at $tarExe (Windows 10 1803+ required)" }
$binDir     = Join-Path $osrmRoot "bin"
$profileDir = Join-Path $osrmRoot "profiles"
$profileLibDir = Join-Path $profileDir "lib"
$graphDir   = Join-Path $osrmRoot "graph"
$tmpDir     = Join-Path $osrmRoot ".tmp"

New-Item -ItemType Directory -Force -Path $binDir, $profileDir, $profileLibDir, $graphDir, $tmpDir | Out-Null

function Write-Step($msg) {
  Write-Host "==> $msg" -ForegroundColor Cyan
}

# ---- 1. OSRM binaries from Project-OSRM GitHub release -------------------
$osrmBinary = Join-Path $binDir "osrm-routed.exe"
if ((Test-Path $osrmBinary) -and -not $Force) {
  Write-Step "OSRM binaries already present at $binDir (use -Force to re-download)"
} else {
  Write-Step "Downloading OSRM $OsrmVersion Windows binaries from Project-OSRM/osrm-backend..."
  $osrmUrl = "https://github.com/Project-OSRM/osrm-backend/releases/download/$OsrmVersion/$OsrmAssetName"
  $osrmTar = Join-Path $tmpDir $OsrmAssetName
  Invoke-WebRequest -Uri $osrmUrl -OutFile $osrmTar -UseBasicParsing
  Write-Step ("Downloaded {0:N1} MB" -f ((Get-Item $osrmTar).Length / 1MB))

  $extractDir = Join-Path $tmpDir "osrm-extract"
  if (Test-Path $extractDir) { Remove-Item -Recurse -Force $extractDir }
  New-Item -ItemType Directory -Force -Path $extractDir | Out-Null

  Write-Step "Extracting OSRM binaries..."
  & $tarExe -xzf $osrmTar -C $extractDir
  if ($LASTEXITCODE -ne 0) { throw "tar extract failed for $osrmTar" }

  # The Project-OSRM tarball lays out binaries under `binding_napi_v8/`.
  $srcDir = Join-Path $extractDir "binding_napi_v8"
  if (-not (Test-Path $srcDir)) { throw "expected $srcDir from OSRM tarball; layout may have changed" }

  Copy-Item -Path (Join-Path $srcDir "osrm-*.exe") -Destination $binDir -Force
  # node_osrm.node is the Node.js binding — not needed for the standalone
  # osrm-routed path. Skip it to keep the bundle smaller.
  Write-Step "OSRM binaries installed to $binDir"
}

# ---- 2. Intel oneTBB runtime DLLs ---------------------------------------
$tbbDll = Join-Path $binDir "tbb12.dll"
if ((Test-Path $tbbDll) -and -not $Force) {
  Write-Step "oneTBB DLLs already present (use -Force to re-download)"
} else {
  Write-Step "Downloading Intel oneTBB $TbbVersion Windows release..."
  $tbbAsset = "oneapi-tbb-$($TbbVersion.TrimStart('v'))-win.zip"
  $tbbUrl   = "https://github.com/uxlfoundation/oneTBB/releases/download/$TbbVersion/$tbbAsset"
  $tbbZip   = Join-Path $tmpDir $tbbAsset
  Invoke-WebRequest -Uri $tbbUrl -OutFile $tbbZip -UseBasicParsing
  Write-Step ("Downloaded {0:N1} MB" -f ((Get-Item $tbbZip).Length / 1MB))

  $tbbExtractDir = Join-Path $tmpDir "tbb-extract"
  if (Test-Path $tbbExtractDir) { Remove-Item -Recurse -Force $tbbExtractDir }
  Expand-Archive -Path $tbbZip -DestinationPath $tbbExtractDir -Force

  # OSRM's Windows .exes were built against vc14 (Visual Studio 2015+ ABI).
  # The redist for the matching ABI lives under redist/intel64/vc14/.
  $tbbRedist = Join-Path $tbbExtractDir "oneapi-tbb-$($TbbVersion.TrimStart('v'))\redist\intel64\vc14"
  if (-not (Test-Path $tbbRedist)) { throw "expected $tbbRedist from oneTBB zip; layout may have changed" }

  # Copy tbb12.dll + tbbmalloc.dll (OSRM links both; the malloc proxy is
  # optional and we skip it to keep the bundle small).
  Copy-Item -Path (Join-Path $tbbRedist "tbb12.dll") -Destination $binDir -Force
  Copy-Item -Path (Join-Path $tbbRedist "tbbmalloc.dll") -Destination $binDir -Force
  Write-Step "oneTBB DLLs installed to $binDir"
}

# ---- 3. car.lua profile + profiles/lib from osrm-backend source ----------
$carProfile = Join-Path $profileDir "car.lua"
if ((Test-Path $carProfile) -and -not $Force) {
  Write-Step "car.lua + lib profiles already present (use -Force to re-download)"
} else {
  Write-Step "Downloading car.lua + profiles/lib from osrm-backend@$OsrmVersion..."
  $rawBase = "https://raw.githubusercontent.com/Project-OSRM/osrm-backend/$OsrmVersion"

  Invoke-WebRequest -Uri "$rawBase/profiles/car.lua" -OutFile $carProfile -UseBasicParsing

  # car.lua require()'s these helpers from profiles/lib. List pinned at v26.5.0
  # — bump alongside $OsrmVersion if the upstream profile layout changes.
  $libFiles = @(
    "access.lua", "destination.lua", "guidance.lua", "maxspeed.lua",
    "measure.lua", "obstacles.lua", "relations.lua", "sequence.lua",
    "set.lua", "tags.lua", "traffic_signal.lua", "utils.lua", "way_handlers.lua"
  )
  foreach ($f in $libFiles) {
    Invoke-WebRequest -Uri "$rawBase/profiles/lib/$f" -OutFile (Join-Path $profileLibDir $f) -UseBasicParsing
  }
  Write-Step "car.lua + $($libFiles.Count) lib files installed to $profileDir"
}

# ---- 4. GCC-states OSM extract (includes Oman) ---------------------------
$srcPbf = Join-Path $tmpDir "gcc-states-latest.osm.pbf"
# Sanity gate: if a previous run left a tiny redirect-stub file (e.g. from
# Geofabrik returning a 302 to the index page when the URL was wrong), force
# a re-download. Real PBF is >50 MB.
if ((Test-Path $srcPbf) -and ((Get-Item $srcPbf).Length -lt (50 * 1MB))) {
  Remove-Item $srcPbf -Force
}
if ((Test-Path $srcPbf) -and -not $Force) {
  Write-Step "GCC-states OSM extract already downloaded ($srcPbf)"
} else {
  Write-Step "Downloading GCC-states OSM extract from Geofabrik..."
  Invoke-WebRequest -Uri $OmanPbfUrl -OutFile $srcPbf -UseBasicParsing
  $sizeMb = (Get-Item $srcPbf).Length / 1MB
  if ($sizeMb -lt 50) {
    throw "Geofabrik download too small ($([math]::Round($sizeMb,2)) MB) — URL likely returned a redirect/error page. Check $OmanPbfUrl."
  }
  Write-Step ("Downloaded {0:N1} MB" -f $sizeMb)
}

# ---- 5. Preprocess: extract → partition → customize ----------------------
$graphFile    = Join-Path $graphDir "gcc.osrm"
$extractExe   = Join-Path $binDir "osrm-extract.exe"
$partitionExe = Join-Path $binDir "osrm-partition.exe"
$customizeExe = Join-Path $binDir "osrm-customize.exe"

if ((Test-Path $graphFile) -and -not $Force) {
  Write-Step "Graph already exists at $graphFile (use -Force to rebuild)"
} else {
  $pbfInGraph = Join-Path $graphDir "gcc-states-latest.osm.pbf"
  Copy-Item -Path $srcPbf -Destination $pbfInGraph -Force

  Write-Step "osrm-extract (car profile) — this is the slow step (~3-6 min for GCC)..."
  & $extractExe --profile $carProfile $pbfInGraph
  if ($LASTEXITCODE -ne 0) { throw "osrm-extract failed (exit $LASTEXITCODE)" }

  $osrmFile = Join-Path $graphDir "gcc-states-latest.osrm"
  Write-Step "osrm-partition (MLD)..."
  & $partitionExe $osrmFile
  if ($LASTEXITCODE -ne 0) { throw "osrm-partition failed (exit $LASTEXITCODE)" }

  Write-Step "osrm-customize (MLD)..."
  & $customizeExe $osrmFile
  if ($LASTEXITCODE -ne 0) { throw "osrm-customize failed (exit $LASTEXITCODE)" }

  # Rename gcc-states-latest.osrm* → gcc.osrm* so the runtime path is stable
  # regardless of Geofabrik file naming.
  Write-Step "Renaming graph files to gcc.osrm.* ..."
  Get-ChildItem -Path $graphDir -Filter "gcc-states-latest.osrm*" | ForEach-Object {
    $newName = $_.Name -replace "^gcc-states-latest", "gcc"
    Move-Item -Path $_.FullName -Destination (Join-Path $graphDir $newName) -Force
  }

  Remove-Item -Path $pbfInGraph -Force -ErrorAction SilentlyContinue
}

Write-Host ""
Write-Host "OSRM setup complete." -ForegroundColor Green
Write-Host "  Binaries: $binDir"
Write-Host "  Profile:  $carProfile"
Write-Host "  Graph:    $graphFile"
Write-Host ""
Write-Host "Sanity check: start osrm-routed manually with"
Write-Host "  & '$osrmBinary' --algorithm mld '$graphFile'"
Write-Host "then in another shell: curl 'http://127.0.0.1:5000/nearest/v1/driving/58.5,23.5'"
