import { useEffect, useState } from 'react';
import type { Salesman, SalesChannel } from '@journey/shared';

const CHANNELS: SalesChannel[] = ['MT', 'TT', 'WS'];
const CHANNEL_LABELS: Record<SalesChannel, string> = {
  MT: 'MT (Modern Trade)',
  TT: 'TT (Traditional Trade)',
  WS: 'WS (Wholesale)'
};

const DAYS = [
  { value: 0, label: 'Sun' },
  { value: 1, label: 'Mon' },
  { value: 2, label: 'Tue' },
  { value: 3, label: 'Wed' },
  { value: 4, label: 'Thu' },
  { value: 5, label: 'Fri' },
  { value: 6, label: 'Sat' },
];

const blankSalesman = (): Salesman => ({
  id: 0,
  name: '',
  startLocationLat: 23.5880,
  startLocationLng: 58.3829,
  workingDays: [0, 1, 2, 3, 4],
  workingHoursStart: '08:00',
  workingHoursEnd: '17:00',
  assignedAreas: [],
  assignedRegions: [],
  channelSkills: [],
  includeCommute: false,
});

export function SalesmenScreen() {
  const [list, setList] = useState<Salesman[]>([]);
  const [editing, setEditing] = useState<Salesman | null>(null);
  const [availableAreas, setAvailableAreas] = useState<string[]>([]);
  const [availableRegions, setAvailableRegions] = useState<string[]>([]);

  const reload = async () => {
    setList(await window.api.listSalesmen());
    setAvailableAreas(await window.api.distinctAreas());
    setAvailableRegions(await window.api.distinctRegions());
  };

  useEffect(() => {
    void reload();
  }, []);

  const save = async (s: Salesman) => {
    await window.api.upsertSalesman(s);
    setEditing(null);
    await reload();
  };

  const remove = async (id: number) => {
    if (
      !confirm(
        'Delete this salesman?\n\nExisting plans keep their own copy of him, ' +
          'so their calendars and analytics are unaffected. He just stops ' +
          'appearing in future plans.',
      )
    )
      return;
    await window.api.deleteSalesman(id);
    setEditing(null);
    await reload();
  };

  const clearAll = async () => {
    if (list.length === 0) return;
    // Two-step confirm because this clears every customer pin and algorithm
    // assignment in one click. Cheap insurance vs a misclick. Existing plans
    // are NOT touched — each plan keeps its own salesman snapshot.
    if (
      !confirm(
        `Delete all ${list.length} salesmen?\n\n` +
          `Existing plans keep their visits and analytics (each plan stores its ` +
          `own copy of its salesmen). But this clears any customer pins pointing ` +
          `at these salesmen AND wipes every algorithm-computed assignment ` +
          `(assigned_salesman_id) for every customer. Customers themselves stay, ` +
          `but you'll need to re-run Compute Assignments.`,
      )
    ) {
      return;
    }
    if (!confirm('Are you sure? This cannot be undone.')) return;
    const removed = await window.api.clearAllSalesmen();
    setEditing(null);
    await reload();
    alert(`Removed ${removed} salesmen.`);
  };

  return (
    <div className="screen">
      <header className="screen-header">
        <h2>Salesmen</h2>
        <button onClick={() => setEditing(blankSalesman())}>Add salesman</button>
        <button
          className="danger"
          onClick={clearAll}
          disabled={list.length === 0}
          title="Delete all salesmen, customer pins, AND algorithm assignments. Customers stay; existing plans keep their own salesman snapshots."
        >
          Clear all salesmen
        </button>
      </header>

      <div className="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Start (lat, lng)</th>
              <th>Working days</th>
              <th>Hours</th>
              <th>Assigned areas</th>
              <th>Assigned regions</th>
              <th>Channels</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {list.map((s) => (
              <tr key={s.id}>
                <td>{s.name}</td>
                <td>
                  {s.startLocationLat.toFixed(4)}, {s.startLocationLng.toFixed(4)}
                </td>
                <td>
                  {s.workingDays
                    .map((d) => DAYS.find((x) => x.value === d)?.label ?? d)
                    .join(', ')}
                </td>
                <td>
                  {s.workingHoursStart}–{s.workingHoursEnd}
                </td>
                <td>
                  {s.assignedAreas.length === 0 ? (
                    <em className="muted">—</em>
                  ) : (
                    s.assignedAreas.join(', ')
                  )}
                </td>
                <td>
                  {s.assignedRegions.length === 0 ? (
                    <em className="muted">—</em>
                  ) : (
                    s.assignedRegions.join(', ')
                  )}
                </td>
                <td>
                  {s.channelSkills.length === 0 ? (
                    <em className="muted">all</em>
                  ) : (
                    s.channelSkills.join(', ')
                  )}
                </td>
                <td>
                  <button onClick={() => setEditing(s)}>Edit</button>
                </td>
              </tr>
            ))}
            {list.length === 0 && (
              <tr>
                <td colSpan={8} className="muted">
                  No salesmen yet. Click "Add salesman" to create one.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* key= remounts the form when the target changes (another salesman, or
          "Add salesman" while one is open — blankSalesman() has id 0). Without
          it the draft seeded from props sticks to the first record opened, so
          Save overwrites that salesman instead of creating a new one. */}
      {editing && (
        <SalesmanForm
          key={editing.id}
          initial={editing}
          availableAreas={availableAreas}
          availableRegions={availableRegions}
          onSave={save}
          onDelete={remove}
          onCancel={() => setEditing(null)}
        />
      )}
    </div>
  );
}

function SalesmanForm(props: {
  initial: Salesman;
  availableAreas: string[];
  availableRegions: string[];
  onSave: (s: Salesman) => Promise<void>;
  onDelete: (id: number) => Promise<void>;
  onCancel: () => void;
}) {
  const [draft, setDraft] = useState<Salesman>(props.initial);

  const toggleDay = (day: number) => {
    const set = new Set(draft.workingDays);
    if (set.has(day)) set.delete(day);
    else set.add(day);
    setDraft({ ...draft, workingDays: Array.from(set).sort() });
  };

  const toggleArea = (area: string) => {
    const set = new Set(draft.assignedAreas);
    if (set.has(area)) set.delete(area);
    else set.add(area);
    setDraft({ ...draft, assignedAreas: Array.from(set).sort() });
  };

  const toggleRegion = (region: string) => {
    const set = new Set(draft.assignedRegions);
    if (set.has(region)) set.delete(region);
    else set.add(region);
    setDraft({ ...draft, assignedRegions: Array.from(set).sort() });
  };

  const toggleChannel = (ch: SalesChannel) => {
    const set = new Set(draft.channelSkills);
    if (set.has(ch)) set.delete(ch);
    else set.add(ch);
    setDraft({ ...draft, channelSkills: Array.from(set).sort() as SalesChannel[] });
  };

  return (
    <aside className="side-panel">
      <header>
        <h3>{draft.id ? 'Edit salesman' : 'New salesman'}</h3>
        <button onClick={props.onCancel}>×</button>
      </header>
      <label>
        Name
        <input value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} />
      </label>
      <div className="row">
        <label>
          Start latitude
          <input
            type="number"
            step="0.000001"
            value={draft.startLocationLat}
            onChange={(e) => setDraft({ ...draft, startLocationLat: Number(e.target.value) })}
          />
        </label>
        <label>
          Start longitude
          <input
            type="number"
            step="0.000001"
            value={draft.startLocationLng}
            onChange={(e) => setDraft({ ...draft, startLocationLng: Number(e.target.value) })}
          />
        </label>
      </div>
      <label className="day-toggle" style={{ marginTop: 4 }}>
        <input
          type="checkbox"
          checked={draft.includeCommute}
          onChange={(e) => setDraft({ ...draft, includeCommute: e.target.checked })}
        />
        Count commute from start location (home→first &amp; last→home legs are
        timed and optimized; off = day starts at the first customer)
      </label>
      <fieldset className="day-picker">
        <legend>Working days</legend>
        {DAYS.map((d) => (
          <label key={d.value} className="day-toggle">
            <input
              type="checkbox"
              checked={draft.workingDays.includes(d.value)}
              onChange={() => toggleDay(d.value)}
            />
            {d.label}
          </label>
        ))}
      </fieldset>
      <div className="row">
        <label>
          Start
          <input
            type="time"
            value={draft.workingHoursStart}
            onChange={(e) => setDraft({ ...draft, workingHoursStart: e.target.value })}
          />
        </label>
        <label>
          End
          <input
            type="time"
            value={draft.workingHoursEnd}
            onChange={(e) => setDraft({ ...draft, workingHoursEnd: e.target.value })}
          />
        </label>
      </div>
      <fieldset className="day-picker">
        <legend>Assigned areas</legend>
        {props.availableAreas.length === 0 && (
          <small className="muted">
            No areas in the active dataset. Map an "area" column at import time to enable this.
          </small>
        )}
        {props.availableAreas.map((area) => (
          <label key={area} className="day-toggle">
            <input
              type="checkbox"
              checked={draft.assignedAreas.includes(area)}
              onChange={() => toggleArea(area)}
            />
            {area}
          </label>
        ))}
        <small className="muted" style={{ display: 'block', marginTop: 4 }}>
          Narrow tag (e.g. a sub-wilayat). Empty = no area restriction.
        </small>
      </fieldset>
      <fieldset className="day-picker">
        <legend>Assigned regions</legend>
        {props.availableRegions.length === 0 && (
          <small className="muted">
            No regions in the active dataset. Map a "region" column at import time to enable this.
          </small>
        )}
        {props.availableRegions.map((region) => (
          <label key={region} className="day-toggle">
            <input
              type="checkbox"
              checked={draft.assignedRegions.includes(region)}
              onChange={() => toggleRegion(region)}
            />
            {region}
          </label>
        ))}
        <small className="muted" style={{ display: 'block', marginTop: 4 }}>
          Broader parent (e.g. wilayat / governorate). A customer is eligible if its area OR region matches. Both empty = covers everyone.
        </small>
      </fieldset>
      <fieldset className="day-picker">
        <legend>Channel skills</legend>
        {CHANNELS.map((ch) => (
          <label key={ch} className="day-toggle">
            <input
              type="checkbox"
              checked={draft.channelSkills.includes(ch)}
              onChange={() => toggleChannel(ch)}
            />
            {CHANNEL_LABELS[ch]}
          </label>
        ))}
        <small className="muted" style={{ display: 'block', marginTop: 4 }}>
          Empty = salesman serves any channel. Check both for a versatile rep.
        </small>
      </fieldset>
      <div className="panel-actions">
        <button onClick={() => void props.onSave(draft)} disabled={!draft.name.trim()}>
          Save
        </button>
        {draft.id !== 0 && (
          <button onClick={() => void props.onDelete(draft.id)} className="danger">
            Delete
          </button>
        )}
      </div>
    </aside>
  );
}
