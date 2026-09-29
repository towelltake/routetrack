<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { analytics, clock, dateAt, dayOf, eligible, located, minutes, moveVisit, validateInputs, weekdays } from './workspace';
import PlanMap from './PlanMap.vue';

const props = defineProps({ routes: { type: Array, default: () => [] } });
const selectedRoutes = ref([]), customers = ref([]), reps = ref([]), assignments = ref({}), assignmentIssues = ref([]);
const start = new Date(); start.setDate(start.getDate() + ((7 - start.getDay()) % 7));
const localDate = `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}-${String(start.getDate()).padStart(2, '0')}`;
const settings = ref({ start: localDate, speed: 40 }), defaults = ref({ frequency: 4, face: 20 });
const tabs = ['Customers', 'Salesmen', 'Assignments', 'Plan', 'Analytics', 'Team estimate'];
const tab = ref('Customers'), busy = ref(''), error = ref(''), notice = ref(''), reviewed = ref(false);
const plan = ref(null), stale = ref(false), search = ref(''), page = ref(1), week = ref(0), repFilter = ref(''), selectedDay = ref('');
const moving = ref(null), destination = ref('');
let worker, request;
const visibleCustomers = computed(() => customers.value.filter(c => `${c.id} ${c.name}`.toLowerCase().includes(search.value.toLowerCase())));
const pageCount = computed(() => Math.max(1, Math.ceil(visibleCustomers.value.length / 20)));
const pageCustomers = computed(() => visibleCustomers.value.slice((page.value - 1) * 20, page.value * 20));
watch(search, () => { page.value = 1; });
const byId = computed(() => Object.fromEntries((plan.value?.customers ?? []).map(c => [c.id, c])));
const metrics = computed(() => plan.value ? analytics(plan.value) : null);
const planDays = computed(() => (plan.value?.days ?? []).filter(d => d.week === week.value && (!repFilter.value || d.rep === repFilter.value)));
const day = computed(() => plan.value?.days.find(d => d.key === selectedDay.value));
const repName = id => (plan.value?.reps ?? reps.value).find(r => r.id === id)?.name ?? id;
const wanted = computed(() => customers.value.reduce((sum, c) => sum + (Number(c.frequency) || 0), 0));
const placed = computed(() => plan.value?.days.reduce((sum, d) => sum + d.ids.length, 0) ?? 0);
const missing = computed(() => plan.value?.issues.reduce((sum, issue) => sum + issue.count, 0) ?? 0);
const targets = computed(() => !moving.value || !plan.value ? [] : plan.value.days.filter(d => d.key !== moving.value.from));
const team = ref({ days: [0, 1, 2, 3, 4], start: '08:00', end: '17:00', maxVisits: 20, travelFactor: 1.3 });
const teamEstimate = computed(() => {
  const t = team.value, available = t.days.length * 4 * (minutes(t.end) - minutes(t.start));
  if (!customers.value.length || !(available > 0) || !(t.maxVisits >= 1) || !(t.travelFactor >= 1)) return null;
  const face = customers.value.reduce((sum, c) => sum + c.frequency * c.face, 0);
  if (!Number.isFinite(face) || face <= 0) return null;
  return { face, available, count: Math.max(1, Math.ceil(face * t.travelFactor / available), Math.ceil(wanted.value / (t.days.length * 4 * t.maxVisits))) };
});

function markChanged() { if (plan.value) stale.value = true; }
watch([customers, reps, settings, assignments], markChanged, { deep: true });

