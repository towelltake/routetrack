import { distance, hasCoordinates } from './planning.js';

export const weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
export const minutes = value => /^\d{2}:\d{2}$/.test(value ?? '') && Number(value.slice(0, 2)) < 24 && Number(value.slice(3)) < 60
  ? Number(value.slice(0, 2)) * 60 + Number(value.slice(3)) : NaN;
export const clock = value => `${String(Math.floor(Math.round(value) / 60)).padStart(2, '0')}:${String(Math.round(value) % 60).padStart(2, '0')}`;
export function dateAt(start, offset) {
  const date = new Date(`${start}T12:00:00Z`);
  date.setUTCDate(date.getUTCDate() + offset);
  return date.toISOString().slice(0, 10);
}
export const dayOf = date => new Date(`${date}T12:00:00Z`).getUTCDay();
const point = c => ({ fixedlatitude: c.lat, fixedlongitude: c.lng });
export const located = c => hasCoordinates(point(c));
const kmBetween = (a, b) => distance(point(a), point(b));
const clone = value => JSON.parse(JSON.stringify(value));

export function validateInputs(customers, reps, settings) {
  const errors = [];
  if (!/^\d{4}-\d{2}-\d{2}$/.test(settings.start) || !Number.isFinite(Date.parse(`${settings.start}T12:00:00Z`)) || dateAt(settings.start, 0) !== settings.start) errors.push('Choose a valid period start.');
  if (!(settings.speed >= 5 && settings.speed <= 120)) errors.push('Estimated travel speed must be 5–120 km/h.');
  if (!customers.length || customers.length > 500) errors.push('Load between 1 and 500 customers.');
  if (!reps.length) errors.push('Add at least one salesman.');
  const validDays = days => Array.isArray(days) && days.length > 0 && days.every(d => Number.isInteger(d) && d >= 0 && d <= 6) && new Set(days).size === days.length;
  for (const c of customers) {
    if (!Number.isInteger(c.frequency) || c.frequency < 1 || c.frequency > 20) errors.push(`${c.name}: visits must be 1–20 per four weeks.`);
    if (!Number.isFinite(c.face) || c.face < 1 || c.face > 240) errors.push(`${c.name}: face time must be 1–240 minutes.`);
    if (!validDays(c.days)) errors.push(`${c.name}: select at least one allowed weekday.`);
    if ((c.windowStart || c.windowEnd) && !(Number.isFinite(minutes(c.windowStart)) && minutes(c.windowEnd) > minutes(c.windowStart))) errors.push(`${c.name}: enter a complete, increasing time window.`);
    if (c.pin && !reps.some(r => r.id === c.pin)) errors.push(`${c.name}: fixed salesman is no longer available.`);
  }
  for (const r of reps) {
    if (!r.name.trim()) errors.push('Every salesman needs a name.');
    if (!validDays(r.days)) errors.push(`${r.name}: select working days.`);
    if (!(Number.isFinite(minutes(r.start)) && minutes(r.end) > minutes(r.start))) errors.push(`${r.name}: shift end must follow shift start on the same day.`);
    if (!Number.isInteger(r.maxVisits) || r.maxVisits < 1 || r.maxVisits > 100) errors.push(`${r.name}: daily visit target must be 1–100.`);
    if (r.commute && !located(r)) errors.push(`${r.name}: enter valid starting coordinates to include commute.`);
  }
  return [...new Set(errors)];
}

export function eligible(c, r) {
  if (c.pin) return c.pin === r.id;
  const split = value => value.split(',').map(v => v.trim().toLowerCase()).filter(Boolean);
  const areas = split(r.areas), regions = split(r.regions);
  const geo = (!areas.length && !regions.length) || (!c.area && !c.region) || areas.includes(c.area.toLowerCase()) || regions.includes(c.region.toLowerCase());
  return geo && (!r.channels.length || !c.channel || r.channels.includes(c.channel));
}
export const capacity = r => r.days.length * 4 * (minutes(r.end) - minutes(r.start));

