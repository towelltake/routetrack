import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import './styles.css';

window.addEventListener('error', (evt) => {
  void window.api.appendDiagnostic({
    message: evt.message,
    stack: evt.error instanceof Error ? evt.error.stack : undefined,
  });
});

window.addEventListener('unhandledrejection', (evt) => {
  const reason = evt.reason;
  const message = reason instanceof Error ? reason.message : String(reason);
  const stack = reason instanceof Error ? reason.stack : undefined;
  void window.api.appendDiagnostic({ message, stack });
});

const root = document.getElementById('root');
if (!root) throw new Error('root element missing');

createRoot(root).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
