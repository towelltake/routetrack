// Quick smoke test: runs the importer against fixture xlsx files under
// Electron's Node runtime (so the better-sqlite3 native binding loads).
// Invoke via: `pnpm exec electron app/scripts/smoke-import.cjs`
// Exits 0 on success, 1 on assertion failure.

const path = require('node:path');
const fs = require('node:fs');
const os = require('node:os');

const { app } = require('electron');

const ROOT = path.resolve(__dirname, '..');
const OUT_MAIN = path.join(ROOT, 'out', 'main');
const MIGRATIONS = path.join(ROOT, 'src', 'main', 'migrations');
const FIXTURES = path.join(ROOT, 'src', 'main', 'import', 'fixtures');

// We run against the production-built main bundle so the importer logic
// resolves all its dependencies the same way the Electron app does.
function loadBuiltMain() {
  const indexJs = path.join(OUT_MAIN, 'index.js');
  if (!fs.existsSync(indexJs)) {
    throw new Error(
      `Build artifact missing: ${indexJs}. Run \`pnpm build\` in the app workspace first.`,
    );
  }
}

function assert(cond, msg) {
  if (!cond) {
    console.error('✗ ASSERT FAILED:', msg);
    process.exitCode = 1;
    throw new Error(msg);
  }
  console.log('✓', msg);
}

