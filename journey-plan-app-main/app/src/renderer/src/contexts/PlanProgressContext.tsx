import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';

// Plan generation runs for several minutes. The Plan screen used to own this
// state locally, which meant navigating away (e.g. to Analytics) and back
// unmounted PlanScreen, tore down the `plan:progress` IPC listener, and lost
// the bar entirely while the main process kept emitting events. Hoisted here
// so the listener stays subscribed for the app's lifetime and any screen can
// read the current value.

export interface PlanProgress {
  message: string;
  percent: number;
}

interface PlanProgressContextValue {
  progress: PlanProgress | null;
  generating: boolean;
  // Called by the screen that kicks off the IPC. Sets a "Starting…" sentinel
  // so the bar appears even before the first server-side event lands.
  beginGeneration: () => void;
  // Called from the calling screen's finally{} block. Clears both fields so
  // the bar disappears across all screens.
  endGeneration: () => void;
}

const Context = createContext<PlanProgressContextValue | null>(null);

export function PlanProgressProvider({ children }: { children: ReactNode }) {
  const [progress, setProgress] = useState<PlanProgress | null>(null);
  const [generating, setGenerating] = useState(false);

  // Subscribe once for the app's lifetime — Layout is the root route and
  // doesn't unmount across navigation, so this effect runs once.
  useEffect(() => {
    return window.api.onPlanProgress((evt) =>
      setProgress({ message: evt.message, percent: evt.percent }),
    );
  }, []);

  const beginGeneration = useCallback(() => {
    setProgress({ message: 'Starting…', percent: 0 });
    setGenerating(true);
  }, []);

  const endGeneration = useCallback(() => {
    setGenerating(false);
    setProgress(null);
  }, []);

  return (
    <Context.Provider value={{ progress, generating, beginGeneration, endGeneration }}>
      {children}
    </Context.Provider>
  );
}

export function usePlanProgress(): PlanProgressContextValue {
  const ctx = useContext(Context);
  if (!ctx) throw new Error('usePlanProgress must be used inside PlanProgressProvider');
  return ctx;
}
