<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { days, hasCoordinates, optimiseOrder, routeDistance } from './planning';
import Workspace from './Workspace.vue';
const mode = ref('workspace');

const routes = ref([]), routecode = ref(''), rows = ref([]), route = ref(null);
const week = ref(''), day = ref('sunseq'), tab = ref('sequence'), search = ref('');
const busy = ref(false), error = ref(''), proposal = ref(null), mapElement = ref(null);
let map, layer, request;
const weeks = computed(() => [...new Set(rows.value.map(row => String(row.rp32weeknumber)))]);
const weekRows = computed(() => rows.value.filter(row => String(row.rp32weeknumber) === week.value));
const current = computed(() => weekRows.value.filter(row => Number(row[day.value]) > 0)
  .sort((a, b) => Number(a[day.value]) - Number(b[day.value]) || String(a.customercode).localeCompare(String(b.customercode))));
const displayed = computed(() => proposal.value ?? current.value);
const missing = computed(() => current.value.filter(row => !hasCoordinates(row)).length);
const beforeKm = computed(() => routeDistance(current.value));
const afterKm = computed(() => routeDistance(displayed.value));
const changes = computed(() => proposal.value?.filter((row, index) => Number(row[day.value]) !== index + 1).length ?? 0);
const customers = computed(() => weekRows.value.filter(row =>
  `${row.customercode} ${row.alternatecode ?? ''} ${row.customername ?? ''}`.toLowerCase().includes(search.value.toLowerCase())));
const km = value => value == null ? 'Unavailable' : `${value.toFixed(1)} km`;

async function load() {
  request?.abort();
  const controller = new AbortController();
  request = controller;
  rows.value = []; route.value = null; proposal.value = null; error.value = ''; week.value = '';
  if (!routecode.value) { busy.value = false; return; }
  busy.value = true;
  try {
    const { data } = await axios.get('/journey-plan/plan.json', {
      params: { routecode: routecode.value }, signal: controller.signal,
    });
    if (request !== controller) return;
    rows.value = data.rows; route.value = data.route; week.value = weeks.value[0] ?? '';
  } catch (e) {
    if (!controller.signal.aborted) error.value = e.response?.data?.message ?? 'Unable to load the journey plan. Please retry.';
  } finally {
    if (request === controller) busy.value = false;
  }
}

function move(index, offset) {
  const next = [...displayed.value];
  [next[index], next[index + offset]] = [next[index + offset], next[index]];
  proposal.value = next;
}

