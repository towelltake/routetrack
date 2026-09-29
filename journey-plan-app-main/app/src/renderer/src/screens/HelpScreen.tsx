import { Link } from 'react-router-dom';

export function HelpScreen() {
  return (
    <div className="screen">
      <header className="screen-header">
        <h2>Help — how to build a journey plan</h2>
      </header>

      <section className="card">
        <h3>1. Import customers</h3>
        <p>
          Bring in an Excel or CSV file with one row per customer. The importer requires an{' '}
          <em>external code</em>, a <em>name</em>, and the customer's <em>latitude</em> and{' '}
          <em>longitude</em>. Visit <em>frequency</em>, <em>facetime</em>, <em>allowed days</em>,{' '}
          <em>area</em>, <em>region</em>, <em>channel</em>, and <em>pinned salesman</em> are
          optional. Each upload replaces the active dataset; old ones archive automatically and can
          be restored from Settings.
        </p>
        <Link to="/import">Open Import →</Link>
      </section>

      <section className="card">
        <h3>2. Set up your salesmen</h3>
        <p>
          Define your team — name, home location, working days, working hours, and (optionally) the
          areas each salesman covers. If a salesman has assigned areas, they only serve customers
          whose area matches (or has no area set).
        </p>
        <Link to="/salesmen">Open Salesmen →</Link>
      </section>

      <section className="card">
        <h3>3. Review on the map</h3>
        <p>
          All customers must arrive in the import with valid lat/lng — geocoding is no longer part
          of this app. Review markers on the Map; if any pin looks wrong, edit the customer's
          coordinates in the Customers screen.
        </p>
        <Link to="/map">Open Map →</Link>
      </section>

      <section className="card">
        <h3>4. Generate a plan</h3>
        <p>
          Pick a 4-week period and click <strong>Generate</strong>. The optimizer assigns visits to
          salesmen and days, honouring frequency, facetime, working hours, allowed days, area
          coverage, and pinned salesmen. Drag visits in the calendar grid to fix assignments — the
          source and destination days re-sequence automatically. Lock as final when sign-off is
          done; export to Excel for the field team.
        </p>
        <Link to="/plan">Open Plan →</Link>
      </section>

      <section className="card">
        <p className="muted small">
          Need to change working hours, frequencies, or facetime defaults?{' '}
          <Link to="/settings">Open Settings</Link>.
        </p>
        <p className="muted small">
          Hit a bug? Copy the error log from <Link to="/settings">Settings → Diagnostics</Link> and
          share it with support.
        </p>
      </section>
    </div>
  );
}
