import { assignCustomers, generatePlan } from './workspace';

self.onmessage = ({ data }) => {
  try {
    const { customers, reps, settings, assignments, action } = data;
    const result = action === 'assign' ? assignCustomers(customers, reps, settings.speed) : generatePlan(customers, reps, settings, assignments);
    self.postMessage({ result });
  } catch (e) { self.postMessage({ error: e.message }); }
};
