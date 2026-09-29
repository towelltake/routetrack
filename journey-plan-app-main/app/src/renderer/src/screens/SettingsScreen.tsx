import { useEffect, useState } from 'react';
import type { AppSettings, CustomerDataset, DiagnosticEntry } from '@journey/shared';
import { WorkingScheduleFields } from '../components/WorkingScheduleFields';
import { formatDbTimestamp } from './planMath';

export function SettingsScreen() {
  const [settings, setSettings] = useState<AppSettings | null>(null);
  const [datasets, setDatasets] = useState<CustomerDataset[]>([]);
  const [diagnostics, setDiagnostics] = useState<DiagnosticEntry[]>([]);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  const reload = async () => {
    setSettings(await window.api.getSettings());
    setDatasets(await window.api.listDatasets());
    setDiagnostics(await window.api.readDiagnostics(200));
  };

  useEffect(() => {
    void reload();
  }, []);

  const saveSettings = async () => {
    if (!settings) return;
    setSaving(true);
    setMessage(null);
    try {
      await window.api.setSettings(settings);
      setMessage('Settings saved.');
    } finally {
      setSaving(false);
    }
  };

  const activate = async (id: number) => {
    await window.api.activateDataset(id);
    await reload();
  };

  const deleteDataset = async (d: CustomerDataset) => {
    if (d.isActive) return;
    const ok1 = window.confirm(
      `Delete "${d.name}"? This permanently removes:\n` +
        `  • ${d.rowCount.toLocaleString()} customers\n` +
        `  • every plan + visit built on this dataset\n\n` +
        `This cannot be undone.`,
    );
    if (!ok1) return;
    const ok2 = window.confirm(`Last chance — really delete "${d.name}"?`);
    if (!ok2) return;
    try {
      const { plans, customers } = await window.api.deleteDataset(d.id);
      setMessage(`Deleted "${d.name}" — ${customers} customers, ${plans} plans.`);
      await reload();
    } catch (err) {
      setMessage(err instanceof Error ? err.message : String(err));
    }
  };

  const refreshDiagnostics = async () => {
    setDiagnostics(await window.api.readDiagnostics(200));
  };

  const clearDiagnostics = async () => {
    await window.api.clearDiagnostics();
    setDiagnostics([]);
  };

  const copyDiagnostics = async () => {
    const text = diagnostics.map((d) => JSON.stringify(d)).join('\n');
    await navigator.clipboard.writeText(text);
    setMessage('Error log copied to clipboard.');
  };

  if (!settings) return <div className="screen">Loading…</div>;

  return (
    <div className="screen">
      <header className="screen-header">
        <h2>Settings</h2>
      </header>

      <section className="card">
        <h3>Default values for new customers</h3>
        <div className="row">
          <label>
            Facetime (min)
            <input
              type="number"
              value={settings.defaultFacetimeMinutes}
              onChange={(e) =>
                setSettings({ ...settings, defaultFacetimeMinutes: Number(e.target.value) })
              }
            />
          </label>
          <label>
            Monthly frequency
            <input
              type="number"
              value={settings.defaultMonthlyFrequency}
              onChange={(e) =>
                setSettings({ ...settings, defaultMonthlyFrequency: Number(e.target.value) })
              }
            />
          </label>
        </div>
      </section>

      <section className="card">
        <h3>Default working schedule for new salesmen</h3>
        <WorkingScheduleFields
          workingDays={settings.defaultWorkingDays}
          workingHoursStart={settings.defaultWorkingHoursStart}
          workingHoursEnd={settings.defaultWorkingHoursEnd}
          onChange={(next) =>
            setSettings({
              ...settings,
              defaultWorkingDays: next.workingDays,
              defaultWorkingHoursStart: next.workingHoursStart,
              defaultWorkingHoursEnd: next.workingHoursEnd,
            })
          }
        />
      </section>

      <section className="card">
        <button onClick={() => void saveSettings()} disabled={saving}>
          {saving ? 'Saving…' : 'Save settings'}
        </button>
        {message && <span className="muted small"> {message}</span>}
      </section>

      <section className="card">
        <h3>Datasets</h3>
        {datasets.length === 0 && <p className="muted">No datasets yet.</p>}
        {datasets.length > 0 && (
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Source file</th>
                <th>Imported</th>
                <th>Rows</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {datasets.map((d) => (
                <tr key={d.id}>
                  <td>{d.name}</td>
                  <td>{d.sourceFilename}</td>
                  <td>{formatDbTimestamp(d.importedAt)}</td>
                  <td>{d.rowCount.toLocaleString()}</td>
                  <td>
                    {d.isActive ? (
                      <span className="badge ok">active</span>
                    ) : (
                      <div className="row" style={{ gap: 4 }}>
                        <button onClick={() => void activate(d.id)}>Make active</button>
                        <button
                          className="danger"
                          onClick={() => void deleteDataset(d)}
                          title="Delete this dataset, its customers, and all plans built on it"
                        >
                          Delete
                        </button>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>

      <section className="card">
        <h3>Diagnostics</h3>
        <p className="muted small">
          Uncaught errors from the app, the sidecar, and the UI are appended here. Nothing is sent
          off this machine.
        </p>
        <div className="row">
          <button onClick={() => void refreshDiagnostics()}>Refresh</button>
          <button onClick={() => void copyDiagnostics()} disabled={diagnostics.length === 0}>
            Copy to clipboard
          </button>
          <button onClick={() => void clearDiagnostics()} disabled={diagnostics.length === 0}>
            Clear log
          </button>
        </div>
        {diagnostics.length === 0 ? (
          <p className="muted">No errors logged.</p>
        ) : (
          <pre className="diagnostics-log">
            {diagnostics
              .slice()
              .reverse()
              .map((d) => {
                const firstStack = d.stack?.split('\n')[0]?.trim() ?? '';
                return `[${d.ts}] (${d.source}) ${d.message}${firstStack ? `\n    ${firstStack}` : ''}`;
              })
              .join('\n')}
          </pre>
        )}
      </section>
    </div>
  );
}