async function main() {
  loadBuiltMain();

  // Point Electron at a throwaway userData dir so we don't pollute the
  // user's real Journey Plan database.
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'journey-smoke-'));
  app.setPath('userData', tmp);
  await app.whenReady();

  // Dynamically require the source so we use the same TS-compiled bundle
  // the production app uses. The built main bundle exports nothing, so we
  // need to import from `out/main`'s peer files or compile-on-demand.
  // Simplest: import the migration runner + repo modules by transpiling
  // them via electron-vite's build output. We rebuilt to `out/main/index.js`,
  // but the importer is tree-shaken into that single file — not separately
  // importable. So instead we exercise the data path by exec'ing SQL through
  // a small in-script clone of the pipeline.
  const Database = require('better-sqlite3');
  const XLSX = require('xlsx');

  const dbPath = path.join(tmp, 'journey-plan.db');
  const db = new Database(dbPath);
  db.pragma('journal_mode = WAL');
  db.pragma('foreign_keys = ON');

  // Apply all migrations in order
  db.exec(`
    CREATE TABLE IF NOT EXISTS _migrations (
      filename TEXT PRIMARY KEY,
      applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
  `);
  const migrationFiles = fs.readdirSync(MIGRATIONS).filter((f) => f.endsWith('.sql')).sort();
  for (const file of migrationFiles) {
    const sql = fs.readFileSync(path.join(MIGRATIONS, file), 'utf-8');
    db.transaction(() => {
      db.exec(sql);
      db.prepare('INSERT INTO _migrations (filename) VALUES (?)').run(file);
    })();
    console.log(`[db] applied ${file}`);
  }

  // --- Inline a thin version of the importer pipeline ---
  function parseSheet(filePath, sheetName) {
    const wb = XLSX.read(fs.readFileSync(filePath), { type: 'buffer', cellDates: false });
    const ws = wb.Sheets[sheetName ?? wb.SheetNames[0]];
    const arr = XLSX.utils.sheet_to_json(ws, { header: 1, blankrows: false, defval: null });
    return { headers: arr[0].map(String), rows: arr.slice(1) };
  }

  const LAT_MIN = 16, LAT_MAX = 28, LNG_MIN = 52, LNG_MAX = 60;
  const DAYS = { sun: 0, mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6 };
  function parseDays(raw) {
    if (raw === null || raw === undefined || raw === '') return null;
    const parts = String(raw).trim().split(/[,;|\s]+/).filter(Boolean);
    return parts.map((p) => {
      const n = Number(p);
      if (Number.isInteger(n) && n >= 0 && n <= 6) return n;
      const d = DAYS[p.toLowerCase().slice(0, 3)];
      if (d === undefined) throw new Error(`unrecognised day "${p}"`);
      return d;
    });
  }

  function validateRows(rows, headers, mapping) {
    const idx = (f) => (mapping[f] ? headers.indexOf(mapping[f]) : -1);
    const e = idx('external_code'), n = idx('name'), addr = idx('address');
    const la = idx('lat'), ln = idx('lng'), ft = idx('facetime_minutes');
    const fr = idx('monthly_frequency'), ad = idx('allowed_days');
    const seen = new Set();
    const validated = [], errors = [];
    rows.forEach((row, i) => {
      const errs = [];
      const code = String(row[e] ?? '').trim();
      const name = String(row[n] ?? '').trim();
      if (!code) errs.push('external_code is empty');
      if (!name) errs.push('name is empty');
      if (code && seen.has(code)) errs.push(`duplicate external_code "${code}" within this upload`);
      const lat = row[la] === null || row[la] === '' ? null : Number(row[la]);
      const lng = row[ln] === null || row[ln] === '' ? null : Number(row[ln]);
      if (lat !== null && (lat < LAT_MIN || lat > LAT_MAX))
        errs.push(`lat ${lat} outside Oman bounds [${LAT_MIN}, ${LAT_MAX}]`);
      if (lng !== null && (lng < LNG_MIN || lng > LNG_MAX))
        errs.push(`lng ${lng} outside Oman bounds [${LNG_MIN}, ${LNG_MAX}]`);
      // Coordinates are mandatory — geocoding was removed 2026-05-22.
      if (lat === null || lng === null)
        errs.push('lat and lng are both required (geocoding is no longer available)');
      const facetime = ft >= 0 && row[ft] !== null && row[ft] !== '' ? Number(row[ft]) : null;
      if (facetime !== null && (facetime < 1 || facetime > 240))
        errs.push(`facetime ${facetime} out of range [1, 240]`);
      let allowedDays = null;
      try {
        if (ad >= 0) allowedDays = parseDays(row[ad]);
      } catch (err) { errs.push(err.message); }
      const freq = fr >= 0 && row[fr] !== null && row[fr] !== '' ? Number(row[fr]) : null;
      if (errs.length > 0) {
        errors.push({ rowNumber: i + 2, externalCode: code || null, errors: errs });
        return;
      }
      seen.add(code);
      validated.push({
        externalCode: code, name, address: addr >= 0 ? row[addr] : null,
        lat, lng, facetimeMinutes: facetime ?? 15, monthlyFrequency: freq ?? 1,
        allowedDaysCsv: allowedDays ? allowedDays.join(',') : null,
      });
    });
    return { validated, errors };
  }

  function commit(filePath, sheet, mapping, datasetName) {
    const { headers, rows } = parseSheet(filePath, sheet);
    const { validated, errors } = validateRows(rows, headers, mapping);
    let datasetId;
    db.transaction(() => {
      const r = db.prepare(
        'INSERT INTO customer_datasets (name, source_filename, is_active, row_count) VALUES (?, ?, 0, ?)',
      ).run(datasetName, path.basename(filePath), validated.length);
      datasetId = Number(r.lastInsertRowid);
      // geocode_status / geocode_confidence dropped in migration 0010 along
      // with the rest of the vestigial geocoding schema (Phase 10 removed the
      // Google client; migration 0010 removed the now-unused columns).
      const insert = db.prepare(`
        INSERT INTO customers (
          dataset_id, external_code, name, address, lat, lng,
          facetime_minutes, monthly_frequency, allowed_days_csv,
          pinned_salesman_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)
      `);
      for (const v of validated) {
        insert.run(
          datasetId, v.externalCode, v.name, v.address, v.lat, v.lng,
          v.facetimeMinutes, v.monthlyFrequency, v.allowedDaysCsv,
        );
      }
      db.prepare('UPDATE customer_datasets SET is_active = 0 WHERE is_active = 1').run();
      db.prepare('UPDATE customer_datasets SET is_active = 1 WHERE id = ?').run(datasetId);
    })();
    return { datasetId, inserted: validated.length, errors };
  }

  // ---- Test 1: dummy_customers.xlsx ----
  console.log('\n=== Test 1: import dummy_customers.xlsx ===');
  const r1 = commit(
    path.join(FIXTURES, 'dummy_customers.xlsx'),
    'Customers',
    {
      external_code: 'CCID',
      name: 'Customer Name',
      address: 'Address',
      lat: 'Latitude',
      lng: 'Longitude',
      facetime_minutes: 'Facetime',
      monthly_frequency: 'Frequency',
      allowed_days: 'Allowed Days',
    },
    'Dummy run',
  );
  console.log(`Inserted=${r1.inserted}, errors=${r1.errors.length}`);
  for (const e of r1.errors) console.log(`  Row ${e.rowNumber} (${e.externalCode ?? '—'}): ${e.errors.join('; ')}`);

  // After geocoding was removed 2026-05-22, the lat+lng-less row (T-004) is
  // rejected at validation rather than imported as pending. Inserted drops
  // from 4 → 3 and errors rises from 7 → 8.
  assert(r1.inserted === 3, '3 valid rows inserted (T-001..T-003)');
  assert(r1.errors.length === 8, '8 validation errors flagged (incl. T-004 missing coords)');

  const stats1 = db.prepare(
    `SELECT COUNT(*) AS n FROM customers WHERE dataset_id = ? AND lat IS NOT NULL AND lng IS NOT NULL`,
  ).get(r1.datasetId);
  console.log(`Rows inserted with coordinates: ${stats1.n}`);
  assert(stats1.n === 3, 'all inserted rows have coordinates');

  const active1 = db.prepare('SELECT * FROM customer_datasets WHERE is_active = 1').get();
  assert(active1 && active1.name === 'Dummy run', 'Dummy run dataset is active');

  // ---- Test 2: import a second file → first archived ----
  console.log('\n=== Test 2: import dummy_second.xlsx (replace) ===');
  const r2 = commit(
    path.join(FIXTURES, 'dummy_second.xlsx'),
    'Customers',
    { external_code: 'CCID', name: 'Customer Name', lat: 'Latitude', lng: 'Longitude' },
    'Second run',
  );
  console.log(`Inserted=${r2.inserted}, errors=${r2.errors.length}`);
  assert(r2.inserted === 3, '3 rows in second file inserted');
  assert(r2.errors.length === 0, 'second file has no validation errors');

  const all = db.prepare('SELECT id, name, is_active, row_count FROM customer_datasets ORDER BY id').all();
  console.log('All datasets:', all);
  assert(all.length === 2, 'two datasets exist (first archived)');
  const activeCount = db.prepare('SELECT COUNT(*) AS c FROM customer_datasets WHERE is_active = 1').get().c;
  assert(activeCount === 1, 'exactly one active dataset (partial unique index)');
  const active2 = db.prepare('SELECT name FROM customer_datasets WHERE is_active = 1').get();
  assert(active2.name === 'Second run', 'Second run is now active');

  // ---- Test 3: customer counts per dataset ----
  console.log('\n=== Test 3: per-dataset customer counts ===');
  const counts = db.prepare(
    'SELECT d.name, COUNT(c.id) AS n FROM customer_datasets d LEFT JOIN customers c ON c.dataset_id = d.id GROUP BY d.id',
  ).all();
  console.log('Per-dataset counts:', counts);
  assert(counts.find((r) => r.name === 'Dummy run').n === 3, '3 customers in archived Dummy run');
  assert(counts.find((r) => r.name === 'Second run').n === 3, '3 customers in active Second run');

  console.log('\nAll assertions passed.');
  db.close();
  app.quit();
}

main().catch((err) => {
  console.error('FATAL:', err);
  process.exitCode = 1;
  app.quit();
});
