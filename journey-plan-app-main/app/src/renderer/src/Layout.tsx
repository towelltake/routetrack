import { useEffect, useState } from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import type { CustomerDataset } from '@journey/shared';
import { PlanProgressProvider } from './contexts/PlanProgressContext';
import { TopoBg } from './components/TopoBg';
import { PRINT_MODE } from './printMode';
import logoUrl from './assets/logo.png';

export function Layout() {
  const [version, setVersion] = useState('');
  const [activeDataset, setActiveDataset] = useState<CustomerDataset | null>(null);

  useEffect(() => {
    void window.api.appVersion().then(setVersion);
    void window.api.getActiveDataset().then(setActiveDataset);
    const interval = setInterval(() => {
      void window.api.getActiveDataset().then(setActiveDataset);
    }, 3000);
    return () => clearInterval(interval);
  }, []);

  return (
    <PlanProgressProvider>
    {!PRINT_MODE && <TopoBg />}
    <div className="layout">
      {!PRINT_MODE && (
      <aside className="sidebar">
        <header>
          <img src={logoUrl} alt="" className="masthead-logo" />
          <h1>Journey Plan</h1>
          <span className="version">v{version}</span>
        </header>
        <nav>
          <NavLink to="/import">Import</NavLink>
          <NavLink to="/customers">Customers</NavLink>
          <NavLink to="/plan">Plan</NavLink>
          <NavLink to="/analytics">Analytics</NavLink>
          <NavLink to="/map">Map</NavLink>
          <NavLink to="/salesmen">Salesmen</NavLink>
          <NavLink to="/assignments">Assignments</NavLink>
          <NavLink to="/help">Help</NavLink>
          <NavLink to="/settings">Settings</NavLink>
        </nav>
        <footer className="dataset-footer">
          <small className="muted">Active dataset</small>
          {activeDataset ? (
            <div>
              <strong title={activeDataset.name}>{activeDataset.name}</strong>
              <small>{activeDataset.rowCount.toLocaleString()} customers</small>
            </div>
          ) : (
            <em className="muted">No dataset imported yet</em>
          )}
        </footer>
      </aside>
      )}
      <main className="content">
        <Outlet />
      </main>
    </div>
    </PlanProgressProvider>
  );
}