export function assignCustomers(customers, reps, speed = 40) {
  const assignments = {}, issues = [], loads = Object.fromEntries(reps.map(r => [r.id, 0]));
  const groups = Object.fromEntries(reps.map(r => [r.id, []]));
  for (const c of [...customers].sort((a, b) => b.frequency * b.face - a.frequency * a.face || a.id.localeCompare(b.id))) {
    if (!located(c)) { issues.push({ customer: c.id, count: c.frequency, reason: 'Missing or invalid coordinates' }); continue; }
    const candidates = reps.filter(r => eligible(c, r) && c.days.some(d => r.days.includes(d)));
    if (!candidates.length) { issues.push({ customer: c.id, count: c.frequency, reason: 'No eligible salesman with a matching working day' }); continue; }
    const cost = r => {
      const peers = groups[r.id];
      const travel = peers.length ? peers.reduce((sum, p) => sum + kmBetween(c, p), 0) / peers.length : located(r) ? kmBetween(c, r) : 0;
      return (loads[r.id] + c.frequency * c.face) / Math.max(1, capacity(r) / 1.3) * 100 + travel / speed * 60;
    };
    candidates.sort((a, b) => cost(a) - cost(b) || a.id.localeCompare(b.id));
    const rep = candidates[0]; assignments[c.id] = rep.id;
    loads[rep.id] += c.frequency * c.face; groups[rep.id].push(c);
  }
  return { assignments, issues, loads };
}

export function weekPattern(frequency, loads) {
  if (frequency === 4) return [0, 1, 2, 3];
  if (frequency <= 3) {
    const choices = frequency === 1 ? [[0], [1], [2], [3]] : frequency === 2 ? [[0, 2], [1, 3]] : [[0, 1, 3], [0, 2, 3]];
    return choices.sort((a, b) => a.reduce((sum, i) => sum + loads[i], 0) - b.reduce((sum, i) => sum + loads[i], 0))[0];
  }
  const result = [];
  for (let w = 0; w < 4; w++) for (let n = 0; n < Math.floor(frequency / 4); n++) result.push(w);
  result.push(...[0, 1, 2, 3].sort((a, b) => loads[a] - loads[b]).slice(0, frequency % 4));
  return result.sort((a, b) => a - b);
}

// Deterministic browser heuristic. Every estimate uses great-circle distance;
// this is not the desktop OR-Tools/OSRM optimisation engine.
export function scheduleDay(ids, rep, byId, speed) {
  let remaining = [...ids], previous = rep.commute ? rep : null, time = minutes(rep.start);
  let travel = 0, km = 0, face = 0, waiting = 0;
  const visits = [];
  while (remaining.length) {
    remaining.sort((a, b) => {
      const score = id => {
        const c = byId[id];
        const drive = previous ? kmBetween(previous, c) / speed * 60 : 0;
        const arrival = Math.max(time + drive, c.windowStart ? minutes(c.windowStart) : 0);
        return drive + Math.max(0, arrival - (c.windowEnd ? minutes(c.windowEnd) : Infinity)) * 5;
      };
      return score(a) - score(b) || a.localeCompare(b);
    });
    const id = remaining.shift(), c = byId[id];
    const leg = previous ? kmBetween(previous, c) : 0, drive = Math.ceil(leg / speed * 60);
    const arrival = time + drive, start = Math.max(arrival, c.windowStart ? minutes(c.windowStart) : 0), end = start + c.face;
    const warnings = [];
    if (end > minutes(rep.end)) warnings.push('Outside working hours');
    if (c.windowEnd && start > minutes(c.windowEnd)) warnings.push('Outside customer time window');
    if (visits.length >= rep.maxVisits) warnings.push('Above daily visit target');
    visits.push({ customer: id, sequence: visits.length + 1, start, end, drive, km: leg, warnings });
    travel += drive; km += leg; face += c.face; waiting += start - arrival; time = end; previous = c;
  }
  const returnKm = ids.length && rep.commute ? kmBetween(previous, rep) : 0;
  const returnMinutes = Math.ceil(returnKm / speed * 60);
  return { visits, travel: travel + returnMinutes, km: km + returnKm, face, waiting, returnMinutes,
    finish: time + returnMinutes, overload: Math.max(0, time + returnMinutes - minutes(rep.end)),
    excessVisits: Math.max(0, visits.length - rep.maxVisits) };
}

