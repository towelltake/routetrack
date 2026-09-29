import { useState } from 'react';
import type {
  ImportCommitResult,
  ImportFieldName,
  ImportMapping,
  ImportPreview,
} from '@journey/shared';

const FIELDS: { key: ImportFieldName; label: string; required: boolean }[] = [
  { key: 'external_code', label: 'External code (e.g. CCID) *', required: true },
  { key: 'name', label: 'Customer name *', required: true },
  { key: 'lat', label: 'Latitude *', required: true },
  { key: 'lng', label: 'Longitude *', required: true },
  { key: 'address', label: 'Address (optional)', required: false },
  { key: 'facetime_minutes', label: 'Facetime in minutes (optional)', required: false },
  { key: 'monthly_frequency', label: 'Monthly visit frequency (optional)', required: false },
  { key: 'allowed_days', label: 'Allowed days (optional)', required: false },
  { key: 'pinned_salesman_name', label: 'Pinned salesman name (optional)', required: false },
  { key: 'area', label: 'Area / Wilayat / Territory (optional)', required: false },
  { key: 'region', label: 'Region / Governorate / Parent area (optional)', required: false },
  { key: 'channel', label: 'Channel — MT, TT, or WS (optional)', required: false },
  { key: 'visit_window_start', label: 'Visit window start, HH:MM (optional)', required: false },
  { key: 'visit_window_end', label: 'Visit window end, HH:MM (optional)', required: false },
];

function autoSuggestMapping(headers: string[]): ImportMapping {
  const out: ImportMapping = {};
  const tests: Record<ImportFieldName, RegExp[]> = {
    external_code: [/^(ccid|customer ?code|external ?code|account ?id|account ?no|code)$/i],
    name: [/^(customer ?name|name|client|account name)$/i],
    address: [/^(address|location|street)$/i],
    lat: [/^(lat|latitude|y)$/i],
    lng: [/^(lng|lon|long|longitude|x)$/i],
    facetime_minutes: [
      /^(facetime|face ?time|visit ?duration|duration|time ?on ?site)( ?\(min\)| ?min(ute)?s?)?$/i,
    ],
    monthly_frequency: [/^(monthly ?frequency|frequency|visits ?per ?month|visit ?freq)$/i],
    allowed_days: [/^(allowed ?days|days|visit ?days)$/i],
    pinned_salesman_name: [/^((pinned ?)?sales ?man( ?name)?|rep)$/i],
    region: [/^(region|governorate|parent ?area|parent ?region|territory ?group)$/i],
    area: [/^(area|wilayat|wilaya|territory|zone)$/i],
    channel: [/^(channel|trade|trade ?channel|mt\/tt|mt ?or ?tt)$/i],
    visit_window_start: [
      /^(visit ?window ?start|window ?start|receiving ?(from|start)|open(s|ing)? ?(time|from)?)$/i,
    ],
    visit_window_end: [
      /^(visit ?window ?end|window ?end|receiving ?(to|until|end)|clos(es|ing)? ?(time|until)?)$/i,
    ],
  };
  // The patterns spell multi-word headers with an optional SPACE ("external
  // ?code"), so a snake_case header never matched — including every column in
  // our own journey-plan-template.xlsx, which made the user re-map 7 of 14
  // fields on every import. Normalise separators before testing.
  const normalise = (h: string) => h.trim().replace(/[_.-]+/g, ' ').replace(/\s+/g, ' ');
  for (const h of headers) {
    const probe = normalise(h);
    for (const f of Object.keys(tests) as ImportFieldName[]) {
      if (out[f]) continue;
      if (tests[f].some((rx) => rx.test(h) || rx.test(probe))) {
        out[f] = h;
        break;
      }
    }
  }
  return out;
}