async function loadRoutes() {
  error.value = ''; notice.value = '';
  if (!selectedRoutes.value.length) { error.value = 'Select one or more source routes.'; return; }
  if (customers.value.length && !window.confirm('Replace the current planning inputs and preview with customers from the selected routes?')) return;
  busy.value = 'Loading route customers';
  const controller = new AbortController(); request = controller;
  try {
    const incoming = new Map(), roster = [];
    for (const code of selectedRoutes.value) {
      const { data } = await axios.get('/journey-plan/plan.json', { params: { routecode: code }, signal: controller.signal });
      if (request !== controller || controller.signal.aborted) return;
      const id = String(code);
      roster.push({ id, name: `${data.route.routename} — salesman ${data.route.salesmancode ?? 'unmapped'}`, days: [0, 1, 2, 3, 4], start: '08:00', end: '17:00', maxVisits: 20, areas: '', regions: '', channels: [], lat: '', lng: '', commute: false });
      for (const row of data.rows) {
        const cid = String(row.customercode);
        if (!incoming.has(cid)) incoming.set(cid, { id: cid, name: row.customername || cid, lat: row.fixedlatitude, lng: row.fixedlongitude, legacyFrequency: row.callfrequency, frequency: defaults.value.frequency, face: defaults.value.face, days: [0, 1, 2, 3, 4], area: '', region: '', channel: '', pin: '', windowStart: '', windowEnd: '', sources: [] });
        const c = incoming.get(cid); if (!c.sources.includes(id)) c.sources.push(id);
      }
    }
    if (!incoming.size) throw new Error('Selected routes have no recurring-plan customers. Existing workspace was retained.');
    if (incoming.size > 500) throw new Error('This preview supports 500 customers. Select fewer routes; existing workspace was retained.');
    customers.value = [...incoming.values()]; reps.value = roster;
    assignments.value = Object.fromEntries(customers.value.filter(c => c.sources.length === 1).map(c => [c.id, c.sources[0]]));
    plan.value = null; assignmentIssues.value = []; reviewed.value = false; stale.value = false; page.value = 1;
    tab.value = 'Customers';
    notice.value = `Loaded ${customers.value.length} unique customers. Review the planning defaults and salesman schedules before generating.`;
  } catch (e) { if (!axios.isCancel(e)) error.value = e.response?.data?.message ?? e.message; }
  finally { if (request === controller) busy.value = ''; }
}