export function generatePlan(customers, reps, settings, assignments) {
  const errors = validateInputs(customers, reps, settings);
  if (errors.length) throw new Error(errors.join('\n'));
  const byId = Object.fromEntries(customers.map(c => [c.id, c])), days = [], issues = [];
  for (let offset = 0; offset < 28; offset++) {
    const date = dateAt(settings.start, offset);
    for (const r of reps) if (r.days.includes(dayOf(date))) days.push({ key: `${r.id}:${date}`, rep: r.id, date, week: Math.floor(offset / 7), ids: [], ...scheduleDay([], r, byId, settings.speed) });
  }
  const loads = Object.fromEntries(reps.map(r => [r.id, [0, 0, 0, 0]]));
  for (const c of [...customers].sort((a, b) => b.frequency * b.face - a.frequency * a.face || a.id.localeCompare(b.id))) {
    const rep = reps.find(r => r.id === (c.pin || assignments[c.id]));
    if (!located(c) || !rep || !eligible(c, rep)) { issues.push({ customer: c.id, count: c.frequency, reason: !located(c) ? 'Missing or invalid coordinates' : 'No eligible assignment' }); continue; }
    const pattern = weekPattern(c.frequency, loads[rep.id]), anchors = new Set();
    for (let week = 0; week < 4; week++) {
      const count = pattern.filter(w => w === week).length, chosen = [];
      for (let n = 0; n < count; n++) {
        const candidates = days.filter(d => d.rep === rep.id && d.week === week && c.days.includes(dayOf(d.date)) && !d.ids.includes(c.id));
        if (!candidates.length) { issues.push({ customer: c.id, count: 1, reason: `Week ${week + 1}: no remaining eligible day` }); continue; }
        const scored = candidates.map(d => {
          const scheduled = scheduleDay([...d.ids, c.id], rep, byId, settings.speed);
          const gap = chosen.length ? Math.min(...chosen.map(date => Math.abs(Date.parse(date) - Date.parse(d.date)) / 86400000)) : 7;
          const windowCost = scheduled.visits.filter(v => v.warnings.includes('Outside customer time window')).length * 1000;
          return { d, scheduled, cost: scheduled.overload * 100 + scheduled.excessVisits * 500 + windowCost + scheduled.face + scheduled.travel + (anchors.size && !anchors.has(dayOf(d.date)) ? 120 : 0) + (gap < 2 ? 200 : 0) };
        }).sort((a, b) => a.cost - b.cost || a.d.date.localeCompare(b.d.date));
        const { d, scheduled } = scored[0]; d.ids.push(c.id); Object.assign(d, scheduled);
        chosen.push(d.date); loads[rep.id][week] += c.face;
      }
      if (!anchors.size) chosen.forEach(date => anchors.add(dayOf(date)));
    }
  }
  return { customers: clone(customers), reps: clone(reps), settings: clone(settings), assignments: { ...assignments }, days, issues };
}

export function moveVisit(plan, customerId, fromKey, toKey) {
  if (fromKey === toKey) return plan;
  const result = clone(plan), from = result.days.find(d => d.key === fromKey), to = result.days.find(d => d.key === toKey);
  const c = result.customers.find(c => c.id === customerId), rep = result.reps.find(r => r.id === to?.rep);
  if (!from?.ids.includes(customerId) || !to || !c || !rep) throw new Error('Choose a valid visit and destination.');
  if (!eligible(c, rep) || !c.days.includes(dayOf(to.date))) throw new Error('Destination violates customer ownership, territory, channel or allowed weekdays.');
  const sources = from.rep === to.rep ? [from] : result.days.filter(d => d.ids.includes(customerId));
  const moves = sources.map(source => ({ source, dest: source.key === from.key ? to : result.days.find(d => d.rep === to.rep && d.date === source.date) }));
  const destinationKeys = moves.map(m => m.dest?.key);
  if (moves.some(m => !m.dest || m.dest.ids.includes(customerId)) || new Set(destinationKeys).size !== destinationKeys.length) throw new Error('Handover would duplicate a visit or use a non-working day. Nothing was moved.');
  for (const { source } of moves) source.ids = source.ids.filter(id => id !== customerId);
  for (const { dest } of moves) dest.ids.push(customerId);
  const byId = Object.fromEntries(result.customers.map(c => [c.id, c]));
  for (const d of new Set(moves.flatMap(m => [m.source, m.dest]))) Object.assign(d, scheduleDay(d.ids, result.reps.find(r => r.id === d.rep), byId, result.settings.speed));
  if (from.rep !== to.rep) result.assignments[customerId] = to.rep;
  return result;
}

export function analytics(plan) {
  const counts = Object.fromEntries(plan.customers.map(c => [c.id, 0]));
  plan.days.forEach(d => d.ids.forEach(id => counts[id]++));
  return { frequency: plan.customers.map(c => ({ ...c, planned: counts[c.id], delta: counts[c.id] - c.frequency })),
    reps: plan.reps.map(r => {
      const days = plan.days.filter(d => d.rep === r.id);
      const sum = key => days.reduce((s, d) => s + d[key], 0);
      return { ...r, visits: days.reduce((s, d) => s + d.ids.length, 0), face: sum('face'), travel: sum('travel'), waiting: sum('waiting'), km: sum('km'),
        utilisation: (sum('face') + sum('travel') + sum('waiting')) / capacity(r) * 100,
        overloaded: days.filter(d => d.overload > 0 || d.excessVisits > 0).length,
        weeks: [0, 1, 2, 3].map(w => days.filter(d => d.week === w).reduce((s, d) => s + d.ids.length, 0)) };
    }) };
}