export function ImportScreen() {
  const [preview, setPreview] = useState<ImportPreview | null>(null);
  const [mapping, setMapping] = useState<ImportMapping>({});
  const [datasetName, setDatasetName] = useState('');
  const [committing, setCommitting] = useState(false);
  const [result, setResult] = useState<ImportCommitResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  const pickFile = async () => {
    setError(null);
    setResult(null);
    try {
      const p = await window.api.openImportFile();
      if (!p) return;
      setPreview(p);
      setMapping(autoSuggestMapping(p.headers));
      setDatasetName(`${p.selectedSheet} – ${new Date().toLocaleDateString()}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    }
  };

  const changeSheet = async (sheet: string) => {
    if (!preview) return;
    const p = await window.api.reparseSheet(preview.filePath, sheet);
    setPreview(p);
    setMapping(autoSuggestMapping(p.headers));
  };

  const commit = async () => {
    if (!preview) return;
    if (!mapping.external_code || !mapping.name) {
      setError('You must map both external_code and name before importing.');
      return;
    }
    setCommitting(true);
    setError(null);
    try {
      const r = await window.api.commitImport({
        filePath: preview.filePath,
        sheet: preview.selectedSheet,
        mapping,
        datasetName,
      });
      setResult(r);
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setCommitting(false);
    }
  };

  return (
    <div className="screen">
      <header className="screen-header">
        <h2>Import customers</h2>
        <p className="muted">
          Each upload replaces the active customer set. The previous dataset is archived and can
          be restored from Settings. Every row must include latitude and longitude.
        </p>
      </header>

      {!preview && (
        <section className="card">
          <button onClick={pickFile}>Choose Excel or CSV…</button>
        </section>
      )}

      {preview && (
        <>
          <section className="card">
            <div className="row">
              <label>
                Source file
                <input type="text" value={preview.filePath} readOnly />
              </label>
              <label>
                Sheet
                <select value={preview.selectedSheet} onChange={(e) => void changeSheet(e.target.value)}>
                  {preview.sheets.map((s) => (
                    <option key={s} value={s}>
                      {s}
                    </option>
                  ))}
                </select>
              </label>
              <label>
                Dataset name
                <input type="text" value={datasetName} onChange={(e) => setDatasetName(e.target.value)} />
              </label>
            </div>
            <p className="muted small">
              Detected {preview.headers.length} columns, {preview.rowCount.toLocaleString()} data rows.
            </p>
          </section>

          <section className="card">
            <h3>Map columns</h3>
            <div className="mapping-grid">
              {FIELDS.map(({ key, label }) => (
                <label key={key}>
                  <span>{label}</span>
                  <select
                    value={mapping[key] ?? ''}
                    onChange={(e) =>
                      setMapping({
                        ...mapping,
                        [key]: e.target.value || undefined,
                      })
                    }
                  >
                    <option value="">— not mapped —</option>
                    {preview.headers.map((h) => (
                      <option key={h} value={h}>
                        {h}
                      </option>
                    ))}
                  </select>
                </label>
              ))}
            </div>
          </section>

          <section className="card">
            <h3>Preview (first {preview.preview.length} rows)</h3>
            <div className="preview-table">
              <table>
                <thead>
                  <tr>
                    {preview.headers.map((h) => (
                      <th key={h}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {preview.preview.map((r, i) => (
                    <tr key={i}>
                      {r.map((c, j) => (
                        <td key={j}>{c === null ? '' : String(c)}</td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          <section className="card">
            <button onClick={commit} disabled={committing}>
              {committing ? 'Importing…' : 'Replace active dataset with this file'}
            </button>
            {error && <div className="result err">{error}</div>}
          </section>
        </>
      )}

      {result && (
        <section className="card">
          <h3>Import complete</h3>
          <p>
            Inserted <strong>{result.inserted.toLocaleString()}</strong> customers.
            {result.errors.length > 0 && (
              <>
                {' '}
                <span className="warn">
                  {result.errors.length.toLocaleString()} row(s) rejected.
                </span>
              </>
            )}
          </p>
          {result.errors.length > 0 && (
            <details>
              <summary>{result.errors.length} validation errors</summary>
              <ul className="errors">
                {result.errors.slice(0, 100).map((e) => (
                  <li key={e.rowNumber}>
                    Row {e.rowNumber}
                    {e.externalCode ? ` (${e.externalCode})` : ''}: {e.errors.join('; ')}
                  </li>
                ))}
                {result.errors.length > 100 && <li>… and {result.errors.length - 100} more</li>}
              </ul>
            </details>
          )}
          {result.warnings.length > 0 && (
            <details open={result.errors.length === 0}>
              <summary>
                <span className="warn">{result.warnings.length} warning(s)</span> — rows imported,
                but something needs attention
              </summary>
              <ul className="errors">
                {result.warnings.slice(0, 100).map((w) => (
                  <li key={`warn-${w.rowNumber}`}>
                    Row {w.rowNumber}
                    {w.externalCode ? ` (${w.externalCode})` : ''}: {w.errors.join('; ')}
                  </li>
                ))}
                {result.warnings.length > 100 && (
                  <li>… and {result.warnings.length - 100} more</li>
                )}
              </ul>
            </details>
          )}
        </section>
      )}
    </div>
  );
}