function addRep() {
  reps.value.push({ id: `draft-${crypto.randomUUID()}`, name: `Planning salesman ${reps.value.length + 1}`, days: [0, 1, 2, 3, 4], start: '08:00', end: '17:00', maxVisits: 20, areas: '', regions: '', channels: [], lat: '', lng: '', commute: false });
}
function removeRep(id) {
  if (customers.value.some(c => c.pin === id)) { error.value = 'Remove customer pins before removing this salesman.'; return; }
  reps.value = reps.value.filter(r => r.id !== id);
  assignments.value = Object.fromEntries(Object.entries(assignments.value).filter(([, rep]) => rep !== id));
}
function run(action) {
  error.value = ''; notice.value = '';
  const errors = validateInputs(customers.value, reps.value, settings.value);
  if (errors.length) { error.value = errors.slice(0, 12).join('\n'); return; }
  if (action === 'generate' && !reviewed.value) { error.value = 'Review the planning inputs and tick the confirmation on the Plan tab.'; return; }
  if (action === 'generate' && plan.value && !window.confirm('Generate a new preview? Manual changes in the current preview will be replaced.')) return;
  busy.value = action === 'assign' ? 'Assigning customers' : 'Generating four-week plan';
  worker = new Worker(new URL('./planner.worker.js', import.meta.url), { type: 'module' });
  worker.onmessage = async ({ data }) => {
    worker?.terminate(); worker = null; busy.value = '';
    if (data.error) { error.value = data.error; return; }
    if (action === 'assign') {
      assignments.value = data.result.assignments; assignmentIssues.value = data.result.issues;
      notice.value = 'Assignments calculated. You can change them below before generating.';
    } else {
      plan.value = data.result; stale.value = false; week.value = 0; repFilter.value = '';
      selectedDay.value = plan.value.days.find(d => d.ids.length)?.key ?? ''; moving.value = null;
      notice.value = 'Four-week preview generated. Review unmet visits, capacity warnings and frequency conformance.';
    }
  };
  worker.onerror = () => { error.value = 'Planning could not complete. Your inputs and previous preview are retained.'; cancel(); };
  worker.postMessage(JSON.parse(JSON.stringify({ action, customers: customers.value, reps: reps.value, settings: settings.value, assignments: assignments.value })));
}
function cancel() { worker?.terminate(); worker = null; request?.abort(); request = null; busy.value = ''; }
function applyMove() {
  try { plan.value = moveVisit(plan.value, moving.value.customer, moving.value.from, destination.value); moving.value = null; notice.value = 'Visit moved and affected days recalculated. Check frequency and capacity warnings.'; error.value = ''; }
  catch (e) { error.value = e.message; }
}
function download() {
  if (!plan.value) return;
  const records = [['Salesman', 'Date', 'Sequence', 'Customer code', 'Customer', 'Start', 'End', 'Drive estimate (min)', 'Face time (min)', 'Warnings']];
  for (const d of plan.value.days) for (const v of d.visits) records.push([repName(d.rep), d.date, v.sequence, v.customer, byId.value[v.customer].name, clock(v.start), clock(v.end), v.drive, byId.value[v.customer].face, v.warnings.join('; ')]);
  const quote = value => `"${String(value ?? '').replace(/^[\s]*[=+@-]/, "'$&").replaceAll('"', '""')}"`;
  const url = URL.createObjectURL(new Blob(['\uFEFF' + records.map(r => r.map(quote).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' }));
  const a = document.createElement('a'); a.href = url; a.download = `journey-plan-${plan.value.settings.start}.csv`; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
}
onBeforeUnmount(cancel);
</script>

<template>
  <div class="content">
    <div class="alert alert-info">Planning workspace · Changes stay in this open page. Refreshing or leaving discards them. Export a generated plan before leaving. Travel uses straight-line estimates, not road routing.</div>
    <div v-if="error" class="alert alert-danger ws-message" role="alert">{{ error }}</div>
    <div v-if="notice" class="alert alert-success" role="status">{{ notice }}</div>
    <div v-if="busy" class="alert alert-primary d-flex justify-content-between" role="status">{{ busy }}… <button class="btn btn-sm btn-alt-secondary" @click="cancel">Cancel</button></div>
    <fieldset :disabled="!!busy">
      <div class="block block-rounded"><div class="block-content block-content-full">
        <div class="row g-3">
          <div class="col-lg-6"><label for="ws-routes" class="form-label">Source routes (select multiple with Ctrl / Command)</label><select id="ws-routes" v-model="selectedRoutes" multiple class="form-select" size="4"><option v-for="r in props.routes" :key="r.routecode" :value="String(r.routecode)">{{ r.routename }} ({{ r.routecode }})</option></select></div>
          <div class="col-sm-3"><label for="ws-frequency" class="form-label">Initial visits / 4 weeks</label><input id="ws-frequency" v-model.number="defaults.frequency" type="number" min="1" max="20" class="form-control" /><label for="ws-face" class="form-label mt-2">Initial face time / visit (min)</label><input id="ws-face" v-model.number="defaults.face" type="number" min="1" max="240" class="form-control" /></div>
          <div class="col-sm-3 d-flex flex-column justify-content-end"><button class="btn btn-primary" :disabled="!selectedRoutes.length" @click="loadRoutes">Load planning inputs</button><span class="text-muted fs-sm mt-2">{{ customers.length }} customers · {{ reps.length }} planning salesmen</span></div>
        </div>
        <p class="fs-sm text-muted mt-3 mb-0">One editable planning salesman is created per source route. Names, schedules and customer rules are draft inputs until SFA mappings are confirmed. Legacy frequency codes are shown for reference, not converted automatically.</p>
      </div></div>
      <div class="nav nav-pills flex-wrap gap-2 mb-3" aria-label="Planning sections"><button v-for="name in tabs" :key="name" class="nav-link" :class="{ active: tab === name }" :aria-pressed="tab === name" @click="tab = name">{{ name }}</button></div>
      <p v-if="stale" class="alert alert-warning">Inputs have changed since this plan was generated. Calendar, map, analytics and export still describe the previous snapshot. Generate again to apply the new inputs.</p>

      <div v-if="tab === 'Customers'" class="block block-rounded"><div class="block-content block-content-full">
        <h3 class="h5">Customer planning rules</h3><p class="text-muted fs-sm">Allowed days and fixed salesman are mandatory constraints. Time windows are preferred start times and may be exceeded with a warning. Blank area, region and channel mean no customer restriction.</p>
        <label for="ws-search" class="form-label">Search name or code</label><input id="ws-search" v-model="search" class="form-control mb-3" />
        <div v-for="c in pageCustomers" :key="c.id" class="ws-editor mb-3">
          <div class="d-flex justify-content-between"><strong>{{ c.name }} <span class="text-muted">{{ c.id }}</span></strong><span class="badge" :class="located(c) ? 'bg-success' : 'bg-warning text-dark'">{{ located(c) ? 'Location available' : 'Needs coordinates' }}</span></div>
          <div class="row g-2 mt-1">
            <label class="col-sm-3">Visits / 4 weeks <input v-model.number="c.frequency" type="number" min="1" max="20" class="form-control" /><small class="text-muted">Legacy code: {{ c.legacyFrequency ?? '—' }}</small></label>
            <label class="col-sm-3">Face time (min)<input v-model.number="c.face" type="number" min="1" max="240" class="form-control" /></label>
            <label class="col-sm-3">Latitude<input v-model.number="c.lat" type="number" step="any" min="-90" max="90" class="form-control" /></label>
            <label class="col-sm-3">Longitude<input v-model.number="c.lng" type="number" step="any" min="-180" max="180" class="form-control" /></label>
            <label class="col-sm-3">Area<input v-model.trim="c.area" class="form-control" /></label><label class="col-sm-3">Region<input v-model.trim="c.region" class="form-control" /></label>
            <label class="col-sm-3">Channel<select v-model="c.channel" class="form-select"><option value="">Unrestricted</option><option>MT</option><option>TT</option><option>WS</option></select></label>
            <label class="col-sm-3">Fixed salesman<select v-model="c.pin" class="form-select"><option value="">Not fixed</option><option v-for="r in reps" :key="r.id" :value="r.id">{{ r.name }}</option></select></label>
            <label class="col-sm-3">Window start<input v-model="c.windowStart" type="time" class="form-control" /></label><label class="col-sm-3">Window end<input v-model="c.windowEnd" type="time" class="form-control" /></label>
            <div class="col-sm-6"><span>Allowed weekdays</span><div class="d-flex flex-wrap gap-2 mt-2"><label v-for="(name, i) in weekdays" :key="i"><input v-model="c.days" type="checkbox" :value="i" /> {{ name.slice(0, 3) }}</label></div></div>
          </div>
        </div>
        <p v-if="!customers.length" class="text-muted">Load source routes to start editing customer rules.</p>
        <div class="d-flex gap-3 align-items-center"><button class="btn btn-alt-secondary" :disabled="page <= 1" @click="page--">Previous</button><span>Page {{ page }} of {{ pageCount }}</span><button class="btn btn-alt-secondary" :disabled="page >= pageCount" @click="page++">Next</button></div>
      </div></div>

      <div v-if="tab === 'Salesmen'" class="block block-rounded"><div class="block-content block-content-full">
        <div class="d-flex justify-content-between mb-3"><h3 class="h5">Salesman schedules and coverage</h3><button class="btn btn-primary" @click="addRep">Add planning salesman</button></div>
        <div v-for="r in reps" :key="r.id" class="ws-editor mb-3"><div class="row g-3">
          <label class="col-sm-6">Name<input v-model="r.name" class="form-control" /></label><label class="col-sm-2">Shift start<input v-model="r.start" type="time" class="form-control" /></label><label class="col-sm-2">Shift end<input v-model="r.end" type="time" class="form-control" /></label><label class="col-sm-2">Visit target / day<input v-model.number="r.maxVisits" type="number" min="1" max="100" class="form-control" /></label>
          <div class="col-sm-6">Working weekdays<div class="d-flex flex-wrap gap-2 mt-2"><label v-for="(name, i) in weekdays" :key="i"><input v-model="r.days" type="checkbox" :value="i" /> {{ name.slice(0, 3) }}</label></div></div>
          <div class="col-sm-6">Channels (none = all)<div class="d-flex gap-3 mt-2"><label v-for="name in ['MT', 'TT', 'WS']" :key="name"><input v-model="r.channels" type="checkbox" :value="name" /> {{ name }}</label></div></div>
          <label class="col-sm-6">Areas (comma-separated, blank = no area list)<input v-model="r.areas" class="form-control" /></label><label class="col-sm-6">Regions (comma-separated, blank = no region list)<input v-model="r.regions" class="form-control" /></label>
          <label class="col-sm-4">Starting latitude<input v-model.number="r.lat" type="number" step="any" class="form-control" /></label><label class="col-sm-4">Starting longitude<input v-model.number="r.lng" type="number" step="any" class="form-control" /></label>
          <label class="col-sm-4 align-self-end"><input v-model="r.commute" type="checkbox" /> Include outbound and return travel</label>
        </div><button class="btn btn-sm btn-alt-danger mt-3" @click="removeRep(r.id)">Remove from workspace</button></div>
        <p class="text-muted fs-sm">A matching area OR region qualifies a customer; channel coverage also applies. A fixed customer salesman overrides area/channel restrictions. Working hours and visit targets are soft limits, with overload warnings.</p>
      </div></div>

      <div v-if="tab === 'Assignments'" class="block block-rounded"><div class="block-content block-content-full">
        <h3 class="h5">Customer ownership</h3><p class="text-muted">Initial assignments follow the source route when it is unambiguous. Automatic assignment balances face-time demand and proximity. Fixed salesmen always take precedence.</p>
        <button class="btn btn-primary mb-3" :disabled="!customers.length" @click="run('assign')">Calculate assignments</button>
        <p v-for="issue in assignmentIssues" :key="issue.customer" class="text-danger">{{ customers.find(c => c.id === issue.customer)?.name }}: {{ issue.reason }}</p>
        <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Customer</th><th>Four-week face time</th><th>Salesman</th></tr></thead><tbody><tr v-for="c in customers" :key="c.id"><td>{{ c.name }} <small>{{ c.id }}</small></td><td>{{ c.frequency * c.face }} min</td><td><span v-if="c.pin">Fixed: {{ reps.find(r => r.id === c.pin)?.name }}</span><select v-else v-model="assignments[c.id]" class="form-select" :aria-label="`Salesman for ${c.name}`"><option value="">Unassigned</option><option v-for="r in reps.filter(r => eligible(c, r))" :key="r.id" :value="r.id">{{ r.name }}</option></select></td></tr></tbody></table></div>
      </div></div>

      <template v-if="tab === 'Plan'">
        <div class="block block-rounded"><div class="block-content block-content-full">
          <div class="row g-3 align-items-end"><label class="col-sm-3">Period start<input v-model="settings.start" type="date" class="form-control" /></label><div class="col-sm-3">Period end (28 days)<div class="form-control bg-body-light">{{ settings.start && Number.isFinite(Date.parse(settings.start)) ? dateAt(settings.start, 27) : '—' }}</div></div><label class="col-sm-3">Estimated travel speed (km/h)<input v-model.number="settings.speed" type="number" min="5" max="120" class="form-control" /></label><div class="col-sm-3"><button class="btn btn-primary w-100" :disabled="!customers.length || !reviewed" @click="run('generate')">Generate four-week plan</button></div></div>
          <label class="mt-3"><input v-model="reviewed" type="checkbox" /> I have reviewed the customer rules and salesman schedules for this preview.</label>
          <p class="text-muted fs-sm mt-2 mb-0">Frequency 2 uses alternate weeks; frequency 3 uses weeks 1/2/4 or 1/3/4. Higher frequencies spread across weeks. The planner prefers repeat weekdays and separated visits while balancing daily load. Time windows apply to visit start time.</p>
        </div></div>
        <template v-if="plan">
          <div class="d-flex flex-wrap gap-3 align-items-center mb-3"><strong>{{ placed }} visits planned · {{ missing }} unmet visits</strong><span>{{ plan.settings.start }} – {{ dateAt(plan.settings.start, 27) }}</span><button class="btn btn-alt-primary" @click="download">Export plan CSV</button></div>
          <div v-if="plan.issues.length" class="alert alert-warning"><strong>Unmet demand</strong><div v-for="(issue, i) in plan.issues" :key="i">{{ byId[issue.customer]?.name }} — {{ issue.count }} visit(s): {{ issue.reason }}</div></div>
          <div class="d-flex gap-3 mb-3"><label>Week<select v-model.number="week" class="form-select"><option v-for="w in 4" :key="w" :value="w - 1">Week {{ w }}</option></select></label><label>Salesman<select v-model="repFilter" class="form-select"><option value="">All salesmen</option><option v-for="r in plan.reps" :key="r.id" :value="r.id">{{ r.name }}</option></select></label></div>
          <div class="ws-calendar mb-4"><button v-for="d in planDays" :key="d.key" class="ws-day text-start" :class="{ 'ws-overload': d.overload > 0 || d.excessVisits > 0 || d.visits.some(v => v.warnings.length), 'ws-selected': selectedDay === d.key }" @click="selectedDay = d.key"><small>{{ repName(d.rep) }}</small><strong>{{ weekdays[dayOf(d.date)] }} · {{ d.date }}</strong><span>{{ d.ids.length }} visits · {{ d.face + d.travel + d.waiting }} min</span><small v-if="d.overload">{{ d.overload }} min after shift</small><small v-if="d.excessVisits">{{ d.excessVisits }} visits above target</small></button></div>
          <div v-if="day" class="block block-rounded"><div class="block-content block-content-full"><h3 class="h5">{{ repName(day.rep) }} · {{ day.date }}</h3><p class="text-muted">Estimated finish {{ clock(day.finish) }} · Travel {{ day.travel }} min · Face time {{ day.face }} min · Waiting {{ day.waiting }} min · Return travel {{ day.returnMinutes }} min</p>
            <div class="table-responsive"><table class="table"><thead><tr><th>#</th><th>Customer</th><th>Time</th><th>Drive</th><th>Warnings</th><th></th></tr></thead><tbody><tr v-for="v in day.visits" :key="v.customer"><td>{{ v.sequence }}</td><td>{{ byId[v.customer]?.name }}</td><td>{{ clock(v.start) }}–{{ clock(v.end) }}</td><td>{{ v.drive }} min</td><td class="text-danger">{{ v.warnings.join('; ') }}</td><td><button class="btn btn-sm btn-alt-primary" @click="moving = { customer: v.customer, from: day.key }; destination = ''">Move</button></td></tr><tr v-if="!day.visits.length"><td colspan="6">No visits on this day.</td></tr></tbody></table></div>
            <div v-if="moving" class="alert alert-info"><strong>Move {{ byId[moving.customer]?.name }}</strong><p>Changing salesman transfers all this customer's visits in this preview. Moving within the same salesman changes only the selected visit. Affected days are resequenced.</p><label class="d-block">Destination<select v-model="destination" class="form-select mb-2"><option value="">Choose destination</option><option v-for="target in targets" :key="target.key" :value="target.key">{{ repName(target.rep) }} · {{ target.date }}</option></select></label><button class="btn btn-primary me-2" :disabled="!destination" @click="applyMove">Apply move</button><button class="btn btn-alt-secondary" @click="moving = null">Cancel</button></div>
            <PlanMap :day="day" :customers="byId" />
          </div></div>
        </template>
      </template>

      <div v-if="tab === 'Analytics'" class="block block-rounded"><div class="block-content block-content-full"><h3 class="h5">Plan analytics</h3><p v-if="!metrics">Generate a plan to see workload and frequency conformance.</p><template v-else>
        <p>{{ placed }} planned visits · {{ missing }} unmet visits · {{ metrics.frequency.filter(c => c.planned > 0).length }} / {{ metrics.frequency.length }} customers reached</p>
        <div class="table-responsive"><table class="table"><thead><tr><th>Salesman</th><th>Visits</th><th>Face time (min)</th><th>Travel (min)</th><th>Waiting (min)</th><th>Estimated km</th><th>Utilisation</th><th>Overloaded days</th><th>Visits W1 / W2 / W3 / W4</th></tr></thead><tbody><tr v-for="r in metrics.reps" :key="r.id"><td>{{ r.name }}</td><td>{{ r.visits }}</td><td>{{ r.face }}</td><td>{{ r.travel }}</td><td>{{ r.waiting }}</td><td>{{ r.km.toFixed(1) }}</td><td>{{ r.utilisation.toFixed(1) }}%</td><td>{{ r.overloaded }}</td><td>{{ r.weeks.join(' / ') }}</td></tr></tbody></table></div>
        <h4 class="h6 mt-4">Visit frequency conformance</h4><div class="table-responsive"><table class="table"><thead><tr><th>Customer</th><th>Required</th><th>Planned</th><th>Difference</th></tr></thead><tbody><tr v-for="c in metrics.frequency" :key="c.id" :class="{ 'table-warning': c.delta !== 0 }"><td>{{ c.name }}</td><td>{{ c.frequency }}</td><td>{{ c.planned }}</td><td>{{ c.delta > 0 ? '+' : '' }}{{ c.delta }}</td></tr></tbody></table></div>
      </template></div></div>

      <div v-if="tab === 'Team estimate'" class="block block-rounded"><div class="block-content block-content-full"><h3 class="h5">Workload-based team estimate</h3><p>This arithmetic estimate uses face-time demand, a travel allowance and a daily visit target. It does not prove that geography, customer windows or territory constraints are feasible.</p>
        <div class="row g-3"><label class="col-sm-3">Shift start<input v-model="team.start" type="time" class="form-control" /></label><label class="col-sm-3">Shift end<input v-model="team.end" type="time" class="form-control" /></label><label class="col-sm-3">Visit target / day<input v-model.number="team.maxVisits" type="number" min="1" max="100" class="form-control" /></label><label class="col-sm-3">Travel allowance multiplier<input v-model.number="team.travelFactor" type="number" min="1" max="5" step="0.1" class="form-control" /></label></div>
        <div class="d-flex gap-3 flex-wrap my-3"><label v-for="(name, i) in weekdays" :key="i"><input v-model="team.days" type="checkbox" :value="i" /> {{ name }}</label></div>
        <p v-if="teamEstimate" class="fs-4">Estimated team: <strong>{{ teamEstimate.count }} salesmen</strong><small class="d-block fs-sm text-muted">{{ teamEstimate.face }} face-time minutes · {{ wanted }} visits over four weeks · {{ teamEstimate.available }} available minutes per salesman</small></p><p v-else class="text-muted">Load customers and enter a valid working schedule to calculate an estimate.</p>
      </div></div>
    </fieldset>
  </div>
</template>

<style scoped>
.ws-message { white-space: pre-line; }
.ws-editor { border: 1px solid var(--bs-border-color, #ddd); border-radius: 12px; padding: 18px; }
.ws-editor label { font-size: 13px; }
.ws-calendar { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 12px; }
.ws-day { border: 1px solid #dbe1ed; border-radius: 12px; background: var(--bs-body-bg, #fff); color: inherit; padding: 16px; display: flex; flex-direction: column; gap: 7px; }
.ws-overload { background: #fff0e9; color: #733a19; }
.ws-selected { outline: 3px solid #597bce; }
fieldset { min-width: 0; }
</style>
