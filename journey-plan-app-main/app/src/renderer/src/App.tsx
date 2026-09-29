import { lazy, Suspense } from 'react';
import { createHashRouter, Navigate, RouterProvider } from 'react-router-dom';
import { Layout } from './Layout';
import { AssignmentsScreen } from './screens/AssignmentsScreen';
import { CustomersScreen } from './screens/CustomersScreen';
import { HelpScreen } from './screens/HelpScreen';
import { ImportScreen } from './screens/ImportScreen';
import { MapScreen } from './screens/MapScreen';
import { PlanScreen } from './screens/PlanScreen';
import { SalesmenScreen } from './screens/SalesmenScreen';
import { SettingsScreen } from './screens/SettingsScreen';

// Lazy-loaded so recharts (only used here) splits into its own chunk and stays
// out of the main renderer bundle. The PDF export window loads this route too;
// its 20s ready-timeout comfortably covers the extra chunk fetch.
const AnalyticsScreen = lazy(() =>
  import('./screens/AnalyticsScreen').then((m) => ({ default: m.AnalyticsScreen })),
);

const router = createHashRouter([
  {
    path: '/',
    element: <Layout />,
    children: [
      { index: true, element: <Navigate to="/import" replace /> },
      { path: 'import', element: <ImportScreen /> },
      { path: 'customers', element: <CustomersScreen /> },
      { path: 'plan', element: <PlanScreen /> },
      {
        path: 'analytics',
        element: (
          <Suspense fallback={<div className="screen"><p className="muted">Loading…</p></div>}>
            <AnalyticsScreen />
          </Suspense>
        ),
      },
      { path: 'map', element: <MapScreen /> },
      { path: 'salesmen', element: <SalesmenScreen /> },
      { path: 'assignments', element: <AssignmentsScreen /> },
      { path: 'help', element: <HelpScreen /> },
      { path: 'settings', element: <SettingsScreen /> },
    ],
  },
]);

export function App() {
  return <RouterProvider router={router} />;
}