function exportPreview() {
  const quote = value => `"${String(value ?? '').replace(/^[=+@\-\t\r\n]/, "'$&").replaceAll('"', '""')}"`;
  const records = [['Route', 'Week code', 'Day', 'Customer code', 'Customer', 'Existing sequence', 'Proposed sequence'],
    ...displayed.value.map((row, index) => [routecode.value, week.value, days.find(d => d[0] === day.value)[1],
      row.customercode, row.customername, row[day.value], proposal.value ? index + 1 : row[day.value]])];
  const url = URL.createObjectURL(new Blob(['\uFEFF' + records.map(record => record.map(quote).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' }));
  const anchor = document.createElement('a'); anchor.href = url; anchor.download = 'journey-plan-preview.csv'; anchor.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function draw() {
  await nextTick();
  if (!map) return;
  layer.clearLayers();
  const points = [];
  displayed.value.forEach((row, index) => {
    if (!hasCoordinates(row)) return;
    const point = [Number(row.fixedlatitude), Number(row.fixedlongitude)]; points.push(point);
    const label = document.createElement('span'); label.textContent = `${proposal.value ? index + 1 : row[day.value]}. ${row.customername ?? row.customercode}`;
    L.circleMarker(point, { radius: 7, color: '#3b59b5', fillOpacity: 0.8 }).bindTooltip(label).addTo(layer);
  });
  if (!missing.value && points.length > 1) L.polyline(points, { color: '#3b59b5', dashArray: '6 8' }).addTo(layer);
  map.invalidateSize();
  if (points.length) map.fitBounds(L.latLngBounds(points), { padding: [30, 30], maxZoom: 14 });
}

watch(routecode, load);
watch([week, day], () => { proposal.value = null; });
watch(displayed, draw);
watch(mode, draw);
onMounted(async () => {
  map = L.map(mapElement.value).setView([23.6, 58.4], 7);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
  layer = L.layerGroup().addTo(map);
  try { routes.value = (await axios.get('/journey-plan/routes.json')).data; }
  catch (e) { error.value = e.response?.data?.message ?? 'Unable to load routes. Reload this page to retry.'; }
});
onBeforeUnmount(() => { request?.abort(); map?.remove(); map = null; });
</script>

<template>
  <Head title="Journey Plan Optimisation" />
  <BasePageHeading title="Journey Plan Optimisation" subtitle="Prepare customer rules, assign salesmen and review a four-week plan." />
  <div class="content pb-0"><div class="btn-group"><button class="btn" :class="mode === 'workspace' ? 'btn-primary' : 'btn-alt-secondary'" @click="mode = 'workspace'">Planning workspace</button><button class="btn" :class="mode === 'existing' ? 'btn-primary' : 'btn-alt-secondary'" @click="mode = 'existing'">Existing SFA plan</button></div><p v-if="error && mode === 'workspace'" class="alert alert-danger mt-3" role="alert">{{ error }}</p></div>
  <Workspace v-show="mode === 'workspace'" :routes="routes" />
  <div v-show="mode === 'existing'" class="content">
    <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
    <div class="block block-rounded">
      <div class="block-content block-content-full">
        <div class="row g-3 align-items-end">
          <div class="col-md-5"><label for="jp-route" class="form-label">Route</label>
            <select id="jp-route" v-model="routecode" class="form-select"><option value="">Select a route</option>
              <option v-for="item in routes" :key="item.routecode" :value="item.routecode">{{ item.routename }} ({{ item.routecode }})</option>
            </select>
          </div>
          <div class="col-md-3"><label for="jp-week" class="form-label">Week code</label>
            <select id="jp-week" v-model="week" class="form-select" :disabled="!weeks.length || busy"><option value="">Select week</option>
              <option v-for="value in weeks" :key="value" :value="value">{{ value }}</option></select>
          </div>
          <div class="col-md-3"><label for="jp-day" class="form-label">Day</label>
            <select id="jp-day" v-model="day" class="form-select" :disabled="busy"><option v-for="[key, label] in days" :key="key" :value="key">{{ label }}</option></select>
          </div>
          <div class="col-md-1"><button class="btn btn-alt-secondary" :disabled="busy || !routecode" title="Reload plan" @click="load">Reload</button></div>
        </div>
      </div>
    </div>
    <p v-if="busy" role="status">Loading journey plan…</p>
    <p v-else-if="!routecode" class="text-muted">Select a route to review its recurring journey plan.</p>
    <p v-else-if="route && !rows.length" class="alert alert-info">This route has no recurring plan records.</p>

    <div v-if="rows.length" class="row g-3 mb-4">
      <div class="col-sm-4"><div class="jp-stat"><span>Customers with a positive day sequence</span><strong>{{ current.length }}</strong></div></div>
      <div class="col-sm-4"><div class="jp-stat"><span>Existing straight-line distance</span><strong>{{ km(beforeKm) }}</strong></div></div>
      <div class="col-sm-4"><div class="jp-stat"><span>Preview straight-line distance</span><strong>{{ km(afterKm) }}</strong></div></div>
    </div>
    <div class="row g-4">
      <div class="col-xl-7">
        <div class="block block-rounded">
          <div class="block-header block-header-default"><h3 class="block-title">{{ route?.routename ?? 'Journey plan' }}</h3>
            <div class="btn-group"><button class="btn btn-sm" :class="tab === 'sequence' ? 'btn-primary' : 'btn-alt-secondary'" @click="tab = 'sequence'">Day sequence</button>
              <button class="btn btn-sm" :class="tab === 'customers' ? 'btn-primary' : 'btn-alt-secondary'" @click="tab = 'customers'">Week records</button></div>
          </div>
          <div class="block-content block-content-full">
            <template v-if="tab === 'sequence'">
              <p class="fs-sm text-muted">Candidates are records with a positive sequence for this day. Scheduling flags still need confirmation. Preview distances connect customers directly and exclude the depot and return trip.</p>
              <p v-if="missing" class="alert alert-warning">{{ missing }} customers have missing or invalid coordinates. Automatic optimisation is unavailable; all customers remain in the list.</p>
              <div class="d-flex flex-wrap gap-2 mb-3">
                <button class="btn btn-primary" :disabled="busy || missing > 0 || current.length < 3 || current.length > 1000" @click="proposal = optimiseOrder(current)">Preview optimisation</button>
                <button class="btn btn-alt-secondary" :disabled="!proposal" @click="proposal = null">Reset preview</button>
                <button class="btn btn-alt-secondary" :disabled="!displayed.length" @click="exportPreview">Export CSV</button>
              </div>
              <p v-if="current.length > 1000" class="text-muted">Automatic preview supports up to 1,000 customers per day.</p>
              <p v-if="proposal" role="status">{{ changes }} sequence values would change. Preview only; nothing has been saved.</p>
              <div class="table-responsive"><table class="table table-vcenter table-striped fs-sm">
                <thead><tr><th>Existing</th><th>Preview</th><th>Customer</th><th>Location</th><th>Reorder</th></tr></thead>
                <tbody><tr v-for="(row, index) in displayed" :key="row.primary_key">
                  <td>{{ row[day] }}</td><td>{{ proposal ? index + 1 : row[day] }}</td>
                  <td>{{ row.customername ?? 'Unknown customer' }}<div class="text-muted">{{ row.alternatecode || row.customercode }}</div></td>
                  <td>{{ hasCoordinates(row) ? 'Available' : 'Missing' }}</td>
                  <td class="text-nowrap"><button class="btn btn-sm btn-alt-secondary me-1" :disabled="index === 0" :aria-label="`Move ${row.customername} up`" @click="move(index, -1)">↑</button>
                    <button class="btn btn-sm btn-alt-secondary" :disabled="index === displayed.length - 1" :aria-label="`Move ${row.customername} down`" @click="move(index, 1)">↓</button></td>
                </tr><tr v-if="!displayed.length"><td colspan="5" class="text-center text-muted py-4">No positive sequence records for the selected week and day.</td></tr></tbody>
              </table></div>
            </template>
            <template v-else>
              <label for="jp-search" class="form-label">Find customer</label><input id="jp-search" v-model="search" class="form-control mb-3" placeholder="Name or code" />
              <p class="fs-sm text-muted">Frequency and restriction flags are shown as stored. Their business meanings have not yet been mapped to planning rules.</p>
              <div class="table-responsive"><table class="table table-striped fs-sm">
                <thead><tr><th>Customer</th><th>Frequency code</th><th v-for="[key, label] in days" :key="key">{{ label.slice(0, 3) }}</th><th>Restriction flags 1–7</th></tr></thead>
                <tbody><tr v-for="row in customers" :key="row.primary_key"><td>{{ row.customername }}<div class="text-muted">{{ row.customercode }}</div></td>
                  <td>{{ row.callfrequency ?? '—' }}</td><td v-for="[key] in days" :key="key">{{ row[key] ?? '—' }}</td>
                  <td class="text-nowrap">{{ Array.from({ length: 7 }, (_, i) => row[`callrestrictiondays${i + 1}`] ?? '—').join(' / ') }}</td></tr>
                </tbody></table></div>
            </template>
          </div>
        </div>
      </div>
      <div class="col-xl-5"><div class="block block-rounded"><div class="block-header block-header-default"><h3 class="block-title">Sequence map</h3></div>
        <div class="block-content block-content-full"><div ref="mapElement" class="jp-map"></div><p class="fs-sm text-muted mt-2 mb-0">Dashed connections are straight-line estimates. Hover over a customer to see its preview position.</p></div>
      </div></div>
    </div>
  </div>
</template>

<style scoped>
.jp-stat { background: var(--bs-body-bg, white); border: 1px solid var(--bs-border-color, #e5e7eb); border-radius: 12px; padding: 20px; height: 100%; }
.jp-stat span { display: block; font-size: 13px; color: #687386; }
.jp-stat strong { display: block; margin-top: 8px; font-size: 26px; }
.jp-map { height: 470px; border-radius: 10px; z-index: 0; }
</style>
