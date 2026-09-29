import { useEffect, useMemo, useState } from 'react';
import type { Customer, SalesChannel } from '@journey/shared';

export function CustomersScreen() {
  const [search, setSearch] = useState('');
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [selected, setSelected] = useState<Customer | null>(null);
  const [loading, setLoading] = useState(false);

  const reload = useMemo(
    () => async () => {
      setLoading(true);
      try {
        const list = await window.api.listCustomers({
          search: search || undefined,
          limit: 2000,
        });
        setCustomers(list);
      } finally {
        setLoading(false);
      }
    },
    [search],
  );

  useEffect(() => {
    void reload();
  }, [reload]);

  const saveEdit = async (c: Customer) => {
    await window.api.upsertCustomer(c);
    setSelected(null);
    await reload();
  };

  const remove = async (id: number) => {
    if (!confirm('Delete this customer? This cannot be undone.')) return;
    await window.api.deleteCustomer(id);
    setSelected(null);
    await reload();
  };

  return (
    <div className="screen">
      <header className="screen-header">
        <h2>Customers</h2>
        <div className="filters">
          <input
            type="search"
            placeholder="Search name or code"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <span className="muted">
            {loading ? 'Loading…' : `${customers.length.toLocaleString()} shown`}
          </span>
        </div>
      </header>

      <div className="table-wrap">
        <table className="customers-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Lat</th>
              <th>Lng</th>
              <th>Facetime</th>
              <th>Freq</th>
              <th>Area</th>
              <th>Region</th>
              <th>Channel</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {customers.map((c) => (
              <tr key={c.id} className={c.id === selected?.id ? 'selected' : ''}>
                <td>{c.externalCode}</td>
                <td>{c.name}</td>
                <td>{c.lat?.toFixed(4) ?? '—'}</td>
                <td>{c.lng?.toFixed(4) ?? '—'}</td>
                <td>{c.facetimeMinutes}m</td>
                <td>{c.monthlyFrequency}/mo</td>
                <td>{c.area ?? <em className="muted">—</em>}</td>
                <td>{c.region ?? <em className="muted">—</em>}</td>
                <td>{c.channel ?? <em className="muted">—</em>}</td>
                <td>
                  <button onClick={() => setSelected(c)}>Edit</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* key= remounts the panel when the user clicks Edit on a different row.
          Without it the draft state seeded from props sticks to the FIRST
          customer opened, and Save writes that customer's id. */}
      {selected && (
        <EditPanel
          key={selected.id}
          customer={selected}
          onSave={saveEdit}
          onCancel={() => setSelected(null)}
          onDelete={remove}
        />
      )}
    </div>
  );
}

function EditPanel(props: {
  customer: Customer;
  onSave: (c: Customer) => void | Promise<void>;
  onCancel: () => void;
  onDelete: (id: number) => void | Promise<void>;
}) {
  const [draft, setDraft] = useState<Customer>(props.customer);

  const update = <K extends keyof Customer>(key: K, value: Customer[K]) => {
    setDraft({ ...draft, [key]: value });
  };

  return (
    <aside className="side-panel">
      <header>
        <h3>Edit customer</h3>
        <button onClick={props.onCancel}>×</button>
      </header>
      <label>
        Code
        <input value={draft.externalCode} onChange={(e) => update('externalCode', e.target.value)} />
      </label>
      <label>
        Name
        <input value={draft.name} onChange={(e) => update('name', e.target.value)} />
      </label>
      <label>
        Address
        <input
          value={draft.address ?? ''}
          onChange={(e) => update('address', e.target.value || null)}
        />
      </label>
      <div className="row">
        <label>
          Lat
          <input
            type="number"
            step="0.000001"
            value={draft.lat ?? ''}
            onChange={(e) => update('lat', e.target.value === '' ? null : Number(e.target.value))}
          />
        </label>
        <label>
          Lng
          <input
            type="number"
            step="0.000001"
            value={draft.lng ?? ''}
            onChange={(e) => update('lng', e.target.value === '' ? null : Number(e.target.value))}
          />
        </label>
      </div>
      <div className="row">
        <label>
          Facetime (min)
          <input
            type="number"
            value={draft.facetimeMinutes}
            onChange={(e) => update('facetimeMinutes', Number(e.target.value))}
          />
        </label>
        <label>
          Frequency / month
          <input
            type="number"
            value={draft.monthlyFrequency}
            onChange={(e) => update('monthlyFrequency', Number(e.target.value))}
          />
        </label>
      </div>
      <div className="row">
        <label>
          Area
          <input
            value={draft.area ?? ''}
            onChange={(e) => update('area', e.target.value || null)}
          />
        </label>
        <label>
          Region
          <input
            value={draft.region ?? ''}
            onChange={(e) => update('region', e.target.value || null)}
          />
        </label>
      </div>
      <div className="row">
        <label>
          Channel
          <select
            value={draft.channel ?? ''}
            onChange={(e) =>
              update('channel', (e.target.value || null) as SalesChannel | null)
            }
          >
            <option value="">— any —</option>
            <option value="MT">MT (Modern Trade)</option>
            <option value="TT">TT (Traditional Trade)</option>
            <option value="WS">WS (Wholesale)</option>
          </select>
        </label>
      </div>
      <div className="row">
        <label>
          Visit window from
          <input
            type="time"
            value={draft.visitWindowStart ?? ''}
            onChange={(e) => update('visitWindowStart', e.target.value || null)}
          />
        </label>
        <label>
          Visit window to
          <input
            type="time"
            value={draft.visitWindowEnd ?? ''}
            onChange={(e) => update('visitWindowEnd', e.target.value || null)}
          />
        </label>
      </div>
      <div className="panel-actions">
        <button
          onClick={() => {
            const ws = draft.visitWindowStart;
            const we = draft.visitWindowEnd;
            if ((ws === null) !== (we === null)) {
              alert('Set both visit window times, or clear both.');
              return;
            }
            if (ws !== null && we !== null && ws >= we) {
              alert('Visit window start must be before its end.');
              return;
            }
            void props.onSave(draft);
          }}
        >
          Save
        </button>
        <button onClick={() => void props.onDelete(draft.id)} className="danger">
          Delete
        </button>
      </div>
    </aside>
  );
}
